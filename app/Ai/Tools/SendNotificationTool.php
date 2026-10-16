<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SendNotificationTool extends PendingWriteTool implements Tool
{
    private const MAX_RECIPIENTS = 500;

    public function name(): string
    {
        return 'send_notification';
    }

    public function description(): Stringable|string
    {
        return 'Prepare an in-app notification to a targeted group of students (a section, specific students, or all students) with a title and message. This tool never sends directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $workspaceId = $this->workspaceId();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $title = trim(strip_tags((string) ($request['title'] ?? '')));
        if ($title === '') {
            return 'Error: title is required.';
        }
        if (mb_strlen($title) > 120) {
            return 'Error: title is too long (maximum 120 characters).';
        }

        $message = trim(strip_tags((string) ($request['message'] ?? '')));
        if ($message === '') {
            return 'Error: message is required.';
        }
        if (mb_strlen($message) > 500) {
            return 'Error: message is too long (maximum 500 characters).';
        }

        $href = trim((string) ($request['href'] ?? '')) ?: '/dashboard';
        if (! str_starts_with($href, '/')) {
            return 'Error: href must be a relative path starting with "/", e.g. "/dashboard".';
        }

        $sectionId = (int) ($request['section_id'] ?? 0);
        $sectionName = trim((string) ($request['section_name'] ?? ''));
        $allStudents = filter_var($request['all_students'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $recipients = collect();
        $targetLabel = '';

        if ($sectionId > 0 || $sectionName !== '') {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('sections.workspace_id', $workspaceId)
                ->when($sectionId > 0, fn ($query) => $query->whereKey($sectionId))
                ->when($sectionId <= 0, fn ($query) => $query->where('name', 'like', "%{$sectionName}%"))
                ->orderBy('name')
                ->first();

            if (! $section) {
                return 'Error: section not found in this workspace. Use sections_admin for valid section IDs.';
            }

            $recipients = $section->users()->where('users.is_admin', false)->get();
            $targetLabel = "Section: {$section->name} (#{$section->id})";
        } elseif ($allStudents) {
            $recipients = User::query()
                ->where('is_admin', false)
                ->where(function ($query) use ($workspaceId) {
                    $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                        ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                        ->orWhere('current_workspace_id', $workspaceId);
                })
                ->get();
            $targetLabel = 'All students in this workspace';
        } else {
            $ids = $this->parseList($request['student_ids'] ?? null)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            foreach ($this->parseList($request['student_names'] ?? null) as $name) {
                $matches = User::query()
                    ->where('is_admin', false)
                    ->where('name', 'like', "%{$name}%")
                    ->where(function ($query) use ($workspaceId) {
                        $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                            ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                            ->orWhere('current_workspace_id', $workspaceId);
                    })
                    ->limit(5)
                    ->get();

                if ($matches->isEmpty()) {
                    return "Error: student \"{$name}\" not found in this workspace. Use the students tool to find valid students.";
                }

                if ($matches->count() > 1) {
                    $options = $matches->map(fn (User $user) => "{$user->name} (#{$user->id})")->implode(', ');

                    return "Error: multiple students match \"{$name}\": {$options}. Use student IDs instead.";
                }

                $ids->push($matches->first()->id);
            }

            if ($ids->isEmpty()) {
                return 'Error: specify section_id or section_name, student_ids or student_names, or all_students.';
            }

            $recipients = User::query()
                ->whereIn('id', $ids->unique()->values())
                ->where('is_admin', false)
                ->get();

            if ($recipients->count() !== $ids->unique()->count()) {
                return 'Error: one or more specified students do not exist in this workspace or are administrators.';
            }

            $targetLabel = 'Specific students ('.$recipients->count().')';
        }

        if ($recipients->isEmpty()) {
            return 'Error: no students matched the requested recipients.';
        }

        if ($recipients->count() > self::MAX_RECIPIENTS) {
            return 'Error: too many recipients ('.self::MAX_RECIPIENTS.' maximum). Narrow the audience (e.g. a single section).';
        }

        $sample = $recipients->take(3)->pluck('name')->implode(', ');
        $recipientsLabel = $recipients->count().' student(s)'.($sample !== '' ? " — e.g. {$sample}" : '');

        $recipientIds = $recipients->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return $this->stageAction(
            'send_notification',
            'Send student notification',
            "Send notification \"{$title}\" to {$recipients->count()} student(s) in this workspace.",
            [
                'student_ids' => $recipientIds,
                'title' => $title,
                'message' => $message,
                'href' => $href,
            ],
            [
                ['field' => 'Audience', 'before' => null, 'after' => $targetLabel],
                ['field' => 'Recipients', 'before' => null, 'after' => $recipientsLabel],
                ['field' => 'Title', 'before' => null, 'after' => $title],
                ['field' => 'Message', 'before' => null, 'after' => $message],
                ['field' => 'Link', 'before' => null, 'after' => $href],
            ],
        );
    }

    /**
     * @return Collection<int, mixed>
     */
    private function parseList(mixed $value): Collection
    {
        if ($value === null || $value === '') {
            return collect();
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)->map(fn ($item) => trim((string) $item))->filter()->values();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Notification title (maximum 120 characters).')->required(),
            'message' => $schema->string()->description('Notification message body (maximum 500 characters).')->required(),
            'href' => $schema->string()->description('Optional relative link the notification opens, e.g. "/dashboard". Defaults to "/dashboard".'),
            'section_id' => $schema->integer()->description('Send to every student in this section.'),
            'section_name' => $schema->string()->description('Section name (or partial name), alternative to section_id.'),
            'student_ids' => $schema->string()->description('Comma-separated student IDs to notify.'),
            'student_names' => $schema->string()->description('Comma-separated student names to notify (each must match exactly one workspace student).'),
            'all_students' => $schema->boolean()->description('Set true to notify every student in the workspace.'),
        ];
    }
}
