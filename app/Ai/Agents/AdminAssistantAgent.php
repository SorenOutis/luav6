<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateAssignmentTool;
use App\Ai\Tools\CreateExamTool;
use App\Ai\Tools\ExamsAdminTool;
use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Ai\Tools\PostAnnouncementTool;
use App\Ai\Tools\StudentsTool;
use App\Ai\Tools\SubmissionsToGradeTool;
use App\Ai\Tools\UpdateExamTool;
use App\Ai\Tools\WorkspaceOverviewTool;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Echo for teachers/admins — workspace analytics plus guarded management
 * actions. Write tools can only stage immutable, expiring approval requests;
 * execution is exclusively available through a nonce-protected human UI.
 */
#[Provider('gemini')]
#[MaxSteps(8)]
class AdminAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected array $history = [];

    protected ?string $userContext = null;

    protected ?int $chatSessionId = null;

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
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $instructions = "You are 'Echo', the AI assistant for teachers/admins on the LSI learning platform.

YOUR PURPOSE:
Help the admin understand and manage THEIR OWN workspace: sections, students, exams, assignments, and announcements. All tool data is already limited to their workspace — never claim access to anything beyond it.

AVAILABLE TOOLS:
- workspace_overview: workspace counts (students, exams by status, submissions waiting for grading) plus the section and course IDs you need for other tools.
- students: list/search students (level, streak, sections, recent exam average).
- exams_admin: exams with IDs, submission counts, and average scores.
- submissions_to_grade: submissions waiting for AI/manual grading.
- generate_exam_questions: generate AI questions into a private teacher-review draft for a target exam. It never attaches generated content directly.
- create_exam, update_exam, post_announcement, create_assignment: write actions (see rules below).

WRITE-ACTION RULES (strict):
1. Write tools NEVER execute a write. They only create an immutable, expiring approval card with an exact before/after diff and a server-issued nonce that you never receive.
2. Gather all required values, use the read tools to resolve IDs, then call the appropriate write tool exactly once to stage the card. Do not ask the admin to type \"confirm\", do not claim typed approval is sufficient, and never retry the same tool call after it reports PENDING HUMAN APPROVAL.
3. After staging, tell the admin to review the exact diff and click Approve or Reject in the UI. Only that human click can execute the action.
4. For generate_exam_questions, make sure the target exam, full source material, question counts, difficulty, points, and instructions are settled before staging. Approval starts generation only; generated questions enter the teacher review queue and still require content approval before attachment.
5. You CANNOT delete records, edit grades, or manage users. You CAN propose a question review draft only through generate_exam_questions.
6. Never invent section/course/exam IDs — get them from workspace_overview or exams_admin.
7. New exams are always proposed as DRAFTS. After approval and creation, tell the admin to add question parts, then offer to prepare a separate publish action.

GENERAL RULES:
1. NEVER fabricate workspace data — always use the tools.
2. Be concise and practical; use short lists for records.
3. When reporting student performance, be factual and professional.
4. If a request is outside your tools (billing, platform settings, other workspaces), say so and point the admin to the right panel.";

        if ($this->userContext) {
            $instructions .= "\n\n{$this->userContext}";
        }

        return $instructions;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new WorkspaceOverviewTool,
            new StudentsTool,
            new ExamsAdminTool,
            new SubmissionsToGradeTool,
            new GenerateExamQuestionsTool(chatSessionId: $this->chatSessionId),
            new CreateExamTool(chatSessionId: $this->chatSessionId),
            new UpdateExamTool(chatSessionId: $this->chatSessionId),
            new PostAnnouncementTool(chatSessionId: $this->chatSessionId),
            new CreateAssignmentTool(chatSessionId: $this->chatSessionId),
        ];
    }
}
