<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'first_name' => $this->nameRules(),
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'cover_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
            'bio' => ['nullable', 'string', 'max:280'],
            'profile_visibility' => [
                'sometimes',
                'string',
                Rule::in([
                    User::PROFILE_VISIBILITY_SECTION,
                    User::PROFILE_VISIBILITY_PRIVATE,
                ]),
            ],
            'profile_show_activity' => ['sometimes', 'boolean'],
            'profile_show_sections' => ['sometimes', 'boolean'],
            'profile_show_social' => ['sometimes', 'boolean'],
            'profile_show_achievements' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the validation rules used to validate a required name part.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
