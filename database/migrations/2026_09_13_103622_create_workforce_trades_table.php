<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_trades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('category', 40);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'workforce_trades_tenant_code_unique');
            $table->index(['tenant_id', 'is_active'], 'workforce_trades_tenant_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_trades');
    }
};
