<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'currency_code']);
            $table->index(['tenant_id', 'is_enabled', 'is_default']);
            $table->foreign('currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });

        Schema::create('branch_currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default_transaction_currency')->default(false);
            $table->boolean('can_receive')->default(true);
            $table->boolean('can_pay')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'currency_code']);
            $table->index(['tenant_id', 'branch_id', 'is_enabled']);
            $table->foreign('currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('from_currency_code', 3);
            $table->char('to_currency_code', 3);
            $table->decimal('rate', 20, 10);
            $table->date('effective_date');
            $table->timestamp('expires_at')->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('draft');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'tenant_id',
                'branch_id',
                'from_currency_code',
                'to_currency_code',
                'status',
                'effective_date',
            ], 'exchange_rates_lookup_index');
            $table->foreign('from_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
            $table->foreign('to_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('branch_currencies');
        Schema::dropIfExists('tenant_currencies');
    }
};
