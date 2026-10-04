<?php

namespace App\Ai\Skills;

final class AdminAssistantSkill
{
    public static function instructions(): string
    {
        $path = base_path('skills/ai-admin-panel/SKILL.md');

        if (! is_file($path)) {
            return 'The AI admin-panel skill is unavailable. Follow the built-in workspace, authorization, approval, privacy, and export-safety rules.';
        }

        $instructions = file_get_contents($path);

        return is_string($instructions) && trim($instructions) !== ''
            ? trim($instructions)
            : 'The AI admin-panel skill is empty. Follow the built-in workspace, authorization, approval, privacy, and export-safety rules.';
    }
}
