<?php

declare(strict_types=1);

namespace App\Ai\Support;

/**
 * Lightweight keyword intent detection for Echo admin chat.
 * Used to mount only the write-tool groups relevant to the current message
 * so the model is not drowning in 50+ tools with MaxSteps(8).
 */
final class AdminAssistantIntent
{
    public const READ = 'read';

    public const EXAMS = 'exams';

    public const GRADES = 'grades';

    public const USERS = 'users';

    public const SECTIONS = 'sections';

    public const COURSES = 'courses';

    public const ANNOUNCEMENTS = 'announcements';

    public const ASSIGNMENTS = 'assignments';

    public const MATERIALS = 'materials';

    public const ACTIVITY = 'activity';

    public const GAMIFICATION = 'gamification';

    public const SUPPORT = 'support';

    public const LESSONS = 'lessons';

    public const PLATFORM = 'platform';

    public const EXPORT = 'export';

    public const ALL = 'all';

    /**
     * @return list<string>
     */
    public static function detect(string $message): array
    {
        $m = mb_strtolower(trim($message));

        if ($m === '') {
            return [self::READ];
        }

        if (preg_match('/\b(manage everything|full access|all tools|do everything)\b/u', $m)) {
            return [self::ALL];
        }

        $groups = [];

        if (preg_match('/\b(exam|quiz|test|question|block student|publish draft)\b/u', $m)) {
            $groups[] = self::EXAMS;
        }
        if (preg_match('/\b(grade|score|submission|marking|rubric)\b/u', $m)) {
            $groups[] = self::GRADES;
        }
        if (preg_match('/\b(student|user|password|enroll|account|admin account)\b/u', $m)) {
            $groups[] = self::USERS;
        }
        if (preg_match('/\b(section|class|classroom)\b/u', $m)) {
            $groups[] = self::SECTIONS;
        }
        if (preg_match('/\b(course|curriculum)\b/u', $m)) {
            $groups[] = self::COURSES;
        }
        if (preg_match('/\b(announce|announcement|notice)\b/u', $m)) {
            $groups[] = self::ANNOUNCEMENTS;
        }
        if (preg_match('/\b(assignment|homework)\b/u', $m)) {
            $groups[] = self::ASSIGNMENTS;
        }
        if (preg_match('/\b(material|document|handout|resource file)\b/u', $m)) {
            $groups[] = self::MATERIALS;
        }
        if (preg_match('/\b(activity task|performance task|lab score|practicum)\b/u', $m)) {
            $groups[] = self::ACTIVITY;
        }
        if (preg_match('/\b(xp|badge|reward|season|leaderboard|gamif)\b/u', $m)) {
            $groups[] = self::GAMIFICATION;
        }
        if (preg_match('/\b(support ticket|ticket reply|help desk)\b/u', $m)) {
            $groups[] = self::SUPPORT;
        }
        if (preg_match('/\b(lesson|module quiz)\b/u', $m)) {
            $groups[] = self::LESSONS;
        }
        if (preg_match('/\b(maintenance|platform|all workspaces|every workspace|failed job|queue)\b/u', $m)) {
            $groups[] = self::PLATFORM;
        }
        if (preg_match('/\b(export|download|csv|xlsx|docx|pdf report|excel|spreadsheet)\b/u', $m)) {
            $groups[] = self::EXPORT;
        }

        if ($groups === [] && self::looksLikeBareName($m)) {
            return [self::READ, self::USERS];
        }

        if ($groups === []) {
            return [self::ALL];
        }

        return array_values(array_unique($groups));
    }

    public static function looksLikeBareName(string $message): bool
    {
        $m = trim($message);
        if ($m === '' || mb_strlen($m) > 80) {
            return false;
        }

        if (preg_match('/[?!]|\b(create|update|delete|show|list|export|grade|find|search|please|can you|how|what|when|where|why)\b/iu', $m)) {
            return false;
        }

        return (bool) preg_match("/^[\p{L}][\p{L}\-']*(?:\s+[\p{L}][\p{L}\-']*){0,3}$/u", $m);
    }

    public static function isMultiStep(array $groups): bool
    {
        if (in_array(self::ALL, $groups, true)) {
            return true;
        }

        $writeGroups = array_diff($groups, [self::READ]);

        return count($writeGroups) >= 2;
    }
}
