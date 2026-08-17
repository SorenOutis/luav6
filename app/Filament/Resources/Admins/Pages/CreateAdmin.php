<?php

namespace App\Filament\Resources\Admins\Pages;

use App\Filament\Resources\Admins\AdminResource;
use App\Models\Workspace;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    protected ?int $workspaceId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->workspaceId = filled($data['workspace_id'] ?? null)
            ? (int) $data['workspace_id']
            : null;
        unset($data['workspace_id']);

        $data['is_admin'] = true;
        $data['is_super_admin'] = false;
        $data['email_verified_at'] = now();
        $data['password'] = Hash::make($data['password']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->workspaceId) {
            $workspace = Workspace::query()->findOrFail($this->workspaceId);
            $workspace->users()->syncWithoutDetaching([
                $this->record->id => ['role' => Workspace::ROLE_ADMIN],
            ]);
            $this->record->forceFill(['current_workspace_id' => $workspace->id])->save();

            return;
        }

        Workspace::createForOwner($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Admin account created successfully. They can now manage the assigned tenant workspace.';
    }
}
