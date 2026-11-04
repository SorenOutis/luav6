<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Settings gate for the proactive bot watchers.
 *
 * Watchers are workspace-scheduled specialists that only read. They hold no
 * write powers: everything they find becomes a suggestion in the team room,
 * and any actual posting, replying, or announcing remains approval-gated.
 */
final class AiBotWatchers
{
    public const ENABLED_KEY = 'ai_bot_watchers_enabled';

    public const ALERT_EMAIL_KEY = 'ai_bot_watchers_alert_email';

    /** Boolean string preserved because platform settings store '1'/'0'. */
    public const ENABLED_DEFAULT = '1';

    public static function enabled(): bool
    {
        return (bool) Setting::get(self::ENABLED_KEY, self::ENABLED_DEFAULT);
    }

    public static function setEnabled(bool $enabled): void
    {
        Setting::setGlobal(self::ENABLED_KEY, $enabled ? '1' : '0');
    }

    /**
     * Override alert address; when unset, the first super administrator owns
     * the watcher inbox.
     */
    public static function alertEmail(): ?string
    {
        $override = trim((string) Setting::get(self::ALERT_EMAIL_KEY, ''));

        if ($override !== '') {
            return $override;
        }

        return User::query()
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->orderBy('id')
            ->value('email');
    }

    public static function setAlertEmail(?string $email): void
    {
        Setting::setGlobal(self::ALERT_EMAIL_KEY, mb_substr(trim((string) $email), 0, 190));
    }
}
