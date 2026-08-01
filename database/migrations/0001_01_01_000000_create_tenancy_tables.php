<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->string('name');
            $table->char('iso3_code', 3)->unique();
            $table->char('default_currency_code', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('default_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->char('default_currency_code', 3)->default('USD');
            $table->boolean('is_multibranch')->default(false);
            $table->boolean('multi_currency_enabled')->default(false);
            $table->string('timezone')->default('Africa/Kampala');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('default_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->char('country_code', 2);
            $table->char('default_currency_code', 3);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->foreign('country_code')
                ->references('code')
                ->on('countries')
                ->restrictOnDelete();
            $table->foreign('default_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });

        Schema::create('staff_positions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('staff', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('staff_position_id')->constrained()->restrictOnDelete();
            $table->string('staff_number');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'staff_number']);
        });
    }
};
