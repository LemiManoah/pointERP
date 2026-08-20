<?php

declare(strict_types=1);

use App\Enums\InventoryStoreType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('equipment_location_id')->nullable()->constrained('equipment_locations')->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('type', 30)->default(InventoryStoreType::Depot->value);
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code'], 'inv_store_tenant_code_uq');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'inv_store_scope_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stores');
    }
};
