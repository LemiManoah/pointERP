<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('name');
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('reporting_deadline')->nullable();
            $table->string('status')->default('planned');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'reference']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
