<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class ExpenseNotificationService
{
    public function __construct(private OperationalNotificationSender $notifications) {}

    public function statusChanged(Expense $expense): void
    {
        $recipients = $expense->status === ExpenseStatus::Submitted
            ? $this->approvers($expense)
            : $this->participants($expense);
        $title = match ($expense->status) {
            ExpenseStatus::Submitted => 'Expense awaiting approval',
            ExpenseStatus::Approved => 'Expense approved',
            ExpenseStatus::Rejected => 'Expense rejected',
            ExpenseStatus::Cancelled => 'Expense cancelled',
            default => 'Expense updated',
        };
        $severity = match ($expense->status) {
            ExpenseStatus::Approved => 'success',
            ExpenseStatus::Rejected, ExpenseStatus::Cancelled => 'warning',
            default => 'info',
        };

        $this->notifications->send($recipients, [
            'tenant_id' => $expense->tenant_id,
            'branch_id' => $expense->branch_id,
            'expense_id' => $expense->id,
            'category' => 'expenses',
            'severity' => $severity,
            'title' => $title,
            'message' => $expense->expense_number.' is now '.$expense->status->label().'.',
            'action_url' => '/expenses/'.$expense->id,
        ]);
    }

    /** @return Collection<int, User> */
    private function approvers(Expense $expense): Collection
    {
        return User::query()
            ->where('tenant_id', $expense->tenant_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can('expenses.approve') && ($user->can('branches.view-all') || $user->branches()->whereKey($expense->branch_id)->exists()))
            ->values();
    }

    /** @return Collection<int, User> */
    private function participants(Expense $expense): Collection
    {
        $participantIds = [];
        foreach ([$expense->getAttribute('created_by'), $expense->getAttribute('submitted_by')] as $participantId) {
            if (is_string($participantId)) {
                $participantIds[] = $participantId;
            }
        }

        return User::query()
            ->where('tenant_id', $expense->tenant_id)
            ->whereIn('id', $participantIds)
            ->where('is_active', true)
            ->get();
    }
}
