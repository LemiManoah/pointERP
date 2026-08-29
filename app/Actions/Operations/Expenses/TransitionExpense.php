<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Enums\ExpenseStatus;
use App\Models\DocumentLink;
use App\Models\Expense;
use App\Models\ExpenseLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ExpenseNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionExpense
{
    public function __construct(private AuditLogger $auditLogger, private ExpenseNotificationService $notifications) {}

    public function handle(Expense $expense, ExpenseStatus $target, User $actor, ?string $reason = null): Expense
    {
        return DB::transaction(function () use ($actor, $expense, $reason, $target): Expense {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            $allowed = match ($target) {
                ExpenseStatus::Submitted => $expense->isEditable() && $expense->lines()->exists(),
                ExpenseStatus::Approved, ExpenseStatus::Rejected => $expense->status === ExpenseStatus::Submitted,
                ExpenseStatus::Cancelled => in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Submitted, ExpenseStatus::Rejected], true),
                default => false,
            };
            if (! $allowed) {
                throw ValidationException::withMessages(['expense' => 'That expense status change is not allowed.']);
            }

            if ($target === ExpenseStatus::Submitted) {
                $requiresEvidence = ExpenseLine::query()
                    ->where('expense_id', $expense->id)
                    ->whereHas('item', fn (Builder $query): Builder => $query
                        ->where('requires_evidence', true)
                        ->orWhereHas('category', fn (Builder $category): Builder => $category->where('requires_evidence', true)))
                    ->exists();
                $hasEvidence = DocumentLink::query()
                    ->where('linkable_type', Expense::class)
                    ->where('linkable_id', $expense->id)
                    ->exists();

                if ($requiresEvidence && ! $hasEvidence) {
                    throw ValidationException::withMessages([
                        'expense' => 'Attach a receipt or supporting document before submitting this expense.',
                    ]);
                }
            }

            $old = $expense->toArray();
            /** @var array<string, mixed> $attributes */
            $attributes = ['status' => $target, 'decision_reason' => $reason, 'updated_by' => $actor->id];
            $prefix = match ($target) {
                ExpenseStatus::Submitted => 'submitted',
                ExpenseStatus::Approved => 'approved',
                ExpenseStatus::Rejected => 'rejected',
                ExpenseStatus::Cancelled => 'cancelled',
                default => null,
            };
            if (is_string($prefix)) {
                $attributes[$prefix.'_by'] = $actor->id;
                $attributes[$prefix.'_at'] = now();
            }

            $expense->update($attributes);
            $this->auditLogger->record('expenses.'.$target->value, $expense, $actor, $old, $expense->fresh()?->toArray() ?? [], $reason, $expense->branch);
            DB::afterCommit(fn () => $this->notifications->statusChanged($expense));

            return $expense;
        });
    }
}
