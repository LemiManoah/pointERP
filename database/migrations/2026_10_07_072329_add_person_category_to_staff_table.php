<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            $table->string('person_category')->default('company_staff')->index();
            $table->string('email')->nullable()->change();
            $table->uuid('staff_position_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw_if(DB::table('staff')->whereNull('email')->orWhereNull('staff_position_id')->exists(), RuntimeException::class, 'Staff email and position must be populated before reversing this migration.');

        Schema::table('staff', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->uuid('staff_position_id')->nullable(false)->change();
            $table->dropIndex(['person_category']);
            $table->dropColumn('person_category');
        });
    }
};
