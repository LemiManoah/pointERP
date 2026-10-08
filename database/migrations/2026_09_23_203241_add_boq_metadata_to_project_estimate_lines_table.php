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
            $table->string('bill', 160)->nullable();
            $table->string('section', 160)->nullable();
            $table->string('element', 160)->nullable();
            $table->string('item_type', 30)->default('measured');
            $table->text('description')->nullable();
            $table->string('source_document')->nullable();
            $table->string('source_sheet', 80)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->index(['project_estimate_id', 'item_type'], 'proj_est_line_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_estimate_lines', function (Blueprint $table): void {
            $table->dropIndex('proj_est_line_type_idx');
            $table->dropColumn(['bill', 'section', 'element', 'item_type', 'description', 'source_document', 'source_sheet', 'source_row']);
        });
    }
};
