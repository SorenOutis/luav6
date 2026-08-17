<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Filament\Resources\Admins\AdminResource;
use App\Models\Workspace;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected ?int $workspaceId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['workspace_id'] = $this->record->current_workspace_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->workspaceId = filled($data['workspace_id'] ?? null)
            ? (int) $data['workspace_id']
            : null;
        unset($data['workspace_id']);

        // Only hash password if a new one was provided
        if (filled($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->workspaceId) {
            return;
        }

        $workspace = Workspace::query()->findOrFail($this->workspaceId);
        if (! $workspace->users()->whereKey($this->record->id)->exists()) {
            $workspace->users()->attach($this->record->id, ['role' => Workspace::ROLE_ADMIN]);
        }
        $this->record->forceFill(['current_workspace_id' => $workspace->id])->save();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Admin account updated successfully.';
    }
}
