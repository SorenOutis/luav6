<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Section;
use App\Models\Workspace;
use App\Support\AvatarGallery;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'userSections' => $user->sections()->orderBy('name')->get(['sections.id', 'name']),
            'avatarGallery' => AvatarGallery::items(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        // The uploaded files and preset value are handled explicitly below.
        // They must never reach fill(): both columns are mass-assignable, so
        // filling an UploadedFile would persist a useless temp path (or
        // throw) when nothing else overwrites it.
        $validated = $request->validated();
        $user->fill(Arr::except($validated, ['avatar', 'avatar_preset', 'cover_photo']));

        $replaced = [];
        $avatarPreset = $validated['avatar_preset'] ?? null;

        if (! $request->hasFile('avatar') && filled($avatarPreset)) {
            $previous = (string) $user->getRawOriginal('avatar');
            $user->avatar = $avatarPreset;

            if (
                filled($previous)
                && $previous !== $avatarPreset
                && ! AvatarGallery::isCurated($previous)
                && ! Str::startsWith($previous, ['http://', 'https://', '//'])
            ) {
                $replaced[] = $previous;
            }
        }

        $replaced = array_filter(array_merge($replaced, [
            $this->storeUpload($request, 'avatar', 'avatars'),
            $this->storeUpload($request, 'cover_photo', 'covers'),
        ]));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        // Only discard the superseded files once the new paths are committed,
        // so a failed save can never leave the user pointing at a file that
        // has already been deleted.
        foreach ($replaced as $path) {
            Storage::disk('public')->delete($path);
        }

        return to_route('profile.edit');
    }

    /**
     * Persist an uploaded image to the public disk and point the column at it.
     *
     * Returns the path of the file this upload supersedes (if any) so the
     * caller can delete it *after* the new path is committed to the database.
     * A write failure surfaces as a validation error rather than a silent
     * no-op — the previous version assumed store() always succeeded, which is
     * how a failed save could look like "nothing happened" to the user.
     */
    private function storeUpload(ProfileUpdateRequest $request, string $field, string $directory): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);

        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $field => 'The upload did not complete. Please try again.',
            ]);
        }

        $user = $request->user();
        $previous = $user->getRawOriginal($field);

        $path = $file->store($directory, 'public');

        if (! $path) {
            throw ValidationException::withMessages([
                $field => 'We could not save that image. Please try again.',
            ]);
        }

        $user->{$field} = $path;

        if (
            ! $previous
            || $previous === $path
            || ($field === 'avatar' && AvatarGallery::isCurated((string) $previous))
            || Str::startsWith((string) $previous, ['http://', 'https://', '//'])
        ) {
            return null;
        }

        return (string) $previous;
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Update the user's sections.
     */
    public function updateSection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_ids' => ['required', 'array'],
            'section_ids.*' => ['exists:sections,id'],
            'section_passwords' => ['sometimes', 'array'],
            'section_passwords.*' => ['nullable', 'string'],
        ]);

        $sectionIds = $validated['section_ids'];
        $passwords = $validated['section_passwords'] ?? [];

        $sections = Section::whereIn('id', $sectionIds)->get();

        $errors = [];
        foreach ($sections as $section) {
            $rawPassword = $section->getRawOriginal('password');
            if (! filled($rawPassword)) {
                continue;
            }

            $submitted = $passwords[$section->id] ?? null;
            if (! filled($submitted) || ! Hash::check($submitted, $rawPassword)) {
                $errors["section_passwords.{$section->id}"] = "Incorrect password for {$section->name}.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $syncData = $sections->mapWithKeys(fn ($s) => [$s->id => ['season_id' => $s->season_id]])->all();
        $request->user()->sections()->sync($syncData);

        if (! $request->user()->is_admin) {
            foreach ($sections->pluck('workspace_id')->filter()->unique() as $workspaceId) {
                if (! $request->user()->workspaces()->whereKey($workspaceId)->exists()) {
                    $request->user()->workspaces()->attach((int) $workspaceId, [
                        'role' => Workspace::ROLE_STUDENT,
                    ]);
                }
            }
        }

        return back();
    }

    /**
     * Join a section by its unique join code.
     */
    public function joinByCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:9'],
        ]);

        $code = Section::normalizeJoinCode($data['code']);

        $section = Section::findByJoinCode($code);

        if (! $section) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid section code. Please check and try again.',
            ], 422);
        }

        // Check if user is already in this section
        $user = $request->user();
        if ($user->sections()->where('section_id', $section->id)->exists()) {
            if ($section->workspace_id) {
                $user->joinWorkspace((int) $section->workspace_id);
            }

            return response()->json([
                'valid' => true,
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'already_joined' => true,
                ],
            ]);
        }

        // Join the section and activate its tenant. The same student account
        // may belong to more than one tenant, but every request has one context.
        $user->sections()->syncWithoutDetaching([$section->id => ['season_id' => $section->season_id]]);
        if ($section->workspace_id) {
            $user->joinWorkspace((int) $section->workspace_id);
        }

        return response()->json([
            'valid' => true,
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'already_joined' => false,
            ],
        ]);
    }

    /**
     * Verify a single section's password (used for the flip-card unlock flow).
     */
    public function verifySectionPassword(Request $request, Section $section)
    {
        $data = $request->validate([
            'password' => ['nullable', 'string'],
        ]);

        $rawPassword = $section->getRawOriginal('password');

        // Section without a password is always "valid"
        if (! filled($rawPassword)) {
            return response()->json(['valid' => true]);
        }

        $submitted = $data['password'] ?? '';
        $valid = filled($submitted) && Hash::check($submitted, $rawPassword);

        return response()->json(['valid' => $valid]);
    }
}
