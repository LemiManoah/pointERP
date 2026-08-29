<?php

declare(strict_types=1);

namespace App\Actions\Operations\Expenses;

use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SaveExpenseCategory
{
    public function __construct(private TenantContext $tenantContext, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?ExpenseCategory $category = null): ExpenseCategory
    {
        $category ??= new ExpenseCategory;
        if ($category->exists && $category->is_active && ! (bool) $data['is_active'] && $category->items()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_active' => 'Deactivate the active expense items in this expense type first.']);
        }

        $old = $category->exists ? $category->toArray() : [];
        $category->fill([
            'tenant_id' => $this->tenantContext->id(),
            'code' => $this->code((string) $data['name'], is_string($data['code'] ?? null) ? $data['code'] : null, $category),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'requires_evidence' => $data['requires_evidence'],
            'is_active' => $data['is_active'],
            'created_by' => $category->exists ? $category->getAttribute('created_by') : $actor->id,
            'updated_by' => $actor->id,
        ])->save();
        $this->auditLogger->record($old === [] ? 'expenses.category.created' : 'expenses.category.updated', $category, $actor, $old, $category->fresh()?->toArray() ?? []);

        return $category;
    }

    private function code(string $name, ?string $requested, ExpenseCategory $category): string
    {
        $base = Str::upper(Str::slug($requested ?: $name, '_')) ?: 'CATEGORY';
        $code = Str::limit($base, 40, '');
        $suffix = 1;

        while (ExpenseCategory::query()->where('code', $code)->when($category->exists, fn (Builder $query): Builder => $query->whereKeyNot($category->id))->exists()) {
            $code = Str::limit($base, 35, '').'_'.++$suffix;
        }

        return $code;
    }
}
