<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boq_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('project_estimate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->string('sha256', 64);
            $table->json('state');
            $table->index(['tenant_id', 'project_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boq_imports');
    }
};
