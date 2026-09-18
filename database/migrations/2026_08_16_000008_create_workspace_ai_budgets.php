<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_budget_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 12);
            $table->date('period_start');
            $table->unsignedBigInteger('used_input_tokens')->default(0);
            $table->unsignedBigInteger('used_output_tokens')->default(0);
            $table->unsignedBigInteger('reserved_tokens')->default(0);
            $table->unsignedBigInteger('used_cost_micros')->default(0);
            $table->unsignedBigInteger('reserved_cost_micros')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->timestamp('warning_emitted_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'period_type', 'period_start'], 'ai_budget_period_unique');
            $table->index(['period_type', 'period_start']);
        });

        Schema::create('ai_budget_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 32);
            $table->string('provider', 80);
            $table->string('model', 191)->nullable();
            $table->unsignedBigInteger('reserved_input_tokens');
            $table->unsignedBigInteger('reserved_output_tokens');
            $table->unsignedBigInteger('reserved_cost_micros');
            $table->unsignedBigInteger('actual_input_tokens')->nullable();
            $table->unsignedBigInteger('actual_output_tokens')->nullable();
            $table->unsignedBigInteger('actual_cost_micros')->nullable();
            $table->string('status', 24)->default('reserved');
            $table->timestamp('expires_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'expires_at']);
            $table->index(['workspace_id', 'feature', 'created_at']);
        });

        Schema::create('ai_budget_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_budget_reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 32);
            $table->string('provider', 80)->nullable();
            $table->string('model', 191)->nullable();
            $table->string('event', 32);
            $table->string('reason', 64)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'event', 'created_at']);
        });

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            $table->string('provider', 80)->change();
            $table->foreignId('ai_budget_reservation_id')
                ->nullable()
                ->after('workspace_id')
                ->constrained('ai_budget_reservations')
                ->nullOnDelete();
            $table->unsignedBigInteger('estimated_cost_micros')->default(0)->after('neurons');

            $table->index(['workspace_id', 'date', 'source'], 'ai_usage_workspace_date_source_idx');
            $table->index(['workspace_id', 'provider', 'date'], 'ai_usage_workspace_provider_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            $table->dropIndex('ai_usage_workspace_date_source_idx');
            $table->dropIndex('ai_usage_workspace_provider_date_idx');
            $table->dropConstrainedForeignId('ai_budget_reservation_id');
            $table->dropColumn('estimated_cost_micros');
            $table->string('provider', 32)->change();
        });

        Schema::dropIfExists('ai_budget_events');
        Schema::dropIfExists('ai_budget_reservations');
        Schema::dropIfExists('ai_budget_periods');
    }
};
