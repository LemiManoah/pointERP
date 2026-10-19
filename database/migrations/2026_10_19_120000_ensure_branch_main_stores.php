<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $branches = DB::table('branches')
            ->join('tenants', 'tenants.id', '=', 'branches.tenant_id')
            ->whereNull('branches.deleted_at')
            ->whereNull('tenants.deleted_at')
            ->select('branches.id', 'branches.tenant_id', 'branches.code', 'branches.name', 'tenants.multi_store_enabled')
            ->get();

        foreach ($branches as $branch) {
            $stores = DB::table('inventory_stores')
                ->where('tenant_id', $branch->tenant_id)
                ->where('branch_id', $branch->id)
                ->whereNull('deleted_at')
                ->where('is_active', true);

            if ((clone $stores)->whereIn('type', ['depot', 'warehouse'])->exists()) {
                continue;
            }

            if (! $branch->multi_store_enabled && (clone $stores)->exists()) {
                continue;
            }

            $code = $branch->code.'-MAIN';
            if (DB::table('inventory_stores')->where('tenant_id', $branch->tenant_id)->where('code', $code)->exists()) {
                continue;
            }

            DB::table('inventory_stores')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'code' => $code,
                'name' => $branch->name.' Main Store',
                'type' => 'depot',
                'is_default_for_site' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Store records may have received stock after creation, so rollback leaves them intact.
    }
};
