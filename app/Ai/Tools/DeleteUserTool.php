<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteUserTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_user';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion or removal of a user account from this workspace for human review. This tool never deletes accounts directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $userId = $request['user_id'] ?? null;
        $userName = isset($request['user_name']) ? trim((string) $request['user_name']) : (isset($request['name']) ? trim((string) $request['name']) : null);
        $userEmail = isset($request['user_email']) ? trim((string) $request['user_email']) : (isset($request['email']) ? trim((string) $request['email']) : null);

        $user = $this->findWorkspaceUser($userId, $userName, $userEmail);

        if (! $user) {
            $lookup = $userId ?? ($userName ?? ($userEmail ?? 'specified'));

            return "Error: user \"{$lookup}\" not found in this workspace. Use the students tool to inspect valid users.";
        }

        if ($user->id === auth()->id()) {
            return 'Error: you cannot delete your own administrator account.';
        }

        if ($user->isSuperAdmin()) {
            return 'Error: super-administrator accounts cannot be deleted.';
        }

        $sectionNames = $user->sections->pluck('name')->implode(', ') ?: 'None';
        $roleLabel = $user->is_admin ? 'Administrator' : 'Student';

        $payload = [
            'user_id' => $user->id,
            'expected_updated_at' => $user->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'User ID', 'before' => (string) $user->id, 'after' => 'Deleted / Removed'],
            ['field' => 'Name', 'before' => $user->name, 'after' => 'Deleted'],
            ['field' => 'Email', 'before' => $user->email, 'after' => 'Deleted'],
            ['field' => 'Role', 'before' => $roleLabel, 'after' => 'Removed'],
            ['field' => 'Sections', 'before' => $sectionNames, 'after' => 'Unenrolled'],
        ];

        $targetWorkspaceId = $this->resolveUserWorkspaceId($user);

        return $this->stageAction(
            'delete_user',
            'Delete user account',
            "Remove {$roleLabel} {$user->name} ({$user->email}) from this workspace.",
            $payload,
            $preview,
            $targetWorkspaceId,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The ID of the user to delete or remove from the workspace.'),
            'user_name' => $schema->string()->description('The name of the user to delete or remove (alternative to user_id).'),
            'user_email' => $schema->string()->description('The email of the user to delete or remove (alternative to user_id).'),
        ];
    }
}
