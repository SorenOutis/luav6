<?php

namespace App\Filament\Resources\LearningMaterials\Pages;

use App\Filament\Resources\LearningMaterials\LearningMaterialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditLearningMaterial extends EditRecord
{
    protected static string $resource = LearningMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->enrichFileMetadata($data);
    }

    private function enrichFileMetadata(array $data): array
    {
        if (filled($data['file_path'] ?? null)) {
            $path = $data['file_path'];

            try {
                $disk = Storage::disk('public');

                if ($disk->exists($path)) {
                    $data['file_size'] = $disk->size($path);
                    $data['mime_type'] = $disk->mimeType($path);
                    $data['file_extension'] = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                } else {
                    $data['file_extension'] = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                }
            } catch (\Throwable $e) {
                $data['file_extension'] = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            }

            if (filled($data['file_name'] ?? null)) {
                $data['file_name'] = CreateLearningMaterial::sanitizeFileName($data['file_name']);
            }
        }

        return $data;
    }
}
