<?php

namespace App\Support;

use App\Models\Setting;

class PlatformMaintenance
{
    public const ENABLED_KEY = 'maintenance_enabled';

    public const TITLE_KEY = 'maintenance_title';

    public const MESSAGE_KEY = 'maintenance_message';

    public const IMAGE_KEY = 'maintenance_image';

    public const DEFAULT_TITLE = "We'll be right back";

    public const DEFAULT_MESSAGE = "We're doing scheduled maintenance to improve your learning experience. Please check back soon.";

    public const DEFAULT_IMAGE = 'maintenance';

    /**
     * @return array<string, string>
     */
    public static function images(): array
    {
        return [
            'maintenance' => 'Maintenance (wrench fox)',
            'calendar' => 'Calendar (waiting)',
            'chat' => 'Chat (announcement)',
            'welcome-hero' => 'Checklist (fixing)',
            'library' => 'Reading (waiting)',
        ];
    }

    public static function isEnabled(): bool
    {
        return (bool) Setting::get(static::ENABLED_KEY, false);
    }

    public static function title(): string
    {
        $title = Setting::get(static::TITLE_KEY, static::DEFAULT_TITLE);

        return filled($title) ? (string) $title : static::DEFAULT_TITLE;
    }

    public static function message(): string
    {
        $message = Setting::get(static::MESSAGE_KEY, static::DEFAULT_MESSAGE);

        return filled($message) ? (string) $message : static::DEFAULT_MESSAGE;
    }

    public static function image(): string
    {
        $image = (string) Setting::get(static::IMAGE_KEY, static::DEFAULT_IMAGE);

        return array_key_exists($image, static::images()) ? $image : static::DEFAULT_IMAGE;
    }

    /**
     * @return array{title: string, message: string, image: string, imageUrl: string, isAuthenticated: bool}
     */
    public static function payload(bool $isAuthenticated = false): array
    {
        $image = static::image();

        return [
            'title' => static::title(),
            'message' => static::message(),
            'image' => $image,
            'imageUrl' => "/images/mascots/fox-{$image}.webp",
            'isAuthenticated' => $isAuthenticated,
        ];
    }

    /**
     * @return array{maintenance_enabled: bool, maintenance_title: string, maintenance_message: string, maintenance_image: string}
     */
    public static function formState(): array
    {
        return [
            'maintenance_enabled' => static::isEnabled(),
            'maintenance_title' => static::title(),
            'maintenance_message' => static::message(),
            'maintenance_image' => static::image(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $enabled = (bool) ($data['maintenance_enabled'] ?? false);

        Setting::setGlobal(static::ENABLED_KEY, $enabled ? '1' : '0');

        $title = trim((string) ($data['maintenance_title'] ?? ''));
        Setting::setGlobal(static::TITLE_KEY, $title !== '' ? $title : static::DEFAULT_TITLE);

        $message = trim((string) ($data['maintenance_message'] ?? ''));
        Setting::setGlobal(static::MESSAGE_KEY, $message !== '' ? $message : static::DEFAULT_MESSAGE);

        $image = (string) ($data['maintenance_image'] ?? static::DEFAULT_IMAGE);
        Setting::setGlobal(static::IMAGE_KEY, array_key_exists($image, static::images()) ? $image : static::DEFAULT_IMAGE);
    }
}
