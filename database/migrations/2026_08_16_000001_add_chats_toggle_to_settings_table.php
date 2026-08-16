<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = [
            ['key' => 'chats_enabled', 'value' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'chats_maintenance_message', 'value' => 'Chats are currently under maintenance. Please try again later.', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($defaults as $row) {
            DB::table('settings')->updateOrInsert(
                ['key' => $row['key'], 'admin_id' => null],
                ['value' => $row['value'], 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['chats_enabled', 'chats_maintenance_message'])->delete();
    }
};
