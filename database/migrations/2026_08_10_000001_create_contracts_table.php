<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('title');
            $table->text('scope_summary')->nullable();
            $table->decimal('contract_value', 20, 4)->nullable();
            $table->char('currency_code', 3);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('retention_percent', 8, 4)->nullable();
            $table->text('payment_terms')->nullable();
            $table->string('status')->default('draft');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
