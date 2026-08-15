<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_calendar_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('reporting_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('exception_date');
            $table->string('type');
            $table->string('name');
            $table->text('reason')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reporting_calendar_id', 'exception_date'], 'reporting_cal_exception_date_unique');
            $table->index(['tenant_id', 'exception_date'], 'reporting_cal_exception_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_calendar_exceptions');
    }
};
