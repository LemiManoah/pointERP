<?php

declare(strict_types=1);

namespace App\Actions\Foundation\CurrencySettings;

use App\Models\Branch;
use App\Models\BranchCurrency;
use App\Models\TenantCurrency;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SaveBranchCurrency
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    /**
     * @param  array{branch_id: string, currency_code: string, is_enabled: bool, is_default_transaction_currency: bool, can_receive: bool, can_pay: bool}  $data
     */
    public function handle(array $data): BranchCurrency
    {
        $tenant = $this->tenantContext->current();
        $branch = Branch::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($data['branch_id'])
            ->firstOrFail();

        if ($branch->default_currency_code === $data['currency_code'] && ! $data['is_enabled']) {
            throw new InvalidArgumentException('The branch base currency cannot be disabled.');
        }

        if (! $data['is_enabled'] && $data['is_default_transaction_currency']) {
            throw new InvalidArgumentException('A disabled branch currency cannot be the transaction default.');
        }

        $tenantCurrencyIsEnabled = TenantCurrency::query()
            ->where('tenant_id', $tenant->id)
            ->where('currency_code', $data['currency_code'])
            ->where('is_enabled', true)
            ->exists();

        if (! $tenantCurrencyIsEnabled) {
            throw new InvalidArgumentException('Enable this currency for the tenant before using it on a branch.');
        }

        return DB::transaction(function () use ($branch, $data, $tenant): BranchCurrency {
            if ($data['is_default_transaction_currency']) {
                BranchCurrency::query()
                    ->where('branch_id', $branch->id)
                    ->where('currency_code', '!=', $data['currency_code'])
                    ->update(['is_default_transaction_currency' => false]);
            }

            $setting = BranchCurrency::withTrashed()->updateOrCreate(
                ['branch_id' => $branch->id, 'currency_code' => $data['currency_code']],
                [
                    'tenant_id' => $tenant->id,
                    'is_enabled' => $data['is_enabled'],
                    'is_default_transaction_currency' => $data['is_default_transaction_currency'],
                    'can_receive' => $data['can_receive'],
                    'can_pay' => $data['can_pay'],
                ],
            );

            if ($setting->trashed()) {
                $setting->restore();
            }

            return $setting;
        });
    }
}
