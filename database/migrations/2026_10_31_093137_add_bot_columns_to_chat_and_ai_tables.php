<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->foreignId('bot_id')->nullable()->after('user_id')->constrained('ai_bots')->nullOnDelete();
            $table->string('mode', 16)->default('solo')->after('bot_id');
            $table->json('participant_bot_ids')->nullable()->after('mode');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->foreignId('bot_id')->nullable()->after('session_id')->constrained('ai_bots')->nullOnDelete();
        });

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            $table->foreignId('bot_id')->nullable()->after('source')->constrained('ai_bots')->nullOnDelete();
        });

        Schema::table('pending_ai_actions', function (Blueprint $table): void {
            $table->foreignId('bot_id')->nullable()->after('chat_session_id')->constrained('ai_bots')->nullOnDelete();
            $table->foreignId('handoff_id')->nullable()->after('bot_id')->constrained('ai_bot_handoffs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pending_ai_actions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('handoff_id');
            $table->dropConstrainedForeignId('bot_id');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bot_id');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bot_id');
        });

        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bot_id');
            $table->dropColumn(['mode', 'participant_bot_ids']);
        });
    }
};
