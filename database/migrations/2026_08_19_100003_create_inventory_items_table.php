<?php

declare(strict_types=1);

use App\Enums\InventoryMaterialClass;
use App\Enums\InventoryTrackingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('inventory_category_id')->constrained('inventory_categories')->restrictOnDelete();
            $table->foreignUuid('stock_unit_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->foreignUuid('preferred_supplier_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('material_class', 40)->default(InventoryMaterialClass::ConstructionMaterial->value);
            $table->string('tracking_type', 20)->default(InventoryTrackingType::None->value);
            $table->string('batch_number', 100)->nullable();
            $table->boolean('is_expires')->default(false);
            $table->boolean('is_for_sale')->default(false);
            $table->decimal('minimum_stock', 20, 4)->nullable();
            $table->decimal('reorder_quantity', 20, 4)->nullable();
            $table->decimal('default_unit_cost', 20, 4)->nullable();
            $table->decimal('default_selling_price', 20, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code'], 'inv_item_tenant_code_uq');
            $table->index(['tenant_id', 'material_class', 'is_active'], 'inv_item_scope_class_idx');
            $table->index(['tenant_id', 'tracking_type', 'is_for_sale'], 'inv_item_tracking_sale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
