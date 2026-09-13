<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->string('employment_type', 40)->default('permanent')->after('staff_position_id');
            $table->foreignUuid('primary_trade_id')
                ->nullable()
                ->after('employment_type')
                ->constrained('workforce_trades')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->dropForeign(['primary_trade_id']);
            $table->dropColumn(['employment_type', 'primary_trade_id']);
        });
    }
};
