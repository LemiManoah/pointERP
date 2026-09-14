<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_item_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 80);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('tenant_id', 'wic_tenant_fk')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('created_by', 'wic_created_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by', 'wic_updated_fk')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'code'], 'wic_tenant_code_uq');
            $table->unique(['tenant_id', 'name'], 'wic_tenant_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_categories');
    }
};
