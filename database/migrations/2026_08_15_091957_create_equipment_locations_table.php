<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equipment_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'eq_loc_tenant_code_uq');
            $table->unique(['tenant_id', 'site_id'], 'eq_loc_tenant_site_uq');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'eq_loc_scope_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_locations');
    }
};
