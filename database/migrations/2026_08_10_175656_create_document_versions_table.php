<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->unique(['document_id', 'version_number'], 'document_versions_number_unique');
            $table->index(['tenant_id', 'document_id'], 'document_versions_tenant_document_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
