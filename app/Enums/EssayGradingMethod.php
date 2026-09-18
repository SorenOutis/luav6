<?php

namespace App\Enums;

enum EssayGradingMethod: string
{
    case Ai = 'ai';
    case Manual = 'manual';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Ai->value => 'AI grades automatically',
            self::Manual->value => 'Teacher grades manually',
        ];
    }

    /** @param array<string, mixed> $question */
    public static function forQuestion(array $question): self
    {
        return self::tryFrom((string) ($question['grading_method'] ?? '')) ?? self::Ai;
    }

    /** @param array<string, mixed> $answer */
    public static function forAnswer(array $answer): self
    {
        return self::tryFrom((string) ($answer['grading_method'] ?? '')) ?? self::Ai;
    }
}
