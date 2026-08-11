<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('document_number')->nullable()->after('reference');
            $table->string('revision', 50)->nullable()->after('document_number');
            $table->string('discipline', 100)->nullable()->after('revision');
            $table->string('issuer')->nullable()->after('discipline');
            $table->date('received_on')->nullable()->after('document_date');

            $table->index(['tenant_id', 'document_number'], 'documents_tenant_doc_number_index');
            $table->index(['tenant_id', 'discipline'], 'documents_tenant_discipline_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_tenant_doc_number_index');
            $table->dropIndex('documents_tenant_discipline_index');
            $table->dropColumn([
                'document_number',
                'revision',
                'discipline',
                'issuer',
                'received_on',
            ]);
        });
    }
};
