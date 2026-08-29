<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read string $id
 * @property-read string $tenant_id
 * @property-read string $expense_id
 * @property-read string $expense_item_id
 * @property-read string|null $project_id
 * @property-read string|null $site_id
 * @property-read string|null $project_activity_id
 * @property-read string $expense_category_name_snapshot
 * @property-read string $expense_item_name_snapshot
 * @property-read string $quantity
 * @property-read string $unit_amount
 * @property-read string $amount
 * @property-read string $base_currency_amount
 */
#[Fillable(['tenant_id', 'expense_id', 'expense_item_id', 'project_id', 'site_id', 'project_activity_id', 'expense_category_name_snapshot', 'expense_item_name_snapshot', 'description', 'quantity', 'unit_amount', 'amount', 'base_currency_amount', 'sort_order'])]
final class ExpenseLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<Factory<ExpenseLine>> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_amount' => 'decimal:4', 'amount' => 'decimal:4', 'base_currency_amount' => 'decimal:4', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** @return BelongsTo<ExpenseItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ExpenseItem::class, 'expense_item_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<ProjectActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'project_activity_id');
    }

    /** @return HasOne<DsrExpenseReconciliation, $this> */
    public function dsrReconciliation(): HasOne
    {
        return $this->hasOne(DsrExpenseReconciliation::class);
    }
}
