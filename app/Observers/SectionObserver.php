<?php

namespace App\Observers;

use App\Models\Section;
use App\Services\AdminNotificationService;

class SectionObserver
{
    /**
     * Handle the Section "created" event.
     */
    public function created(Section $section): void
    {
        AdminNotificationService::notifyAdmins(
            title: 'New Section Created',
            body: "Section '{$section->name}' was created.",
            workspace: $section->workspace,
            icon: 'heroicon-o-folder-plus',
            color: 'primary',
        );
    }
}
