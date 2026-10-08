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
            $table->decimal('percentage_rate', 18, 4)->nullable();
            $table->json('percentage_base_keys')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_estimate_lines', function (Blueprint $table): void {
            $table->dropColumn(['percentage_rate', 'percentage_base_keys']);
        });
    }
};
