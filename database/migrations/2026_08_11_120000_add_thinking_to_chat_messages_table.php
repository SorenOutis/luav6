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
        if (Schema::hasColumn('chat_messages', 'thinking')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->longText('thinking')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('chat_messages', 'thinking')) {
            return;
        }

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('thinking');
        });
    }
};
