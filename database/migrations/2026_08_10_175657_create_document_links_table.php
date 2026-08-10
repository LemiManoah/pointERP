<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type');
            $table->uuid('linkable_id');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'linkable_type', 'linkable_id'], 'document_links_target_unique');
            $table->index(['tenant_id', 'linkable_type', 'linkable_id'], 'document_links_tenant_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};
