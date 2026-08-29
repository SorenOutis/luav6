<?php

namespace App\Observers;

use App\Models\Section;
use App\Services\AdminNotificationService;
use Throwable;

class SectionObserver
{
    /**
     * Handle the Section "created" event.
     */
    public function created(Section $section): void
    {
        try {
            $workspace = AdminNotificationService::resolveWorkspace($section);

            AdminNotificationService::notifyAdmins(
                title: 'New Section Created',
                body: "Section '{$section->name}' was created.",
                workspace: $workspace,
                icon: 'heroicon-o-folder-plus',
                color: 'primary',
            );
        } catch (Throwable) {
        }
    }
}
