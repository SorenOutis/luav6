<?php

namespace App\Http\Controllers;

use App\Models\LearningMaterial;
use App\Models\LearningMaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LibraryHubController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $sectionIds = $user->sections()->pluck('sections.id');

        $query = LearningMaterial::query()
            ->with(['category:id,name,slug', 'sections:id,name'])
            ->when($user->is_admin, function ($q) {
                $q->published();
            }, function ($q) use ($sectionIds) {
                $q->visibleToSections($sectionIds);
            })
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->string('category'))
                    ->orWhere('id', $request->string('category'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $materials = $query->get()->map(function (LearningMaterial $material) {
            return $this->serializeMaterial($material);
        });

        $categories = LearningMaterialCategory::query()
            ->whereIn('id', $materials->pluck('category.id')->filter()->unique())
            ->get(['id', 'name', 'slug']);

        // Also include all categories that have at least one visible material? Provide full list for filter UI
        $allCategories = LearningMaterialCategory::query()
            ->when($user->is_admin, function ($q) {
                $q->whereHas('materials', fn ($mq) => $mq->published());
            }, function ($q) use ($sectionIds) {
                $q->whereHas('materials', fn ($mq) => $mq->visibleToSections($sectionIds));
            })
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Library/Index', [
            'materials' => $materials->values(),
            'categories' => $allCategories->values(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'category' => $request->string('category')->toString(),
            ],
        ]);
    }

    public function file(Request $request, LearningMaterial $material)
    {
        $user = $request->user();

        if (! $material->isVisibleTo($user)) {
            abort(403, 'This material is not available to you.');
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($material->file_path)) {
            abort(404, 'File not found.');
        }

        $isDownload = $request->boolean('download', false);

        if ($isDownload && ! $material->is_downloadable) {
            abort(403, 'Downloading is disabled for this material.');
        }

        // Increment counters
        if ($isDownload) {
            $material->increment('download_count');
        } else {
            // Count view on preview/stream
            $material->increment('view_count');
        }

        $mime = $material->mime_type ?: $disk->mimeType($material->file_path) ?: 'application/octet-stream';
        $sanitizedName = $this->sanitizeFileName($material->file_name ?: basename($material->file_path));

        // For inline preview we return file response with inline disposition
        // For download we use attachment
        $disposition = $isDownload ? 'attachment' : 'inline';

        return $disk->response($material->file_path, $sanitizedName, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($sanitizedName).'"',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function serializeMaterial(LearningMaterial $material): array
    {
        return [
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->description,
            'category' => $material->category ? [
                'id' => $material->category->id,
                'name' => $material->category->name,
                'slug' => $material->category->slug,
            ] : null,
            'file_name' => $material->file_name,
            'file_extension' => $material->file_extension,
            'file_size' => $material->file_size,
            'file_url' => $material->file_url,
            'cover_image' => $material->cover_image_url,
            'is_downloadable' => (bool) $material->is_downloadable,
            'view_count' => (int) $material->view_count,
            'download_count' => (int) $material->download_count,
            'sections' => $material->sections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
            'created_at' => $material->created_at?->toIso8601String(),
        ];
    }

    private function sanitizeFileName(string $name): string
    {
        $name = basename(trim($name));
        // Remove dangerous characters
        $name = preg_replace('/[^A-Za-z0-9._\- ]+/', '', $name) ?? $name;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ($name === '') {
            $name = 'document.pdf';
        }

        // Ensure extension preserved if stripped
        if (! str_contains($name, '.')) {
            $name .= '.pdf';
        }

        return $name;
    }
}
