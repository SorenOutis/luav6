<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case Identification = 'identification';
    case Essay = 'essay';
    case TrueFalse = 'true_false';

    public function label(): string
    {
        return match ($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::Identification => 'Identification',
            self::Essay => 'Essay',
            self::TrueFalse => 'True/False',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    public static function tryFromStored(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtolower(trim($value))) : null;
    }

    public static function labelFor(mixed $value): string
    {
        return self::tryFromStored($value)?->label() ?? self::MultipleChoice->label();
    }

    public function usesChoiceAnswer(): bool
    {
        return $this === self::MultipleChoice || $this === self::TrueFalse;
    }

    public function usesTextAnswer(): bool
    {
        return ! $this->usesChoiceAnswer();
    }
}
