<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_question_drafts', function (Blueprint $table): void {
            $table->foreignId('target_exam_id')->nullable()->after('user_id')->constrained('exams')->nullOnDelete();
            $table->text('attachment_instructions')->nullable()->after('difficulty');
            $table->string('review_status', 32)->default('not_ready')->after('status');
            $table->unsignedInteger('review_version')->default(0)->after('review_status');
            $table->timestamp('submitted_for_review_at')->nullable()->after('generated_at');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_for_review_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');

            $table->index(['workspace_id', 'review_status', 'created_at'], 'ai_question_drafts_review_queue_idx');
        });

        DB::table('ai_question_drafts')
            ->where('status', 'ready')
            ->update([
                'review_status' => 'awaiting_review',
                'review_version' => 1,
                'submitted_for_review_at' => DB::raw('COALESCE(generated_at, updated_at)'),
            ]);

        Schema::create('ai_essay_feedback_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_number');
            $table->text('question_text');
            $table->longText('answer_text');
            $table->decimal('max_points', 8, 2)->unsigned()->default(1);
            $table->decimal('proposed_score', 8, 2)->unsigned();
            $table->text('proposed_feedback');
            $table->string('provider', 80)->nullable();
            $table->string('model', 191)->nullable();
            $table->char('source_hash', 64);
            $table->string('review_status', 32)->default('awaiting_review');
            $table->unsignedInteger('generation_version')->default(1);
            $table->timestamp('generated_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['exam_submission_id', 'question_number'], 'ai_essay_feedback_submission_question_unique');
            $table->index(['workspace_id', 'review_status', 'created_at'], 'ai_essay_feedback_review_queue_idx');
        });

        Schema::create('ai_review_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('reviewable');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 48);
            $table->unsignedInteger('version')->nullable();
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'event', 'created_at']);
            $table->index(['reviewable_type', 'reviewable_id', 'created_at'], 'ai_review_events_reviewable_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_review_events');
        Schema::dropIfExists('ai_essay_feedback_drafts');

        Schema::table('ai_question_drafts', function (Blueprint $table): void {
            $table->dropIndex('ai_question_drafts_review_queue_idx');
            $table->dropConstrainedForeignId('target_exam_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'attachment_instructions',
                'review_status',
                'review_version',
                'submitted_for_review_at',
                'reviewed_at',
                'rejection_reason',
            ]);
        });
    }
};
