<?php

declare(strict_types=1);

namespace App\Actions\Foundation\CurrencySettings;

use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\TenantCurrency;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SaveBranchCurrency
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{branch_id: string, currency_code: string, is_enabled: bool}  $data
     */
    public function handle(array $data): BranchCurrency
    {
        $tenant = $this->tenantContext->current();
        $branch = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($data['branch_id'])
            ->firstOrFail();

        throw_if($branch->default_currency_code === $data['currency_code'] && ! $data['is_enabled'], InvalidArgumentException::class, 'The branch base currency cannot be disabled.');

        $tenantCurrencyIsEnabled = TenantCurrency::query()
            ->where('tenant_id', $tenant->id)
            ->where('currency_code', $data['currency_code'])
            ->where('is_enabled', true)
            ->exists();

        throw_unless($tenantCurrencyIsEnabled, InvalidArgumentException::class, 'Enable this currency for the tenant before using it on a branch.');

        return DB::transaction(function () use ($branch, $data, $tenant): BranchCurrency {
            $setting = BranchCurrency::withTrashed()->firstOrNew([
                'branch_id' => $branch->id,
                'currency_code' => $data['currency_code'],
            ]);
            $oldValues = $setting->exists ? $this->snapshot($setting) : [];

            if ($setting->trashed()) {
                $setting->restore();
            }

            $setting->fill([
                'tenant_id' => $tenant->id,
                'is_enabled' => $data['is_enabled'],
                'is_default_transaction_currency' => $branch->default_currency_code === $data['currency_code'],
                'can_receive' => $data['is_enabled'],
                'can_pay' => $data['is_enabled'],
            ]);
            $setting->save();

            $this->auditLogger->record(
                event: 'currency.branch_currency.updated',
                subject: $setting,
                oldValues: $oldValues,
                newValues: $this->snapshot($setting),
                branch: $branch,
            );

            return $setting;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BranchCurrency $setting): array
    {
        return [
            'tenant_id' => $setting->tenant_id,
            'branch_id' => $setting->branch_id,
            'currency_code' => $setting->currency_code,
            'is_enabled' => $setting->is_enabled,
            'is_default_transaction_currency' => $setting->is_default_transaction_currency,
            'can_receive' => $setting->can_receive,
            'can_pay' => $setting->can_pay,
        ];
    }
}
