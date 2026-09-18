<?php

namespace App\Filament\Resources\Workspaces\Pages;

use App\Filament\Resources\Workspaces\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditWorkspace extends EditRecord
{
    protected static string $resource = WorkspaceResource::class;

    protected ?int $ownerId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['owner_id'] = $this->record->users()
            ->wherePivot('role', Workspace::ROLE_OWNER)
            ->value('users.id')
            ?? $this->record->created_by;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->ownerId = (int) $data['owner_id'];
        unset($data['owner_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        DB::table('workspace_user')
            ->where('workspace_id', $this->record->id)
            ->where('role', Workspace::ROLE_OWNER)
            ->update(['role' => Workspace::ROLE_ADMIN, 'updated_at' => now()]);

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $this->record->id, 'user_id' => $this->ownerId],
            ['role' => Workspace::ROLE_OWNER, 'updated_at' => now(), 'created_at' => now()],
        );

        $owner = User::query()->find($this->ownerId);
        if ($owner && ! $owner->current_workspace_id) {
            $owner->forceFill(['current_workspace_id' => $this->record->id])->save();
        }
    }
}
