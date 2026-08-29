<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ExpenseExchangeRate
{
    /** @return array{id: string|null, rate: float, base_currency_code: string} */
    public function resolve(Branch $branch, string $currencyCode, CarbonInterface $date): array
    {
        $base = $branch->default_currency_code;
        if ($currencyCode === $base) {
            return ['id' => null, 'rate' => 1.0, 'base_currency_code' => $base];
        }

        $rate = $this->rate($branch, $currencyCode, $base, $date);
        if ($rate instanceof ExchangeRate) {
            return ['id' => $rate->id, 'rate' => (float) $rate->rate, 'base_currency_code' => $base];
        }

        $inverse = $this->rate($branch, $base, $currencyCode, $date);
        if ($inverse instanceof ExchangeRate && (float) $inverse->rate > 0) {
            return ['id' => $inverse->id, 'rate' => 1 / (float) $inverse->rate, 'base_currency_code' => $base];
        }

        throw ValidationException::withMessages(['currency_code' => sprintf('No approved exchange rate is available between %s and %s for the expense date.', $currencyCode, $base)]);
    }

    private function rate(Branch $branch, string $from, string $to, CarbonInterface $date): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('status', ExchangeRate::STATUS_APPROVED)
            ->where('from_currency_code', $from)
            ->where('to_currency_code', $to)
            ->whereDate('effective_date', '<=', $date)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhereDate('expires_at', '>=', $date))
            ->where(fn (Builder $query): Builder => $query->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$branch->id])
            ->latest('effective_date')
            ->first();
    }
}
