<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingTenantContextException;
use App\Models\AuditActivity;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;

final readonly class AuditLogger
{
    public function __construct(private TenantContext $tenantContext)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        Model $subject,
        ?User $actor = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?Branch $branch = null,
        array $properties = [],
    ): void {
        $actor ??= $this->actor();
        $tenantId = $this->tenantId($subject, $actor);
        $branchId = $branch->id ?? $this->branchId($subject);
        $request = request();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        $logger = activity('audit')
            ->event($event)
            ->performedOn($subject)
            ->withChanges([
                'old' => $oldValues,
                'attributes' => $newValues,
            ])
            ->withProperties([
                ...$properties,
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'reason' => $reason,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ])
            ->tap(function (Activity $activity) use ($branchId, $ipAddress, $reason, $tenantId, $userAgent): void {
                if (! $activity instanceof AuditActivity) {
                    return;
                }

                $activity->forceFill([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'reason' => $reason,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            });

        if ($actor instanceof User) {
            $logger->causedBy($actor);
        } else {
            $logger->causedByAnonymous();
        }

        $logger->log($event);
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function tenantId(Model $subject, ?User $actor): ?string
    {
        if ($subject instanceof Tenant) {
            return $subject->id;
        }

        $attributes = $subject->getAttributes();
        $tenantId = $attributes['tenant_id'] ?? null;

        if (is_string($tenantId)) {
            return $tenantId;
        }

        if ($actor instanceof User) {
            return $actor->tenant_id;
        }

        try {
            return $this->tenantContext->id();
        } catch (MissingTenantContextException) {
            return null;
        }
    }

    private function branchId(Model $subject): ?string
    {
        $attributes = $subject->getAttributes();
        $branchId = $attributes['branch_id'] ?? null;

        return is_string($branchId) ? $branchId : null;
    }
}
