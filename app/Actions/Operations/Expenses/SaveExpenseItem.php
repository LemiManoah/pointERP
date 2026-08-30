<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class SaveExpenseItem
{
    public function __construct(private TenantContext $tenantContext, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?ExpenseItem $item = null): ExpenseItem
    {
        $category = ExpenseCategory::query()->findOrFail((string) $data['expense_category_id']);
        $item ??= new ExpenseItem;
        $old = $item->exists ? $item->toArray() : [];
        $item->fill([
            'tenant_id' => $this->tenantContext->id(),
            'expense_category_id' => $category->id,
            'default_unit_of_measure_id' => (bool) $data['has_quantity'] ? ($data['default_unit_of_measure_id'] ?? null) : null,
            'code' => $this->code((string) $data['name'], is_string($data['code'] ?? null) ? $data['code'] : null, $item),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'has_quantity' => $data['has_quantity'],
            'requires_evidence' => $data['requires_evidence'],
            'is_active' => $data['is_active'],
            'created_by' => $item->exists ? $item->getAttribute('created_by') : $actor->id,
            'updated_by' => $actor->id,
        ])->save();
        $this->auditLogger->record($old === [] ? 'expenses.item.created' : 'expenses.item.updated', $item, $actor, $old, $item->fresh()?->toArray() ?? []);

        return $item;
    }

    private function code(string $name, ?string $requested, ExpenseItem $item): string
    {
        $base = Str::upper(Str::slug($requested ?: $name, '_')) ?: 'ITEM';
        $code = Str::limit($base, 50, '');
        $suffix = 1;
        while (ExpenseItem::query()->where('code', $code)->when($item->exists, fn (Builder $query): Builder => $query->whereKeyNot($item->id))->exists()) {
            $code = Str::limit($base, 45, '').'_'.++$suffix;
        }

        return $code;
    }
}
