<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_user', function (Blueprint $table): void {
            $table->foreignUuid('site_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->boolean('can_submit_dsr')->default(false);
            $table->boolean('can_review_dsr')->default(false);
            $table->timestamps();

            $table->primary(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
    }
};
