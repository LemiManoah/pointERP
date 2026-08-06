<?php

declare(strict_types=1);

namespace App\Actions\Foundation\ExchangeRates;

use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SaveExchangeRate
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    /**
     * @param  array{branch_id?: string|null, from_currency_code: string, to_currency_code: string, rate: int|float|string, effective_date: string, expires_at?: string|null}  $data
     */
    public function handle(array $data, User $actor, ?ExchangeRate $exchangeRate = null): ExchangeRate
    {
        $tenant = $this->tenantContext->current();

        if ($exchangeRate instanceof ExchangeRate && $exchangeRate->status !== ExchangeRate::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only draft exchange rates can be edited.');
        }

        if (($data['branch_id'] ?? null) === null && ! $actor->can('branches.view-all')) {
            throw new InvalidArgumentException('Only users with all-branch access can create tenant-wide exchange rates.');
        }

        if (($data['branch_id'] ?? null) !== null) {
            Branch::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($data['branch_id'])
                ->firstOrFail();
        }

        return DB::transaction(function () use ($actor, $data, $exchangeRate, $tenant): ExchangeRate {
            $attributes = [
                'tenant_id' => $tenant->id,
                'branch_id' => $data['branch_id'] ?? null,
                'from_currency_code' => mb_strtoupper($data['from_currency_code']),
                'to_currency_code' => mb_strtoupper($data['to_currency_code']),
                'rate' => $data['rate'],
                'effective_date' => $data['effective_date'],
                'expires_at' => $data['expires_at'] ?? null,
                'source' => 'manual',
                'status' => ExchangeRate::STATUS_DRAFT,
                'updated_by' => $actor->id,
            ];

            if ($exchangeRate instanceof ExchangeRate) {
                $exchangeRate->update($attributes);

                return $exchangeRate;
            }

            return ExchangeRate::query()->create([
                ...$attributes,
                'created_by' => $actor->id,
            ]);
        });
    }
}
