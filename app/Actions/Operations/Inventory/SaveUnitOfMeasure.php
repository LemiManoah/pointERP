<?php

declare(strict_types=1);

namespace App\Actions\Operations\Inventory;

use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantContext;
use Illuminate\Support\Str;

final readonly class SaveUnitOfMeasure
{
    public function __construct(private AuditLogger $auditLogger, private TenantContext $tenantContext) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor, ?UnitOfMeasure $unit = null): UnitOfMeasure
    {
        $attributes = ['tenant_id' => $this->tenantContext->id(), 'code' => $data['code'] ?? $this->generateCode($data), 'name' => $data['name'], 'symbol' => $data['symbol'] ?? null, 'quantity_dimension' => $data['quantity_dimension'], 'is_base_unit' => $data['is_base_unit'], 'is_active' => $data['is_active']];
        $old = $unit?->only(array_keys($attributes)) ?? [];
        if ($unit instanceof UnitOfMeasure) {
            $unit->update($attributes);
            $event = 'inventory.unit.updated';
        } else {
            $unit = UnitOfMeasure::query()->create($attributes);
            $event = 'inventory.unit.created';
        }

        $this->auditLogger->record($event, $unit, $actor, $old, $unit->fresh()?->toArray() ?? []);

        return $unit;
    }

    /** @param array<string, mixed> $data */
    private function generateCode(array $data): string
    {
        $source = mb_trim((string) ($data['symbol'] ?? '')) ?: (string) $data['name'];
        $base = Str::upper(Str::slug($source, '_'));
        $base = $base !== '' ? $base : 'UNIT';
        $tenantId = $this->tenantContext->id();
        $suffix = 1;

        do {
            $ending = $suffix === 1 ? '' : '_'.$suffix;
            $code = mb_substr($base, 0, 30 - mb_strlen($ending)).$ending;
            $suffix++;
        } while (UnitOfMeasure::query()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }
}
