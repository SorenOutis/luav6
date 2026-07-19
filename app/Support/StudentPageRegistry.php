<?php

namespace App\Support;

use App\Models\Setting;

class StudentPageRegistry
{
    public const SETTING_KEY = 'student_page_controls';

    public const MODE_ENABLED = 'enabled';

    public const MODE_BLURRED = 'blurred';

    public const MODE_DISABLED = 'disabled';

    public static function pages(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'path' => '/dashboard',
                'description' => 'Student home, announcements, progress, leaderboard, and upcoming activities.',
            ],
            'assignments' => [
                'label' => 'Assignments',
                'path' => '/assignments',
                'description' => 'Assignment listing and student submission workflow.',
            ],
            'exams' => [
                'label' => 'Activities / Exams',
                'path' => '/exams',
                'description' => 'Published exams, activity list, exam taking, and submissions.',
            ],
            'games' => [
                'label' => 'Games',
                'path' => '/games',
                'description' => 'Games hub and playable game routes.',
            ],
            'grades' => [
                'label' => 'Grades',
                'path' => '/grades',
                'description' => 'Student grade viewing page.',
            ],
            'maps' => [
                'label' => 'Learning Maps',
                'path' => '/maps',
                'description' => 'Interactive learning map and node completion.',
            ],
            'courses' => [
                'label' => 'Courses',
                'path' => '/courses',
                'description' => 'Course catalog, lesson viewer, and learning content.',
            ],
            'ngl' => [
                'label' => 'Anonymous Messages',
                'path' => '/ngl',
                'description' => 'Anonymous classroom message board.',
            ],
            'profile' => [
                'label' => 'Profile & Settings',
                'path' => '/settings',
                'description' => 'Student profile, password, appearance, and account settings.',
            ],
        ];
    }

    public static function defaults(): array
    {
        return collect(static::pages())
            ->map(fn () => [
                'mode' => static::MODE_ENABLED,
                'message' => null,
            ])
            ->all();
    }

    public static function controls(): array
    {
        $stored = Setting::get(static::SETTING_KEY);
        $decoded = is_string($stored) ? json_decode($stored, true) : [];
        $decoded = is_array($decoded) ? $decoded : [];

        return collect(static::defaults())
            ->map(function (array $default, string $key) use ($decoded) {
                $control = is_array($decoded[$key] ?? null) ? $decoded[$key] : [];
                $mode = $control['mode'] ?? $default['mode'];

                if (! in_array($mode, [static::MODE_ENABLED, static::MODE_BLURRED, static::MODE_DISABLED], true)) {
                    $mode = static::MODE_ENABLED;
                }

                return [
                    'mode' => $mode,
                    'message' => filled($control['message'] ?? null) ? (string) $control['message'] : null,
                ];
            })
            ->all();
    }

    public static function setControls(array $controls): void
    {
        $normalized = collect(static::defaults())
            ->map(function (array $default, string $key) use ($controls) {
                $control = is_array($controls[$key] ?? null) ? $controls[$key] : [];
                $mode = $control['mode'] ?? $default['mode'];

                if (! in_array($mode, [static::MODE_ENABLED, static::MODE_BLURRED, static::MODE_DISABLED], true)) {
                    $mode = static::MODE_ENABLED;
                }

                return [
                    'mode' => $mode,
                    'message' => filled($control['message'] ?? null) ? trim((string) $control['message']) : null,
                ];
            })
            ->all();

        Setting::set(static::SETTING_KEY, json_encode($normalized));
    }

    public static function controlFor(string $key): array
    {
        return static::controls()[$key] ?? [
            'mode' => static::MODE_ENABLED,
            'message' => null,
        ];
    }

    public static function sharedForPath(string $path): array
    {
        $pages = static::pages();
        $controls = static::controls();
        $currentKey = collect($pages)
            ->keys()
            ->first(fn (string $key) => static::pathMatches($path, $pages[$key]['path']));

        return [
            'pages' => collect($pages)
                ->map(fn (array $page, string $key) => [
                    'label' => $page['label'],
                    'path' => $page['path'],
                    'mode' => $controls[$key]['mode'] ?? static::MODE_ENABLED,
                    'message' => $controls[$key]['message'] ?? null,
                ])
                ->all(),
            'current' => $currentKey ? [
                'key' => $currentKey,
                'label' => $pages[$currentKey]['label'],
                'mode' => $controls[$currentKey]['mode'] ?? static::MODE_ENABLED,
                'message' => $controls[$currentKey]['message'] ?? null,
            ] : null,
        ];
    }

    private static function pathMatches(string $currentPath, string $configuredPath): bool
    {
        $currentPath = '/'.ltrim($currentPath, '/');
        $configuredPath = '/'.trim($configuredPath, '/');

        return $currentPath === $configuredPath || str_starts_with($currentPath, $configuredPath.'/');
    }
}
