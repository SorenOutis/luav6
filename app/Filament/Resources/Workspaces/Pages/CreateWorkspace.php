<?php

namespace App\Filament\Resources\Workspaces\Pages;

use App\Filament\Resources\Workspaces\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkspace extends CreateRecord
{
    protected static string $resource = WorkspaceResource::class;

    protected ?int $ownerId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ownerId = (int) $data['owner_id'];
        unset($data['owner_id']);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->users()->attach($this->ownerId, [
            'role' => Workspace::ROLE_OWNER,
        ]);

        $owner = User::query()->find($this->ownerId);
        if ($owner && ! $owner->current_workspace_id) {
            $owner->forceFill(['current_workspace_id' => $this->record->id])->save();
        }
    }
}
