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

        $userId = (int) ($request['user_id'] ?? 0);
        $user = $this->findWorkspaceUser($userId);

        if (! $user) {
            return "Error: user with ID {$userId} not found in this workspace. Use the students tool to find valid student IDs.";
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

        return $this->stageAction(
            'reset_user_password',
            'Reset user password',
            "Set a new password for {$user->name} ({$user->email}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The user ID whose password should be reset.')->required(),
            'new_password' => $schema->string()->description('The new password (minimum 6 characters).')->required(),
        ];
    }
}
