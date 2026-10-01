<?php

namespace App\Ai\Tools;

use App\Support\PlatformMaintenance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageMaintenanceTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'manage_maintenance';
    }

    public function description(): Stringable|string
    {
        return 'Inspect or modify platform-wide maintenance mode (enable, disable, or update maintenance message/title). EXCLUSIVELY available to Super Administrators. Never available to standard workspace admins.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();

        if (! $user?->is_admin) {
            return 'Only administrators can use this tool.';
        }

        $action = trim((string) ($request['action'] ?? 'status'));

        // If inspecting current status, return the state immediately
        if ($action === 'status') {
            $state = PlatformMaintenance::formState();
            $statusText = $state['maintenance_enabled']
                ? 'ENABLED (The site is currently in maintenance mode; students and workspace admins see the maintenance page)'
                : 'DISABLED (The site is live and fully accessible)';

            return "Current Platform Maintenance Status:\n"
                ."- Status: {$statusText}\n"
                ."- Title: \"{$state['maintenance_title']}\"\n"
                ."- Message: \"{$state['maintenance_message']}\"\n"
                ."- Mascot Image: {$state['maintenance_image']}";
        }

        // For modifying maintenance mode, strictly require Super Admin privileges
        if (! $user->isSuperAdmin()) {
            return 'PERMISSION DENIED: Modifying platform maintenance mode requires Super Administrator privileges. Standard workspace administrators cannot change platform-wide maintenance.';
        }

        $currentEnabled = PlatformMaintenance::isEnabled();

        $newEnabled = match ($action) {
            'enable' => true,
            'disable' => false,
            'update' => isset($request['enabled']) ? (bool) $request['enabled'] : $currentEnabled,
            default => $currentEnabled,
        };

        $newTitle = filled($request['title'] ?? null)
            ? trim((string) $request['title'])
            : PlatformMaintenance::title();

        $newMessage = filled($request['message'] ?? null)
            ? trim((string) $request['message'])
            : PlatformMaintenance::message();

        $newImage = filled($request['image'] ?? null)
            ? trim((string) $request['image'])
            : PlatformMaintenance::image();

        $payload = [
            'maintenance_enabled' => $newEnabled,
            'maintenance_title' => $newTitle,
            'maintenance_message' => $newMessage,
            'maintenance_image' => $newImage,
        ];

        $changes = [
            [
                'field' => 'Maintenance Mode',
                'before' => $currentEnabled ? 'Enabled (Site Closed)' : 'Disabled (Live)',
                'after' => $newEnabled ? 'Enabled (Site Closed)' : 'Disabled (Live)',
            ],
            [
                'field' => 'Scope',
                'before' => null,
                'after' => 'Platform-wide (All Students & Workspace Admins)',
            ],
        ];

        if ($newTitle !== PlatformMaintenance::title()) {
            $changes[] = [
                'field' => 'Title',
                'before' => PlatformMaintenance::title(),
                'after' => $newTitle,
            ];
        }

        if ($newMessage !== PlatformMaintenance::message()) {
            $changes[] = [
                'field' => 'Message',
                'before' => PlatformMaintenance::message(),
                'after' => $newMessage,
            ];
        }

        $titleAction = $newEnabled ? 'Enable Platform Maintenance Mode' : 'Disable Platform Maintenance Mode';
        $summaryAction = $newEnabled
            ? 'Put the site into maintenance mode. All students and workspace admins will see the maintenance screen; only Super Admins can bypass it.'
            : 'Turn off maintenance mode. The application will immediately become live and accessible to all students and teachers.';

        return $this->stageAction(
            'manage_maintenance',
            $titleAction,
            $summaryAction,
            $payload,
            $changes,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('The maintenance action to perform: "status" (check current state), "enable" (turn on maintenance mode), "disable" (turn off maintenance mode/bring site live), or "update" (update title/message/image).')
                ->enum(['status', 'enable', 'disable', 'update'])
                ->required(),
            'title' => $schema->string()->description('Optional custom headline for the maintenance screen (e.g., "We\'ll be right back").'),
            'message' => $schema->string()->description('Optional custom message displayed on the maintenance screen.'),
            'image' => $schema->string()->description('Optional mascot image variant: "maintenance", "calendar", "chat", "welcome-hero", or "library".'),
            'enabled' => $schema->boolean()->description('Used with action "update" to explicitly set enabled state.'),
        ];
    }
}
