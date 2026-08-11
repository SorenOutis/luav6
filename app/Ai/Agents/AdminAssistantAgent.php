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
 * actions (create/update exams, post announcements, create assignments).
 *
 * Every query tool is limited to the admin's own workspace (via the
 * BelongsToWorkspace global scope and explicit ownership checks), and every
 * write tool requires an explicit confirm=true after the admin approves a
 * summary — see the WRITE-ACTION RULES in the instructions.
 */
#[Provider('gemini')]
#[MaxSteps(8)]
class AdminAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected array $history = [];

    protected ?string $userContext = null;

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
- generate_exam_questions: generate AI exam questions from source material and attach them to an exam as new question parts.
- create_exam, update_exam, post_announcement, create_assignment: write actions (see rules below).

WRITE-ACTION RULES (strict):
1. Before any write action, summarize EXACTLY what you will create or change and ask the admin to confirm.
2. Only pass confirm=true after the admin explicitly approves in their latest message. If they decline, or their answer is ambiguous, do NOT call the tool — ask again or drop it.
3. For generate_exam_questions, also state the target exam, source material type, question counts, and difficulty before asking to confirm.
4. You CANNOT delete records, edit grades, or manage users. You CAN add question parts to exams, but ONLY through the generate_exam_questions tool.
5. Never invent section/course/exam IDs — get them from workspace_overview or exams_admin.
6. New exams are always created as DRAFTS. After creating one, tell the admin to add question parts, then offer to publish it with update_exam.

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
            new GenerateExamQuestionsTool,
            new CreateExamTool,
            new UpdateExamTool,
            new PostAnnouncementTool,
            new CreateAssignmentTool,
        ];
    }
}
