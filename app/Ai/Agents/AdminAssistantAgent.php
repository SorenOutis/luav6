<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\RequiresReplyGuard;
use App\Ai\Skills\AdminAssistantSkill;
use App\Ai\Support\AdminAssistantIntent;
use App\Ai\Support\ToolRegistry;
use App\Support\PlatformKnowledge;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Echo for teachers/admins — workspace analytics plus guarded management
 * actions. Write tools only stage approval requests; execution is exclusively
 * available through a nonce-protected human UI.
 *
 * MaxSteps is 12 so multi-step intents (section + exam + questions) can finish
 * when write tools are scoped to matching groups rather than the full catalog.
 */
#[MaxSteps(12)]
class AdminAssistantAgent implements Agent, Conversational, HasTools, RequiresReplyGuard
{
    use Promptable;

    protected array $history = [];

    protected ?string $userContext = null;

    protected ?int $chatSessionId = null;

    /** @var list<string>|null */
    protected ?array $intentGroups = null;

    public function setHistory(array $history): self
    {
        $this->history = $history;

        return $this;
    }

    public function setUserContext(?string $userContext): self
    {
        $this->userContext = $userContext;

        return $this;
    }

    public function setChatSessionId(?int $chatSessionId): self
    {
        $this->chatSessionId = $chatSessionId;

        return $this;
    }

    /**
     * @param  list<string>|null  $groups
     */
    public function setIntentGroups(?array $groups): self
    {
        $this->intentGroups = $groups;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $instructions = "You are 'Echo', the AI assistant for teachers/admins on the LSI learning platform.

ROLE BOUNDARIES:
- Super Administrators (Is Super Admin: YES): may use platform-wide tools when those tools exist (manage_maintenance, daily_report, list_workspaces, platform_counts, failed_jobs). Workspace tools still default to the active workspace.
- Workspace Administrators (Is Super Admin: NO): only the active workspace. Decline platform maintenance, global settings, and other-workspace data.
- Platform-wide answers only when a platform-wide tool was used. Never claim all-workspace data from a workspace-scoped tool.
- The active workspace is already resolved. Never ask which workspace to use for ordinary writes.

WRITE-APPROVAL RULES (strict):
1. Write tools NEVER execute a write. They only stage an immutable, expiring approval card with an exact diff and a server nonce you never receive.
2. NEVER claim an approval card exists, is ready, or was staged unless a write tool actually ran this turn and returned PENDING HUMAN APPROVAL with an action_id.
3. After staging, tell the admin to review the exact diff and click Approve or Reject in the UI. Typed 'confirm' is never approval. Do not retry the same tool after pending.
4. Never invent student, section, course, exam, submission, or grade IDs — resolve them with read tools first.
5. A message that is only a person's name is a student search: call `students` immediately with only that name as `search`. If several match, list options; never pick silently.

RESPONSE CONTRACT:
1. Outcome — one sentence: found, staged, declined, or blocked.
2. Facts — short list from tool fields only (include action_id and expires_at when a write was staged).
3. Next step — approve the card, choose among options, or the one follow-up tool you can run.
No greeting, no restating the question, no policy lecture. Past tense only for completed reads; future or pending for writes.

READ TOOLS (always available): workspace_overview, students, user_activity, at_risk_students, section_performance, leaderboard, ai_action_log, support_tickets, exams_admin, courses_admin, sections_admin, assignments_admin, announcements_admin, materials_admin, research_topic, activity_tasks_admin, submissions_to_grade, grades_admin.
PLATFORM TOOLS (Super Admin): manage_maintenance, daily_report, list_workspaces, platform_counts, failed_jobs.
WRITE TOOLS are mounted by intent for this turn; use only tools present in the tool list.
Export via export_report stages only — never invent a filename or download URL until approval returns one.";

        if ($this->userContext) {
            $instructions .= "\n\n{$this->userContext}";
        }

        $instructions .= "\n\n".PlatformKnowledge::adminNotes();
        $instructions .= "\n\nRUNTIME SKILL:\n".AdminAssistantSkill::instructions();

        return $instructions;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        $groups = $this->intentGroups ?? [AdminAssistantIntent::ALL];
        $keys = [];

        foreach ($groups as $group) {
            $keys = array_merge($keys, ToolRegistry::keysForIntent($group));
        }

        // Echo has always mounted these two approval-gated platform/export
        // tools regardless of intent; keep that behaviour for compatibility.
        $keys = array_merge($keys, ['manage_maintenance', 'export_report']);

        return ToolRegistry::makeMany(
            array_values(array_unique($keys)),
            $this->chatSessionId,
        );
    }
}
