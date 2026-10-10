<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AwardStudentXpTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'award_student_xp';
    }

    public function description(): Stringable|string
    {
        return 'Prepare awarding bonus XP and gamification points to a student with a custom reason for human review. This tool never awards XP directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $studentId = $request['student_id'] ?? null;
        $studentName = isset($request['student_name']) ? trim((string) $request['student_name']) : null;
        $studentEmail = isset($request['student_email']) ? trim((string) $request['student_email']) : null;

        $student = $this->findWorkspaceUser($studentId, $studentName, $studentEmail);

        if (! $student) {
            $identifier = $studentId ?? ($studentName ?? ($studentEmail ?? 'specified'));

            return "Error: student \"{$identifier}\" not found in this workspace. Use the students tool to inspect valid students.";
        }

        if (! isset($request['amount_xp']) || ! is_numeric($request['amount_xp'])) {
            return 'Error: amount_xp is required and must be a number.';
        }

        $amountXp = (float) $request['amount_xp'];
        $amountPoints = isset($request['amount_points']) && is_numeric($request['amount_points'])
            ? (float) $request['amount_points']
            : 0.0;

        $reason = trim((string) ($request['reason'] ?? ''));
        if ($reason === '') {
            return 'Error: a reason for awarding XP is required (e.g. "Excellent classroom participation").';
        }

        $targetWorkspaceId = $this->resolveUserWorkspaceId($student);

        $payload = [
            'student_id' => $student->id,
            'amount_xp' => $amountXp,
            'amount_points' => $amountPoints,
            'reason' => $reason,
        ];

        $preview = [
            ['field' => 'Student', 'before' => null, 'after' => "{$student->name} (#{$student->id})"],
            ['field' => 'Award XP', 'before' => null, 'after' => "+{$amountXp} XP"],
            ['field' => 'Award Points', 'before' => null, 'after' => "+{$amountPoints} Points"],
            ['field' => 'Reason', 'before' => null, 'after' => $reason],
        ];

        return $this->stageAction(
            'award_student_xp',
            'Award student XP & points',
            "Award +{$amountXp} XP and +{$amountPoints} Points to {$student->name} for \"{$reason}\".",
            $payload,
            $preview,
            $targetWorkspaceId,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->description('The ID of the student receiving XP.'),
            'student_name' => $schema->string()->description('The name of the student receiving XP (alternative if ID unknown).'),
            'student_email' => $schema->string()->description('The email of the student receiving XP (alternative if ID unknown).'),
            'amount_xp' => $schema->number()->description('Amount of XP to award (e.g. 50).')->required(),
            'amount_points' => $schema->number()->description('Amount of gamification points to award (e.g. 25).'),
            'reason' => $schema->string()->description('The reason or achievement note for the award.')->required(),
        ];
    }
}
