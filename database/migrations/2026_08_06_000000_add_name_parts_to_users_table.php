<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
        });

        // Backfill: split every existing full name into the three new columns.
        // Single-word names go to first_name; multi-word names map the first
        // word to first_name, the last word to last_name, and everything in
        // between to middle_name.
        $users = DB::table('users')->select('id', 'name')->orderBy('id')->get();

        foreach ($users as $user) {
            [$first, $middle, $last] = static::splitName((string) $user->name);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }

    /**
     * Split a full name into first / middle / last parts.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function splitName(string $name): array
    {
        $parts = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            static fn ($part): bool => $part !== ''
        ));

        if (count($parts) === 0) {
            return ['', '', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], '', ''];
        }

        $first = $parts[0];
        $last = $parts[count($parts) - 1];
        $middle = count($parts) > 2
            ? implode(' ', array_slice($parts, 1, -1))
            : '';

        return [$first, $middle, $last];
    }
};
