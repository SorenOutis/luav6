<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Section;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($request->hasFile('avatar')) {
            // Delete old avatar if it exists
            if ($user->getRawOriginal('avatar')) {
                Storage::disk('public')->delete($user->getRawOriginal('avatar'));
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        if ($request->hasFile('cover_photo')) {
            // Delete old cover photo if it exists
            if ($user->getRawOriginal('cover_photo')) {
                Storage::disk('public')->delete($user->getRawOriginal('cover_photo'));
            }

            $path = $request->file('cover_photo')->store('covers', 'public');
            $user->cover_photo = $path;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit');
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

        $request->user()->sections()->sync($sectionIds);

        return back();
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
