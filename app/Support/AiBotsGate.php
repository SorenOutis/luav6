<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Global kill switch for the bot roster.
 *
 * A platform super admin can switch every bot off from Platform Settings.
 * When disabled, no bot conversation can start or continue, no routine runs,
 * no handoff is delivered, and no coordinator delegation is mounted. Ordinary
 * (non-bot) Echo chat and the control center are unaffected, so operators can
 * still re-enable bots and inspect history.
 */
final class AiBotsGate
{
    public const ENABLED_KEY = 'ai_bots_enabled';

    public const MAINTENANCE_MESSAGE_KEY = 'ai_bots_maintenance_message';

    public const DEFAULT_DISABLED_MESSAGE = 'AI bots are currently disabled by a platform administrator.';

    public static function enabled(): bool
    {
        return (bool) Setting::get(self::ENABLED_KEY, '1');
    }

    public static function disabledMessage(): string
    {
        return (string) Setting::get(self::MAINTENANCE_MESSAGE_KEY, self::DEFAULT_DISABLED_MESSAGE);
    }

    public static function setEnabled(bool $enabled): void
    {
        Setting::setGlobal(self::ENABLED_KEY, $enabled ? '1' : '0');
    }

    public static function setDisabledMessage(string $message): void
    {
        Setting::setGlobal(self::MAINTENANCE_MESSAGE_KEY, $message);
    }
}
