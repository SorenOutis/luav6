<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** PostgreSQL concurrent indexes cannot be created inside a transaction. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'public_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('public_id')->nullable()->after('id');
                $table->string('profile_visibility', 20)->default('section');
                $table->boolean('profile_show_activity')->default(false);
                $table->boolean('profile_show_sections')->default(true);
                $table->boolean('profile_show_social')->default(true);
                $table->boolean('profile_show_achievements')->default(true);
            });
        }

        DB::table('users')
            ->whereNull('public_id')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['public_id' => (string) Str::uuid7()]);
                }
            });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS users_public_id_unique ON users (public_id)');
        } elseif (! $this->indexExists('users_public_id_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('public_id'));
        }

        $this->rewriteExistingProfileNotificationLinks();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS users_public_id_unique');
        } else {
            Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_public_id_unique'));
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'public_id',
                'profile_visibility',
                'profile_show_activity',
                'profile_show_sections',
                'profile_show_social',
                'profile_show_achievements',
            ]);
        });
    }

    private function indexExists(string $name): bool
    {
        return collect(Schema::getIndexes('users'))
            ->contains(fn (array $index): bool => strcasecmp((string) ($index['name'] ?? ''), $name) === 0);
    }

    /** Keep previously delivered social/badge notifications from becoming dead links. */
    private function rewriteExistingProfileNotificationLinks(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->select(['id', 'data'])
            ->orderBy('created_at')
            ->chunk(500, function ($notifications): void {
                $parsed = collect($notifications)->mapWithKeys(function ($notification): array {
                    $data = json_decode((string) $notification->data, true);

                    return [$notification->id => is_array($data) ? $data : []];
                });

                $userIds = $parsed
                    ->pluck('href')
                    ->filter(fn ($href): bool => is_string($href) && preg_match('#^/u/([0-9]+)$#', $href) === 1)
                    ->map(function (string $href): int {
                        preg_match('#^/u/([0-9]+)$#', $href, $matches);

                        return (int) $matches[1];
                    })
                    ->unique()
                    ->values();

                $publicIds = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('public_id', 'id');

                foreach ($parsed as $notificationId => $data) {
                    $href = $data['href'] ?? null;
                    if (! is_string($href) || preg_match('#^/u/([0-9]+)$#', $href, $matches) !== 1) {
                        continue;
                    }

                    $publicId = $publicIds[(int) $matches[1]] ?? null;
                    if (! $publicId) {
                        continue;
                    }

                    $data['href'] = "/u/{$publicId}";
                    DB::table('notifications')
                        ->where('id', $notificationId)
                        ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                }
            });
    }
};
