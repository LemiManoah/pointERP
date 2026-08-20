<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_unit_conversions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('from_unit_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->foreignUuid('to_unit_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->decimal('multiplier', 24, 10);
            $table->decimal('divisor', 24, 10)->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['inventory_item_id', 'from_unit_id', 'to_unit_id'], 'inv_conv_item_units_uq');
            $table->index(['tenant_id', 'is_active'], 'inv_conv_scope_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_unit_conversions');
    }
};
