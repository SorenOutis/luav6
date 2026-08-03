<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\StudentPageRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => fn () => $request->user() ? [
                'unreadCount' => $request->user()->unreadNotifications()->count(),
                'items' => $request->user()->notifications()
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'type' => $notification->data['type'] ?? 'system',
                        'icon' => $notification->data['icon'] ?? 'bell',
                        'title' => $notification->data['title'] ?? 'Notification',
                        'message' => $notification->data['message'] ?? null,
                        'meta' => $notification->data['meta'] ?? null,
                        'image' => $notification->data['image'] ?? null,
                        'href' => $notification->data['href'] ?? '/dashboard',
                        'readAt' => optional($notification->read_at)?->toIso8601String(),
                        'createdAt' => $notification->created_at?->diffForHumans(),
                    ])
                    ->values(),
            ] : [
                'unreadCount' => 0,
                'items' => [],
            ],
            'aiChat' => [
                'enabled' => (bool) Setting::get('ai_chat_enabled', true),
                'maintenanceMessage' => Setting::get('ai_chat_maintenance_message', 'Echo is currently under maintenance. Please try again later.'),
            ],
            'studentPageControls' => fn () => StudentPageRegistry::sharedForPath($request->path()),
            'schoolBranding' => fn () => [
                'name' => Setting::get('school_name', 'LSI Engine'),
                'tagline' => Setting::get('school_tagline', 'Learning Systems Intelligence'),
                'logoUrl' => filled(Setting::get('school_logo_path')) ? Storage::disk('public')->url(Setting::get('school_logo_path')) : null,
                'accentColor' => Setting::get('school_accent_color', '#f59e0b'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sectionName' => fn () => $request->user()?->sections->pluck('name')->join(', '),
        ];
    }
}
