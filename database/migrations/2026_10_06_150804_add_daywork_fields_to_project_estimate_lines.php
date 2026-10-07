<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_estimate_lines', function (Blueprint $table): void {
            $table->string('daywork_resource_type', 20)->nullable();
            $table->foreignUuid('daywork_inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignUuid('daywork_equipment_category_id')->nullable()->constrained('equipment_categories')->nullOnDelete();
            $table->foreignUuid('daywork_workforce_trade_id')->nullable()->constrained('workforce_trades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_estimate_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('daywork_inventory_item_id');
            $table->dropConstrainedForeignId('daywork_equipment_category_id');
            $table->dropConstrainedForeignId('daywork_workforce_trade_id');
            $table->dropColumn('daywork_resource_type');
        });
    }
};
