<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->date('document_date')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('confidentiality')->default('normal');
            $table->string('status')->default('active');
            $table->uuid('current_version_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id', 'status'], 'documents_tenant_branch_status_index');
            $table->index(['tenant_id', 'document_type_id'], 'documents_tenant_type_index');
            $table->index(['tenant_id', 'expires_on'], 'documents_tenant_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
