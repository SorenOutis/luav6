<?php

namespace App\Ai\Tools;

use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UserActivityTool implements Tool
{
    public function name(): string
    {
        return 'user_activity';
    }

    public function description(): Stringable|string
    {
        return 'Inspect account activity in the workspace: who logged in recently, who has gone inactive, and recent account signups. Use mode "recent_logins" for who logged in, "inactive" for students who have not logged in for N days, and "recent_signups" for newly created accounts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $mode = trim((string) ($request['mode'] ?? 'recent_logins'));
        if (! in_array($mode, ['recent_logins', 'inactive', 'recent_signups'], true)) {
            return 'Error: mode must be "recent_logins", "inactive", or "recent_signups".';
        }

        $days = min(max((int) ($request['days'] ?? 7), 1), 365);
        $limit = min(max((int) ($request['limit'] ?? 15), 1), 50);
        $includeAdmins = filter_var($request['include_admins'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $search = ! empty($request['search']) ? trim((string) $request['search']) : null;
        $sectionId = ! empty($request['section_id']) ? (int) $request['section_id'] : null;

        $membership = function ($query) use ($workspaceId) {
            $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                ->orWhere('current_workspace_id', $workspaceId);
        };

        $base = User::query()
            ->where($membership)
            ->when(! $includeAdmins, fn ($q) => $q->where('is_admin', false))
            ->when($sectionId, fn ($q) => $q->whereHas('sections', fn ($sub) => $sub
                ->where('sections.id', $sectionId)
                ->where('sections.workspace_id', $workspaceId)))
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")));

        $cutoff = now()->subDays($days);

        $query = (clone $base)->with('sections:id,name');

        match ($mode) {
            'recent_logins' => $query
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', $cutoff)
                ->orderByDesc('last_login_at'),
            'inactive' => $query
                ->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', $cutoff))
                ->orderBy('last_login_at'),
            'recent_signups' => $query
                ->where('created_at', '>=', $cutoff)
                ->orderByDesc('created_at'),
        };

        $users = $query->limit($limit)->get();

        $summary = [
            'workspace_members' => (clone $base)->count(),
            'logged_in_today' => (clone $base)
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', now()->startOfDay())
                ->count(),
            'logged_in_within_days' => (clone $base)
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '>=', $cutoff)
                ->count(),
            'inactive_over_days' => (clone $base)
                ->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', $cutoff))
                ->count(),
            'never_logged_in' => (clone $base)->whereNull('last_login_at')->count(),
            'new_signups_within_days' => (clone $base)->where('created_at', '>=', $cutoff)->count(),
        ];

        $rows = $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->isSuperAdmin() ? 'Super Admin' : ($user->is_admin ? 'Admin' : 'Student'),
            'last_login_at' => $user->last_login_at?->format('M d, Y g:i A'),
            'last_login_human' => $user->last_login_at?->diffForHumans() ?? 'Never',
            'days_since_login' => $user->last_login_at ? (int) $user->last_login_at->diffInDays(now()) : null,
            'joined_at' => $user->created_at?->format('M d, Y'),
            'sections' => $user->sections->pluck('name')->values()->all(),
        ])->values();

        return json_encode([
            'mode' => $mode,
            'window_days' => $days,
            'summary' => $summary,
            'users' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()->description('One of "recent_logins" (who logged in during the window), "inactive" (members who have not logged in for N days or never), or "recent_signups" (accounts created during the window). Defaults to "recent_logins".'),
            'days' => $schema->integer()->description('Lookback window in days for logins/signups, or inactivity threshold for "inactive". Defaults to 7.'),
            'section_id' => $schema->integer()->description('Optional section ID to restrict results to one class section.'),
            'search' => $schema->string()->description('Optional name or email search filter.'),
            'include_admins' => $schema->boolean()->description('Include admin/teacher accounts in the results. Defaults to false (students only).'),
            'limit' => $schema->integer()->description('Maximum number of users to return (1-50, default 15).'),
        ];
    }
}
