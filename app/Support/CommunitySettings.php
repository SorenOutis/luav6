<?php

namespace App\Support;

use App\Models\Setting;

class CommunitySettings
{
    public const SETTING_FEED_SCOPE = 'community_feed_scope';

    public const SETTING_RESTRICT_TO_SECTION = 'community_restrict_to_section';

    public const SETTING_ALLOW_ANONYMOUS = 'community_allow_anonymous_posts';

    public const SETTING_STORIES_ENABLED = 'community_stories_enabled';

    public const SETTING_MUSIC_ENABLED = 'community_music_enabled';

    public const SETTING_MEDIA_UPLOADS_ENABLED = 'community_media_uploads_enabled';

    public const SETTING_FILE_ATTACHMENTS_ENABLED = 'community_file_attachments_enabled';

    public const SCOPE_WORKSPACE = 'workspace';

    public const SCOPE_SECTION = 'section';

    public static function feedScope(): string
    {
        $explicitScope = Setting::get(self::SETTING_FEED_SCOPE);
        if ($explicitScope === self::SCOPE_SECTION || $explicitScope === self::SCOPE_WORKSPACE) {
            return $explicitScope;
        }

        $restrictToSection = Setting::get(self::SETTING_RESTRICT_TO_SECTION);
        if ($restrictToSection !== null) {
            return filter_var($restrictToSection, FILTER_VALIDATE_BOOLEAN)
                ? self::SCOPE_SECTION
                : self::SCOPE_WORKSPACE;
        }

        return self::SCOPE_WORKSPACE;
    }

    public static function isRestrictedToSection(): bool
    {
        return static::feedScope() === self::SCOPE_SECTION;
    }

    public static function allowsAnonymous(): bool
    {
        $setting = Setting::get(self::SETTING_ALLOW_ANONYMOUS);
        if ($setting === null) {
            return true;
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public static function storiesEnabled(): bool
    {
        $setting = Setting::get(self::SETTING_STORIES_ENABLED);
        if ($setting === null) {
            return true;
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public static function musicEnabled(): bool
    {
        $setting = Setting::get(self::SETTING_MUSIC_ENABLED);
        if ($setting === null) {
            return true;
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public static function mediaUploadsEnabled(): bool
    {
        $setting = Setting::get(self::SETTING_MEDIA_UPLOADS_ENABLED);
        if ($setting === null) {
            return true;
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    public static function fileAttachmentsEnabled(): bool
    {
        $setting = Setting::get(self::SETTING_FILE_ATTACHMENTS_ENABLED);
        if ($setting === null) {
            return true;
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{feed_scope: string, allow_anonymous: bool, stories_enabled: bool, music_enabled: bool, media_uploads_enabled: bool, file_attachments_enabled: bool}
     */
    public static function formState(): array
    {
        return [
            'feed_scope' => static::feedScope(),
            'allow_anonymous' => static::allowsAnonymous(),
            'stories_enabled' => static::storiesEnabled(),
            'music_enabled' => static::musicEnabled(),
            'media_uploads_enabled' => static::mediaUploadsEnabled(),
            'file_attachments_enabled' => static::fileAttachmentsEnabled(),
        ];
    }

    /**
     * @param  array{feed_scope?: string, allow_anonymous?: bool, stories_enabled?: bool, music_enabled?: bool, media_uploads_enabled?: bool, file_attachments_enabled?: bool}  $data
     */
    public static function save(array $data): void
    {
        $scope = ($data['feed_scope'] ?? null) === self::SCOPE_SECTION
            ? self::SCOPE_SECTION
            : self::SCOPE_WORKSPACE;

        $isRestricted = ($scope === self::SCOPE_SECTION);
        $allowAnonymous = (bool) ($data['allow_anonymous'] ?? true);
        $storiesEnabled = (bool) ($data['stories_enabled'] ?? true);
        $musicEnabled = (bool) ($data['music_enabled'] ?? true);
        $mediaUploadsEnabled = (bool) ($data['media_uploads_enabled'] ?? true);
        $fileAttachmentsEnabled = (bool) ($data['file_attachments_enabled'] ?? true);

        // Update platform global settings
        Setting::setGlobal(self::SETTING_FEED_SCOPE, $scope);
        Setting::setGlobal(self::SETTING_RESTRICT_TO_SECTION, $isRestricted ? '1' : '0');
        Setting::setGlobal(self::SETTING_ALLOW_ANONYMOUS, $allowAnonymous ? '1' : '0');
        Setting::setGlobal(self::SETTING_STORIES_ENABLED, $storiesEnabled ? '1' : '0');
        Setting::setGlobal(self::SETTING_MUSIC_ENABLED, $musicEnabled ? '1' : '0');
        Setting::setGlobal(self::SETTING_MEDIA_UPLOADS_ENABLED, $mediaUploadsEnabled ? '1' : '0');
        Setting::setGlobal(self::SETTING_FILE_ATTACHMENTS_ENABLED, $fileAttachmentsEnabled ? '1' : '0');
    }
}
