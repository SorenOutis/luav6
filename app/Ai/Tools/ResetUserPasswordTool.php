<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ResetUserPasswordTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'reset_user_password';
    }

    public function description(): Stringable|string
    {
        return 'Prepare a password reset for a student or administrator in the active workspace for human review. This tool never changes passwords directly; only the administrator approving in the UI can execute it.';
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

            return "Error: user \"{$lookup}\" not found in this workspace. Use the students tool to find valid student IDs.";
        }

        $password = (string) ($request['new_password'] ?? '');
        if (strlen($password) < 6) {
            return 'Error: new_password must be at least 6 characters long.';
        }

        if ($user->isSuperAdmin() && ! auth()->user()?->isSuperAdmin()) {
            return 'Error: only super-administrators can reset super-admin credentials.';
        }

        $payload = [
            'user_id' => $user->id,
            'password' => $password,
            'expected_updated_at' => $user->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'User', 'before' => $user->name, 'after' => $user->name],
            ['field' => 'Email', 'before' => $user->email, 'after' => $user->email],
            ['field' => 'Action', 'before' => 'Current password', 'after' => 'Reset password (••••••••, '.strlen($password).' characters)'],
        ];

        $targetWorkspaceId = $this->resolveUserWorkspaceId($user);

        return $this->stageAction(
            'reset_user_password',
            'Reset user password',
            "Set a new password for {$user->name} ({$user->email}).",
            $payload,
            $preview,
            $targetWorkspaceId,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The user ID whose password should be reset.'),
            'user_name' => $schema->string()->description('The name of the user whose password should be reset (alternative to user_id).'),
            'user_email' => $schema->string()->description('The email of the user whose password should be reset (alternative to user_id).'),
            'new_password' => $schema->string()->description('The new password (minimum 6 characters).')->required(),
        ];
    }
}
