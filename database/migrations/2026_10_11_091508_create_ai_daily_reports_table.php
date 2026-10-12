<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_daily_reports', function (Blueprint $table): void {
            $table->id();
            $table->date('report_date')->unique();
            $table->string('timezone', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('workspace_ids');
            $table->decimal('max_cost_usd', 10, 6);
            $table->json('snapshot')->nullable();
            $table->json('analysis')->nullable();
            $table->string('provider', 80);
            $table->string('model', 191)->nullable();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('generation_queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivery_queued_at')->nullable();
            $table->timestamp('delivery_started_at')->nullable();
            $table->timestamp('email_sent_at')->nullable()->index();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->string('delivery_error_code', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_daily_reports');
    }
};
