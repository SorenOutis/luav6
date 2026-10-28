<?php

namespace App\Support;

use App\Models\Section;
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

    public const SETTING_SECTION_VIEWABLE_MAP = 'community_section_viewable_sections';

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

        if (array_key_exists('section_viewable_map', $data) && is_array($data['section_viewable_map'])) {
            static::saveSectionViewableMap($data['section_viewable_map']);
        }
    }

    /**
     * Get the normalized per-section cross-section visibility map.
     * Keys are integer section IDs; values are either ['*'] (all sections) or array of integer section IDs.
     *
     * @return array<int, array<int, int|string>>
     */
    public static function sectionViewableMap(): array
    {
        $raw = Setting::get(self::SETTING_SECTION_VIEWABLE_MAP);
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $sectionId => $targets) {
            $secId = (int) $sectionId;
            if ($secId <= 0 || ! is_array($targets)) {
                continue;
            }

            if (in_array('*', $targets, true)) {
                $normalized[$secId] = ['*'];

                continue;
            }

            $ids = array_values(array_unique(array_filter(
                array_map('intval', $targets),
                fn (int $id): bool => $id > 0 && $id !== $secId,
            )));

            if ($ids !== []) {
                $normalized[$secId] = $ids;
            }
        }

        return $normalized;
    }

    /**
     * Get the configured viewable section entries for a specific section.
     *
     * @return array<int, int|string>
     */
    public static function viewableForSection(int $sectionId): array
    {
        $map = static::sectionViewableMap();

        return $map[$sectionId] ?? [];
    }

    /**
     * Determine whether a section is configured to view all other sections in its workspace.
     */
    public static function canSectionViewAll(int $sectionId): bool
    {
        return in_array('*', static::viewableForSection($sectionId), true);
    }

    /**
     * Persist the entire per-section viewable sections map.
     *
     * @param  array<int|string, mixed>  $map
     */
    public static function saveSectionViewableMap(array $map): void
    {
        $normalized = [];
        foreach ($map as $sectionId => $targets) {
            $secId = (int) $sectionId;
            if ($secId <= 0 || ! is_array($targets)) {
                continue;
            }

            if (in_array('*', $targets, true)) {
                $normalized[(string) $secId] = ['*'];

                continue;
            }

            $ids = array_values(array_unique(array_filter(
                array_map('intval', $targets),
                fn (int $id): bool => $id > 0 && $id !== $secId,
            )));

            if ($ids !== []) {
                $normalized[(string) $secId] = $ids;
            }
        }

        Setting::setGlobal(self::SETTING_SECTION_VIEWABLE_MAP, json_encode($normalized));
    }

    /**
     * Update which other sections a single section is allowed to view.
     *
     * @param  array<int, int|string>  $targetSectionIds
     */
    public static function setSectionViewable(int $sectionId, array $targetSectionIds = [], bool $canViewAll = false): void
    {
        if ($sectionId <= 0) {
            return;
        }

        $map = static::sectionViewableMap();

        if ($canViewAll) {
            $map[$sectionId] = ['*'];
        } else {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', $targetSectionIds),
                fn (int $id): bool => $id > 0 && $id !== $sectionId,
            )));

            if ($ids === []) {
                unset($map[$sectionId]);
            } else {
                $map[$sectionId] = $ids;
            }
        }

        static::saveSectionViewableMap($map);
    }

    /**
     * Resolve all section IDs that a single section can view (including itself).
     *
     * @return array<int, int>
     */
    public static function resolveViewableSectionIdsForSection(int $sectionId, ?int $workspaceId = null): array
    {
        if ($sectionId <= 0) {
            return [];
        }

        $configured = static::viewableForSection($sectionId);

        if (in_array('*', $configured, true)) {
            $allIds = Section::withoutGlobalScope('workspace')
                ->when($workspaceId !== null, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return array_values(array_unique(array_merge([$sectionId], $allIds)));
        }

        $targetIds = array_values(array_unique(array_merge(
            [$sectionId],
            array_map('intval', $configured),
        )));

        if ($workspaceId !== null && count($targetIds) > 1) {
            $validIds = Section::withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $targetIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return array_values(array_unique(array_merge([$sectionId], $validIds)));
        }

        return $targetIds;
    }

    /**
     * Resolve all section IDs that a student with the given enrolled sections can view.
     *
     * @param  array<int, int|string>  $enrolledSectionIds
     * @return array<int, int>
     */
    public static function resolveViewableSectionIds(array $enrolledSectionIds, ?int $workspaceId = null): array
    {
        $enrolled = array_values(array_unique(array_filter(
            array_map('intval', $enrolledSectionIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($enrolled === []) {
            return [];
        }

        $merged = [];
        foreach ($enrolled as $secId) {
            $merged = array_merge($merged, static::resolveViewableSectionIdsForSection($secId, $workspaceId));
        }

        return array_values(array_unique($merged));
    }
}
