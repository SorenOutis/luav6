<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Accounts created through Google/GitHub never choose a password, so the
     * column has to accept NULL. `User::hasPassword()` is what the rest of the
     * app checks before offering password-based flows.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Give any password-less (social-only) account a random unusable hash
        // first, otherwise the NOT NULL constraint cannot be restored.
        DB::table('users')->whereNull('password')->update([
            'password' => bcrypt(Str::random(64)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
