<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Enums\ExpensePayeeType;
use App\Enums\ExpenseStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Site;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ExpenseExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type ExpenseLinePayload array{expense_item_id: string, project_id?: string|null, site_id?: string|null, project_activity_id?: string|null, description?: string|null, quantity: numeric-string, unit_amount: numeric-string}
 * @phpstan-type ExpensePayload array{branch_id: string, expense_date: string, payee_type: string, customer_id?: string|null, staff_id?: string|null, payee_name?: string|null, currency_code: string, description?: string|null, reference?: string|null, lines: list<ExpenseLinePayload>, initial_payment_amount?: numeric-string|null, initial_payment_method?: string|null, initial_payment_reference?: string|null}
 */
final readonly class SaveExpense
{
    public function __construct(
        private AuditLogger $auditLogger,
        private ExpenseExchangeRate $exchangeRates,
        private RecordExpensePayment $recordPayment,
    ) {}

    /** @param ExpensePayload $data */
    public function handle(array $data, User $actor, ?Expense $expense = null, bool $canRecordPayment = false): Expense
    {
        return DB::transaction(function () use ($actor, $canRecordPayment, $data, $expense): Expense {
            if ($expense instanceof Expense && ! $expense->isEditable()) {
                throw ValidationException::withMessages(['expense' => 'Only draft or rejected expenses can be changed.']);
            }

            $isNew = ! $expense instanceof Expense;
            $expense ??= new Expense;
            $old = $expense->exists ? $expense->load('lines')->toArray() : [];
            $branch = Branch::query()->findOrFail($data['branch_id']);
            $expenseDate = CarbonImmutable::parse($data['expense_date']);
            $rate = $this->exchangeRates->resolve($branch, $data['currency_code'], $expenseDate);
            $payee = $this->payee($data);
            $linePayloads = [];
            $total = 0.0;

            foreach ($data['lines'] as $index => $line) {
                $item = ExpenseItem::query()->with('category')->findOrFail($line['expense_item_id']);
                $this->assertAllocation($branch, $line, $index);
                $amount = (float) $line['quantity'] * (float) $line['unit_amount'];
                $total += $amount;
                $linePayloads[] = [
                    'tenant_id' => $branch->tenant_id,
                    'expense_item_id' => $item->id,
                    'project_id' => $line['project_id'] ?? null,
                    'site_id' => $line['site_id'] ?? null,
                    'project_activity_id' => $line['project_activity_id'] ?? null,
                    'expense_category_name_snapshot' => $item->category->name,
                    'expense_item_name_snapshot' => $item->name,
                    'description' => $line['description'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_amount' => $line['unit_amount'],
                    'amount' => $amount,
                    'base_currency_amount' => $amount * $rate['rate'],
                    'sort_order' => $index,
                ];
            }

            if ($expense->exists && $expense->paidAmount() > $total + 0.0001) {
                throw ValidationException::withMessages(['lines' => 'The expense total cannot be lower than payments already recorded.']);
            }

            $expense->fill([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'expense_number' => $expense->exists ? $expense->expense_number : 'EXP-'.now()->format('Ym').'-'.mb_strtoupper(str()->random(6)),
                'expense_date' => $expenseDate,
                'payee_type' => $data['payee_type'],
                'customer_id' => $data['customer_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'payee_name_snapshot' => $payee,
                'currency_code' => $data['currency_code'],
                'base_currency_code' => $rate['base_currency_code'],
                'exchange_rate_id' => $rate['id'],
                'exchange_rate' => $rate['rate'],
                'subtotal' => $total,
                'total_amount' => $total,
                'base_currency_total' => $total * $rate['rate'],
                'description' => $data['description'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => $expense->exists
                    ? ($expense->status === ExpenseStatus::Rejected ? ExpenseStatus::Draft : $expense->status)
                    : ExpenseStatus::Draft,
                'decision_reason' => null,
                'created_by' => $expense->exists ? $expense->getAttribute('created_by') : $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            $expense->lines()->delete();
            foreach ($linePayloads as $payload) {
                $expense->lines()->create($payload);
            }

            $initialPayment = (float) ($data['initial_payment_amount'] ?? 0);
            if ($isNew && $initialPayment > 0) {
                if (! $canRecordPayment) {
                    throw ValidationException::withMessages(['initial_payment_amount' => 'You do not have permission to record payments.']);
                }

                $this->recordPayment->handle($expense, [
                    'paid_at' => $data['expense_date'],
                    'amount' => (string) $initialPayment,
                    'payment_method' => (string) $data['initial_payment_method'],
                    'reference' => $data['initial_payment_reference'] ?? null,
                    'notes' => 'Initial payment recorded with expense.',
                ], $actor, true);
            }

            $expense->load(['lines', 'payments']);
            $this->auditLogger->record($isNew ? 'expenses.created' : 'expenses.updated', $expense, $actor, $old, $expense->toArray(), branch: $branch);

            return $expense;
        });
    }

    /** @param ExpensePayload $data */
    private function payee(array $data): string
    {
        return match (ExpensePayeeType::from($data['payee_type'])) {
            ExpensePayeeType::Company => Customer::query()->findOrFail((string) $data['customer_id'])->name,
            ExpensePayeeType::Staff => Staff::query()->findOrFail((string) $data['staff_id'])->name,
            ExpensePayeeType::Other => (string) $data['payee_name'],
        };
    }

    /** @param array<string, mixed> $line */
    private function assertAllocation(Branch $branch, array $line, int $index): void
    {
        $project = isset($line['project_id']) ? Project::query()->find($line['project_id']) : null;
        $site = isset($line['site_id']) ? Site::query()->find($line['site_id']) : null;
        $activity = isset($line['project_activity_id']) ? ProjectActivity::query()->find($line['project_activity_id']) : null;
        if ($project instanceof Project && $project->branch_id !== $branch->id) {
            throw ValidationException::withMessages([sprintf('lines.%d.project_id', $index) => 'The project must belong to the expense branch.']);
        }

        if ($site instanceof Site && (! $project instanceof Project || $site->project_id !== $project->id)) {
            throw ValidationException::withMessages([sprintf('lines.%d.site_id', $index) => 'The site must belong to the selected project.']);
        }

        if ($activity instanceof ProjectActivity && (! $project instanceof Project || $activity->project_id !== $project->id)) {
            throw ValidationException::withMessages([sprintf('lines.%d.project_activity_id', $index) => 'The Work item must belong to the selected project.']);
        }
    }
}
