<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\AvatarGallery;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'avatar_preset' => [
                'nullable',
                'string',
                Rule::in(AvatarGallery::paths()),
            ],
            'profile_music_track_id' => [
                'nullable',
                Rule::exists('profile_music_tracks', 'id')
                    ->where('is_active', true)
                    ->where(function ($query) {
                        $wsId = app(WorkspaceContext::class)->id();
                        $query->where(function ($sub) use ($wsId) {
                            $sub->where('is_global', true)->orWhereNull('workspace_id');
                            if ($wsId) {
                                $sub->orWhere('workspace_id', $wsId);
                            }
                        });
                    }),
            ],
        ];
    }
}
