<?php

declare(strict_types=1);

namespace App\Actions\Foundation\ExchangeRates;

use App\Models\Branch;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SaveExchangeRate
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{branch_id?: string|null, from_currency_code: string, to_currency_code: string, rate: int|float|string, effective_date: string, expires_at?: string|null}  $data
     */
    public function handle(array $data, User $actor, ?ExchangeRate $exchangeRate = null): ExchangeRate
    {
        $tenant = $this->tenantContext->current();

        throw_if($exchangeRate instanceof ExchangeRate && $exchangeRate->status !== ExchangeRate::STATUS_DRAFT, InvalidArgumentException::class, 'Only draft exchange rates can be edited.');

        throw_if(
            $tenant->is_multibranch
            && ($data['branch_id'] ?? null) === null
            && ! $actor->can('exchange-rates.manage-facility-wide'),
            InvalidArgumentException::class,
            'You do not have permission to manage facility-wide exchange rates.',
        );

        if (($data['branch_id'] ?? null) !== null) {
            Branch::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($data['branch_id'])
                ->firstOrFail();
        }

        return DB::transaction(function () use ($actor, $data, $exchangeRate, $tenant): ExchangeRate {
            $oldValues = $exchangeRate instanceof ExchangeRate ? $this->snapshot($exchangeRate) : [];
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

                $this->auditLogger->record(
                    event: 'currency.exchange_rate.updated',
                    subject: $exchangeRate,
                    actor: $actor,
                    oldValues: $oldValues,
                    newValues: $this->snapshot($exchangeRate),
                );

                return $exchangeRate;
            }

            $createdExchangeRate = ExchangeRate::query()->create([
                ...$attributes,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->record(
                event: 'currency.exchange_rate.created',
                subject: $createdExchangeRate,
                actor: $actor,
                newValues: $this->snapshot($createdExchangeRate),
            );

            return $createdExchangeRate;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ExchangeRate $exchangeRate): array
    {
        return [
            'tenant_id' => $exchangeRate->tenant_id,
            'branch_id' => $exchangeRate->branch_id,
            'from_currency_code' => $exchangeRate->from_currency_code,
            'to_currency_code' => $exchangeRate->to_currency_code,
            'rate' => $exchangeRate->rate,
            'effective_date' => $exchangeRate->effective_date->toDateString(),
            'expires_at' => $exchangeRate->expires_at?->toDateTimeString(),
            'source' => $exchangeRate->source,
            'status' => $exchangeRate->status,
            'approved_by' => $exchangeRate->approved_by,
            'approved_at' => $exchangeRate->approved_at?->toDateTimeString(),
            'created_by' => $exchangeRate->created_by,
            'updated_by' => $exchangeRate->updated_by,
        ];
    }
}
