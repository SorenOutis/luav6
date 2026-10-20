<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FULLTEXT is MySQL-only. SQLite (tests) and PostgreSQL keep the
     * LIKE-based fallback used in ChatHistoryController::search().
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists('chat_messages_content_fulltext')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->fullText('content', 'chat_messages_content_fulltext');
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->indexExists('chat_messages_content_fulltext')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropFullText('chat_messages_content_fulltext');
        });
    }

    private function indexExists(string $name): bool
    {
        try {
            return collect(Schema::getIndexes('chat_messages'))
                ->contains(fn (array $index): bool => strcasecmp((string) ($index['name'] ?? ''), $name) === 0);
        } catch (Throwable) {
            return false;
        }
    }
};
