<?php

declare(strict_types=1);

namespace App\Actions\Workforce;

use App\Models\User;
use App\Models\WorkforceTrade;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final readonly class SaveWorkforceTrade
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantContext $tenantContext,
    ) {}

    /** @param array{code?: string|null, name: string, category: string, is_active: bool} $data */
    public function handle(array $data, User $actor, ?WorkforceTrade $trade = null): WorkforceTrade
    {
        $attributes = [
            'tenant_id' => $this->tenantContext->id(),
            'code' => $this->code($data['code'] ?? null, $data['name'], $trade),
            'name' => $data['name'],
            'category' => $data['category'],
            'is_active' => $data['is_active'],
            'updated_by' => $actor->id,
        ];
        $oldValues = $trade?->only(array_keys($attributes)) ?? [];

        if ($trade instanceof WorkforceTrade) {
            $trade->update($attributes);
            $event = 'workforce.trade.updated';
        } else {
            $trade = WorkforceTrade::query()->create([...$attributes, 'created_by' => $actor->id]);
            $event = 'workforce.trade.created';
        }

        $this->auditLogger->record($event, $trade, $actor, $oldValues, $trade->fresh()?->toArray() ?? []);

        return $trade;
    }

    private function code(?string $requested, string $name, ?WorkforceTrade $trade): string
    {
        $base = Str::upper(mb_trim((string) $requested));

        if ($base === '') {
            $base = Str::upper(Str::slug($name, '-'));
        }

        $base = mb_substr($base === '' ? 'TRADE' : $base, 0, 40);
        $candidate = $base;
        $suffix = 2;

        while (WorkforceTrade::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('code', $candidate)
            ->when($trade instanceof WorkforceTrade, fn (Builder $query): Builder => $query->where('id', '!=', $trade->id))
            ->exists()) {
            $candidate = mb_substr($base, 0, 35).'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
