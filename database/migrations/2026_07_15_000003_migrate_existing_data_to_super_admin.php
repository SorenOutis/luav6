<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 1. Finds the first admin user (or creates one if none exist).
     * 2. Sets is_super_admin = true on all existing admin users.
     * 3. Assigns admin_id on all existing workspace data to the default super admin.
     */
    public function up(): void
    {
        // ── Step 1: Find or create the default super admin ──
        $superAdmin = DB::table('users')->where('is_admin', true)->orderBy('id')->first();

        if (! $superAdmin) {
            // No admin exists yet — create one
            $superAdminId = DB::table('users')->insertGetId([
                'name' => 'Default Super Admin',
                'email' => 'admin@luav6.test',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $superAdminId = $superAdmin->id;
        }

        // ── Step 2: Promote all existing admins to super admin ──
        DB::table('users')->where('is_admin', true)->update(['is_super_admin' => true]);

        // ── Step 3: Assign admin_id to all existing workspace data ──
        $tables = [
            'sections',
            'exams',
            'assignments',
            'courses',
            'seasons',
            'badges',
            'rewards',
            'announcements',
            'ai_question_drafts',
            'anonymous_messages',
            'td_maps',
            'td_enemies',
            'td_towers',
            'td_difficulties',
        ];

        foreach ($tables as $table) {
            $affected = DB::table($table)->whereNull('admin_id')->update(['admin_id' => $superAdminId]);
            if ($affected > 0) {
                echo "  ✓ Assigned {$affected} rows in '{$table}' to super admin (ID: {$superAdminId})\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is a data migration. Reversing is destructive and only
     * recommended in development. It sets admin_id back to null and
     * removes super admin status from admins that weren't the original admin.
     */
    public function down(): void
    {
        $tables = [
            'sections',
            'exams',
            'assignments',
            'courses',
            'seasons',
            'badges',
            'rewards',
            'announcements',
            'ai_question_drafts',
            'anonymous_messages',
            'td_maps',
            'td_enemies',
            'td_towers',
            'td_difficulties',
        ];

        foreach ($tables as $table) {
            DB::table($table)->update(['admin_id' => null]);
        }

        // Remove is_super_admin from all users
        DB::table('users')->update(['is_super_admin' => false]);
    }
};
