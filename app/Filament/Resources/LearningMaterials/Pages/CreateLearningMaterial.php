<?php

namespace App\Filament\Resources\LearningMaterials\Pages;

use App\Filament\Resources\LearningMaterials\LearningMaterialResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateLearningMaterial extends CreateRecord
{
    protected static string $resource = LearningMaterialResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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

            // Sanitize display filename
            if (filled($data['file_name'] ?? null)) {
                $data['file_name'] = self::sanitizeFileName($data['file_name']);
            }
        }

        return $data;
    }

    public static function sanitizeFileName(string $name): string
    {
        $name = basename(trim($name));
        $name = preg_replace('/[^A-Za-z0-9._\- ]+/', '', $name) ?? $name;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $name !== '' ? $name : 'document.pdf';
    }
}
