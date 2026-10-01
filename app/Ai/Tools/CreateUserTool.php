<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateUserTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'create_user';
    }

    public function description(): Stringable|string
    {
        return 'Prepare a new user account (student or administrator) with name, email, password, and optional class section assignments for human review. This tool never creates the account directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            return 'Error: user name is required.';
        }

        $email = trim(strtolower((string) ($request['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Error: a valid email address is required.';
        }

        if (User::query()->where('email', $email)->exists()) {
            return "Error: a user with email \"{$email}\" already exists.";
        }

        $password = (string) ($request['password'] ?? '');
        if (strlen($password) < 6) {
            return 'Error: password must be at least 6 characters long.';
        }

        $isAdmin = (bool) ($request['is_admin'] ?? false);

        $sectionIds = [];
        if (! empty($request['section_ids'])) {
            $raw = is_array($request['section_ids'])
                ? $request['section_ids']
                : explode(',', (string) $request['section_ids']);

            $sectionIds = collect($raw)
                ->map(fn ($id) => (int) trim((string) $id))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $sections = collect();
        if ($sectionIds !== []) {
            $sections = Section::query()
                ->withoutGlobalScope('workspace')
                ->whereIn('id', $sectionIds)
                ->where('workspace_id', $this->workspaceId())
                ->get();

            if ($sections->count() !== count($sectionIds)) {
                return 'Error: one or more specified sections do not exist in this workspace. Check workspace_overview for valid section IDs.';
            }
        }

        $roleLabel = $isAdmin ? 'Administrator' : 'Student';
        $sectionNames = $sections->isNotEmpty()
            ? $sections->pluck('name')->implode(', ')
            : 'None';

        $payload = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => $isAdmin,
            'section_ids' => $sectionIds,
        ];

        $preview = [
            ['field' => 'Name', 'before' => null, 'after' => $name],
            ['field' => 'Email', 'before' => null, 'after' => $email],
            ['field' => 'Role', 'before' => null, 'after' => $roleLabel],
            ['field' => 'Password', 'before' => null, 'after' => '•••••••• ('.strlen($password).' characters)'],
            ['field' => 'Sections', 'before' => null, 'after' => $sectionNames],
        ];

        return $this->stageAction(
            'create_user',
            'Create user account',
            "Create {$roleLabel} account for {$name} ({$email}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Full name of the user, e.g. "Juan Dela Cruz".')->required(),
            'email' => $schema->string()->description('Unique email address for logging in.')->required(),
            'password' => $schema->string()->description('Password for the user account (minimum 6 characters).')->required(),
            'is_admin' => $schema->boolean()->description('True if creating a co-administrator; false for a student. Defaults to false.'),
            'section_ids' => $schema->string()->description('Optional comma-separated list of section IDs to enroll the student into, e.g. "1, 2".'),
        ];
    }
}
