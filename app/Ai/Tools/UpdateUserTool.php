<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateUserTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_user';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing user in the workspace (name, email, section assignments, or ban status) for human review. This tool never applies changes directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $userId = (int) ($request['user_id'] ?? 0);
        $user = $this->findWorkspaceUser($userId);

        if (! $user) {
            return "Error: user with ID {$userId} not found in this workspace. Use the students tool to inspect valid users.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['name']) && trim((string) $request['name']) !== '') {
            $newName = trim((string) $request['name']);
            if ($newName !== $user->name) {
                $changes['name'] = $newName;
                $preview[] = ['field' => 'Name', 'before' => $user->name, 'after' => $newName];
            }
        }

        if (isset($request['email']) && trim((string) $request['email']) !== '') {
            $newEmail = strtolower(trim((string) $request['email']));
            if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                return 'Error: a valid email address is required.';
            }
            if ($newEmail !== strtolower($user->email)) {
                if (User::query()->where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
                    return "Error: email \"{$newEmail}\" is already registered by another account.";
                }
                $changes['email'] = $newEmail;
                $preview[] = ['field' => 'Email', 'before' => $user->email, 'after' => $newEmail];
            }
        }

        if (isset($request['password']) && trim((string) $request['password']) !== '') {
            $newPassword = (string) $request['password'];
            if (strlen($newPassword) < 6) {
                return 'Error: password must be at least 6 characters long.';
            }
            $changes['password'] = $newPassword;
            $preview[] = ['field' => 'Password', 'before' => 'Current password', 'after' => 'Updated (••••••••, '.strlen($newPassword).' characters)'];
        }

        if (isset($request['is_banned'])) {
            $isBanned = (bool) $request['is_banned'];
            if ($isBanned !== (bool) $user->is_banned) {
                $changes['is_banned'] = $isBanned;
                $changes['ban_reason'] = $isBanned ? trim((string) ($request['ban_reason'] ?? 'Banned by administrator')) : null;
                $preview[] = [
                    'field' => 'Ban Status',
                    'before' => $user->is_banned ? 'Banned ('.($user->ban_reason ?: 'No reason').')' : 'Active',
                    'after' => $isBanned ? 'Banned ('.($changes['ban_reason'] ?: 'No reason').')' : 'Active',
                ];
            }
        }

        $newSectionIds = null;
        if (isset($request['section_ids'])) {
            $raw = is_array($request['section_ids'])
                ? $request['section_ids']
                : explode(',', (string) $request['section_ids']);

            $newSectionIds = collect($raw)
                ->map(fn ($id) => (int) trim((string) $id))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $currentSectionIds = $user->sections->pluck('id')->sort()->values()->all();
            $sortedNew = collect($newSectionIds)->sort()->values()->all();

            if ($currentSectionIds !== $sortedNew) {
                $newSections = Section::query()
                    ->withoutGlobalScope('workspace')
                    ->whereIn('id', $newSectionIds)
                    ->where('workspace_id', $this->workspaceId())
                    ->get();

                if ($newSections->count() !== count($newSectionIds)) {
                    return 'Error: one or more specified sections do not exist in this workspace.';
                }

                $beforeNames = $user->sections->pluck('name')->implode(', ') ?: 'None';
                $afterNames = $newSections->pluck('name')->implode(', ') ?: 'None';

                $changes['section_ids'] = $newSectionIds;
                $preview[] = ['field' => 'Sections', 'before' => $beforeNames, 'after' => $afterNames];
            }
        }

        if ($changes === []) {
            return "No changes were specified for user {$user->name} (#{$user->id}).";
        }

        $payload = [
            'user_id' => $user->id,
            'changes' => $changes,
            'expected_updated_at' => $user->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_user',
            'Update user account',
            "Update account details for {$user->name} (#{$user->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The ID of the user to update.')->required(),
            'name' => $schema->string()->description('Optional updated full name.'),
            'email' => $schema->string()->description('Optional updated email address.'),
            'password' => $schema->string()->description('Optional new password (minimum 6 characters).'),
            'section_ids' => $schema->string()->description('Optional comma-separated list of section IDs to assign the user to.'),
            'is_banned' => $schema->boolean()->description('Optional boolean flag to ban or unban the user.'),
            'ban_reason' => $schema->string()->description('Optional reason when banning a user.'),
        ];
    }
}
