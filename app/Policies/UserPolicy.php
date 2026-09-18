<?php

namespace App\Policies;

use App\Models\User;
use App\Support\WorkspaceContext;

class UserPolicy
{
    public function viewPublicProfile(User $viewer, User $profile): bool
    {
        if ($viewer->is($profile) || $viewer->isSuperAdmin()) {
            return true;
        }

        // Student-facing profiles never expose administrator accounts.
        if ($profile->is_admin) {
            return false;
        }

        if ($this->managesStudent($viewer, $profile)) {
            return true;
        }

        if (($profile->profile_visibility ?? User::PROFILE_VISIBILITY_SECTION) === User::PROFILE_VISIBILITY_PRIVATE) {
            return false;
        }

        return ! $viewer->is_admin && $this->sharesSection($viewer, $profile);
    }

    /** Detailed grades/progress are private to the owner and authorized staff. */
    public function viewPrivateProgress(User $viewer, User $profile): bool
    {
        return $viewer->is($profile)
            || $viewer->isSuperAdmin()
            || $this->managesStudent($viewer, $profile);
    }

    /** A student may explicitly opt in to sharing their activity with peers. */
    public function viewProfileActivity(User $viewer, User $profile): bool
    {
        return $this->viewPrivateProgress($viewer, $profile)
            || (($profile->profile_show_activity ?? false)
                && $this->viewPublicProfile($viewer, $profile));
    }

    public function interactWithProfile(User $viewer, User $profile): bool
    {
        return ! $viewer->is_admin
            && ! $profile->is_admin
            && ! $viewer->is($profile)
            && ($profile->profile_show_social ?? true)
            && $this->viewPublicProfile($viewer, $profile)
            && $this->sharesSection($viewer, $profile);
    }

    private function managesStudent(User $viewer, User $profile): bool
    {
        if (! $viewer->is_admin || $viewer->isSuperAdmin() || $profile->is_admin) {
            return false;
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        return $profile->sections()
            ->when($workspaceId, fn ($query) => $query->where('sections.workspace_id', $workspaceId))
            ->when(! $workspaceId, fn ($query) => $query->whereNull('sections.workspace_id'))
            ->exists();
    }

    private function sharesSection(User $first, User $second): bool
    {
        return $first->sections()
            ->whereIn('sections.id', $second->sections()->select('sections.id'))
            ->exists();
    }
}
