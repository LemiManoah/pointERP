<?php

declare(strict_types=1);

use App\Enums\UnitDimension;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_of_measures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->string('symbol', 20)->nullable();
            $table->string('quantity_dimension', 30)->default(UnitDimension::Count->value);
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code'], 'uom_tenant_code_uq');
            $table->index(['tenant_id', 'quantity_dimension', 'is_active'], 'uom_scope_dim_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_of_measures');
    }
};
