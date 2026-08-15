<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporting_calendars', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('timezone')->default('Africa/Kampala');
            $table->time('reporting_deadline')->default('18:00:00');
            $table->json('working_days');
            $table->unsignedTinyInteger('missing_escalation_days')->default(2);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active'], 'reporting_cal_tenant_active_idx');
            $table->index(['tenant_id', 'project_id', 'is_active'], 'reporting_cal_project_idx');
            $table->index(['tenant_id', 'site_id', 'is_active'], 'reporting_cal_site_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporting_calendars');
    }
};
