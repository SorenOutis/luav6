<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tenantTables = [
        'sections',
        'exams',
        'assignments',
        'courses',
        'course_modules',
        'lessons',
        'seasons',
        'badges',
        'rewards',
        'announcements',
        'ai_question_drafts',
        'anonymous_messages',
        'settings',
        'grades',
        'ai_usage_logs',
    ];

    /** Tables whose legacy admin_id identifies their initial tenant owner. */
    private array $adminOwnedTables = [
        'sections',
        'exams',
        'assignments',
        'courses',
        'course_modules',
        'lessons',
        'seasons',
        'badges',
        'rewards',
        'announcements',
        'ai_question_drafts',
        'anonymous_messages',
        'settings',
    ];

    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('current_workspace_id')
                ->nullable()
                ->index()
                ->after('public_id')
                ->constrained('workspaces')
                ->nullOnDelete();
        });

        foreach ($this->tenantTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->index()
                    ->constrained('workspaces')
                    ->cascadeOnDelete();
            });
        }

        $workspaceByAdmin = $this->createInitialAdminWorkspaces();
        $this->backfillAdminOwnedRecords($workspaceByAdmin);
        $this->makeLegacyCreatorsNullableOnDelete();
        $this->backfillRelationalRecords();
        $this->backfillStudentMemberships();

        Schema::table('settings', function (Blueprint $table): void {
            $table->unique(['workspace_id', 'key'], 'settings_workspace_key_unique');
        });
    }

    public function down(): void
    {
        $this->restoreLegacyCreatorCascade();

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table): void {
                $table->dropUnique('settings_workspace_key_unique');
            });
        }

        foreach (array_reverse($this->tenantTables) as $tableName) {
            if (! Schema::hasColumn($tableName, 'workspace_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('workspace_id');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_workspace_id');
        });

        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }

    /** @return array<int, int> admin user id => workspace id */
    private function createInitialAdminWorkspaces(): array
    {
        $mapping = [];
        $usedSlugs = [];

        foreach (DB::table('users')->where('is_admin', true)->orderBy('id')->get() as $admin) {
            $base = Str::slug((string) $admin->name) ?: 'workspace';
            $slug = $base.'-'.$admin->id;
            while (isset($usedSlugs[$slug])) {
                $slug .= '-x';
            }
            $usedSlugs[$slug] = true;

            $workspaceId = DB::table('workspaces')->insertGetId([
                'public_id' => (string) Str::uuid7(),
                'name' => $admin->name.' Workspace',
                'slug' => $slug,
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('workspace_user')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $admin->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $admin->id)->update([
                'current_workspace_id' => $workspaceId,
            ]);

            $mapping[(int) $admin->id] = (int) $workspaceId;
        }

        return $mapping;
    }

    private function makeLegacyCreatorsNullableOnDelete(): void
    {
        foreach ($this->adminOwnedTables as $tableName) {
            if (! Schema::hasColumn($tableName, 'admin_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['admin_id']);
                $table->foreign('admin_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    private function restoreLegacyCreatorCascade(): void
    {
        foreach ($this->adminOwnedTables as $tableName) {
            if (! Schema::hasColumn($tableName, 'admin_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['admin_id']);
                $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    /** @param array<int, int> $workspaceByAdmin */
    private function backfillAdminOwnedRecords(array $workspaceByAdmin): void
    {
        foreach ($this->adminOwnedTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'admin_id')) {
                continue;
            }

            foreach ($workspaceByAdmin as $adminId => $workspaceId) {
                DB::table($tableName)
                    ->where('admin_id', $adminId)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $workspaceId]);
            }
        }
    }

    private function backfillRelationalRecords(): void
    {
        $relations = [
            ['assignments', 'course_id', 'courses'],
            ['course_modules', 'course_id', 'courses'],
            ['lessons', 'course_module_id', 'course_modules'],
            ['exams', 'section_id', 'sections'],
            ['announcements', 'section_id', 'sections'],
        ];

        foreach ($relations as [$table, $foreignKey, $parent]) {
            DB::statement(
                "UPDATE {$table} SET workspace_id = (SELECT {$parent}.workspace_id FROM {$parent} WHERE {$parent}.id = {$table}.{$foreignKey}) WHERE workspace_id IS NULL AND {$foreignKey} IS NOT NULL"
            );
        }

        if (Schema::hasTable('grades')) {
            DB::statement(
                'UPDATE grades SET workspace_id = (SELECT sections.workspace_id FROM sections WHERE sections.id = grades.section_id) WHERE workspace_id IS NULL'
            );
        }

        if (Schema::hasTable('anonymous_messages')) {
            $messages = DB::table('anonymous_messages')
                ->whereNull('workspace_id')
                ->whereNotNull('user_id')
                ->select(['id', 'user_id'])
                ->get();

            foreach ($messages as $message) {
                $workspaceId = DB::table('section_user')
                    ->join('sections', 'sections.id', '=', 'section_user.section_id')
                    ->where('section_user.user_id', $message->user_id)
                    ->whereNotNull('sections.workspace_id')
                    ->orderBy('sections.id')
                    ->value('sections.workspace_id');

                if ($workspaceId) {
                    DB::table('anonymous_messages')
                        ->where('id', $message->id)
                        ->update(['workspace_id' => $workspaceId]);
                }
            }
        }
    }

    private function backfillStudentMemberships(): void
    {
        $sectionMemberships = DB::table('section_user')
            ->join('sections', 'sections.id', '=', 'section_user.section_id')
            ->whereNotNull('sections.workspace_id')
            ->selectRaw('sections.workspace_id, section_user.user_id')
            ->distinct()
            ->get();
        $courseMemberships = DB::table('course_user')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->whereNotNull('courses.workspace_id')
            ->selectRaw('courses.workspace_id, course_user.user_id')
            ->distinct()
            ->get();
        $adminIds = DB::table('users')->where('is_admin', true)->pluck('id');
        $memberships = $sectionMemberships
            ->concat($courseMemberships)
            ->reject(fn ($membership): bool => $adminIds->contains($membership->user_id))
            ->unique(fn ($membership): string => $membership->workspace_id.':'.$membership->user_id);

        foreach ($memberships as $membership) {
            DB::table('workspace_user')->insertOrIgnore([
                'workspace_id' => $membership->workspace_id,
                'user_id' => $membership->user_id,
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')
                ->where('id', $membership->user_id)
                ->whereNull('current_workspace_id')
                ->update(['current_workspace_id' => $membership->workspace_id]);
        }
    }
};
