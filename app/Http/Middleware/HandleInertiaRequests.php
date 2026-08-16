<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\PublicFileUrl;
use App\Support\StudentPageRegistry;
use Illuminate\Http\Request;
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
            'seo' => [
                'siteName' => config('seo.site_name'),
                'tagline' => config('seo.tagline'),
                'description' => config('seo.description'),
                'siteUrl' => config('app.url'),
                'ogImage' => config('seo.og_image'),
                'locale' => config('seo.locale'),
            ],
            'notifications' => fn () => $request->user() ? [
                'unreadCount' => $request->user()->unreadNotifications()->count(),
                'items' => $request->user()->notifications()
                    ->select(['id', 'data', 'read_at', 'created_at'])
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
            'aiChat' => fn () => [
                'enabled' => (bool) Setting::get('ai_chat_enabled', true),
                'maintenanceMessage' => Setting::get('ai_chat_maintenance_message', 'Echo is currently under maintenance. Please try again later.'),
                'isAdmin' => (bool) ($request->user()?->is_admin),
                'suggestions' => $request->user()?->is_admin
                    ? [
                        ['label' => '📋 Needs grading', 'message' => 'Which submissions are waiting to be graded?'],
                        ['label' => '🗂️ Workspace overview', 'message' => 'Give me an overview of my workspace'],
                        ['label' => '📝 Create an exam', 'message' => 'Help me create a new exam'],
                        ['label' => '📣 Post announcement', 'message' => 'I want to post an announcement for my students'],
                    ]
                    : [
                        ['label' => '📋 My Assignments', 'message' => 'What are my upcoming assignments?'],
                        ['label' => '📝 Upcoming Exams', 'message' => 'What exams do I have coming up?'],
                        ['label' => '📊 My Progress', 'message' => 'Show me my learning progress'],
                        ['label' => '🎯 Claim Daily XP', 'message' => 'Claim my daily XP reward'],
                    ],
            ],
            'chats' => fn () => [
                'enabled' => (bool) Setting::get('chats_enabled', true),
                'maintenanceMessage' => Setting::get('chats_maintenance_message', 'Chats are currently under maintenance. Please try again later.'),
            ],
            'studentPageControls' => function () use ($request) {
                $shared = StudentPageRegistry::sharedForPath($request->path());
                $chatsEnabled = (bool) Setting::get('chats_enabled', true);

                if (! $chatsEnabled && ! ($request->user()?->is_admin)) {
                    if (isset($shared['pages']['chats'])) {
                        $shared['pages']['chats']['mode'] = StudentPageRegistry::MODE_DISABLED;
                        $shared['pages']['chats']['message'] = Setting::get('chats_maintenance_message', 'Chats are currently under maintenance. Please try again later.');
                    }

                    if (($shared['current']['key'] ?? null) === 'chats') {
                        $shared['current']['mode'] = StudentPageRegistry::MODE_DISABLED;
                        $shared['current']['message'] = Setting::get('chats_maintenance_message', 'Chats are currently under maintenance. Please try again later.');
                    }
                }

                return $shared;
            },
            'schoolBranding' => fn () => [
                'name' => Setting::get('school_name', 'LSI Engine'),
                'tagline' => Setting::get('school_tagline', 'Learning Systems Intelligence'),
                'logoUrl' => PublicFileUrl::resolve(Setting::get('school_logo_path')),
                'accentColor' => Setting::get('school_accent_color', '#f59e0b'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'sectionName' => fn () => $request->user()?->sections->pluck('name')->join(', '),
        ];
    }
}
