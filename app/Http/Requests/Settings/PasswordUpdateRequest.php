<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Accounts created through social login have no password yet, so there
        // is nothing to confirm the first time they set one.
        $currentPasswordRules = $this->user()?->hasPassword()
            ? $this->currentPasswordRules()
            : ['nullable'];

        return [
            'current_password' => $currentPasswordRules,
            'password' => $this->passwordRules(),
        ];
    }
}
