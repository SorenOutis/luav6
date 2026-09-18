<?php

namespace App\Http\Data;

use App\Models\User;

final class AuthUserData
{
    /** @return array<string, mixed>|null */
    public static function from(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'public_id' => $user->public_id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'cover_photo' => $user->cover_photo,
            'bio' => $user->bio,
            'is_admin' => (bool) $user->is_admin,
            'is_super_admin' => (bool) $user->is_super_admin,
            'is_banned' => (bool) $user->is_banned,
            'banned_at' => $user->banned_at?->toIso8601String(),
            'ban_reason' => $user->ban_reason,
            'profile_visibility' => $user->profile_visibility,
            'profile_show_activity' => (bool) $user->profile_show_activity,
            'profile_show_sections' => (bool) $user->profile_show_sections,
            'profile_show_social' => (bool) $user->profile_show_social,
            'profile_show_achievements' => (bool) $user->profile_show_achievements,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }
}
