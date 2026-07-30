<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- Core academic tables ---

        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        // --- Gamification tables ---

        Schema::table('seasons', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('badges', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        // --- Content tables ---

        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('ai_question_drafts', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('anonymous_messages', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        // --- Tower Defense tables ---

        Schema::table('td_maps', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('td_enemies', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('td_towers', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        Schema::table('td_difficulties', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');
        });

        // --- Settings (per-workspace) ---

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete()
                ->after('id');
            $table->index('admin_id');

            // Settings keys are now unique per admin (null = global)
            $table->dropUnique('settings_key_unique');
            $table->unique(['admin_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse in reverse order

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['admin_id', 'key']);
            $table->unique('key');
            $table->dropIndex(['admin_id']);
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
        });

        $tables = [
            'td_difficulties', 'td_towers', 'td_enemies', 'td_maps',
            'anonymous_messages', 'ai_question_drafts', 'announcements',
            'rewards', 'badges', 'seasons',
            'courses', 'assignments', 'exams', 'sections',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['admin_id']);
                $table->dropForeign(['admin_id']);
                $table->dropColumn('admin_id');
            });
        }
    }
};
