<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Generates the level 1-100 badge image set.
 *
 * Each badge is a complete SVG medal (256x256) with tier-based colors:
 *
 *   1-10     Bronze
 *   11-20    Silver
 *   21-40    Gold
 *   41-60    Emerald
 *   61-80    Sapphire
 *   81-99    Amethyst
 *   100      Legendary
 *
 * Files are stored on the public disk under "badges/level-XXX.svg".
 */
class BadgeImageGenerator
{
    public const MIN_LEVEL = 1;

    public const MAX_LEVEL = 100;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tiers(): array
    {
        return [
            1 => [
                'name' => 'Bronze',
                'min' => 1,
                'max' => 10,
                'outer' => ['#a0662f', '#d29655', '#6b3d1b'],
                'ring' => '#d89957',
                'inner' => ['#5a3016', '#452612', '#2b180c'],
                'text' => '#f7e3c4',
                'accent' => '#e0a15f',
            ],
            2 => [
                'name' => 'Silver',
                'min' => 11,
                'max' => 20,
                'outer' => ['#6b7484', '#c3ccd9', '#414a58'],
                'ring' => '#c9d1dd',
                'inner' => ['#59606d', '#464d59', '#2e333d'],
                'text' => '#eef2f8',
                'accent' => '#cdd7e5',
            ],
            3 => [
                'name' => 'Gold',
                'min' => 21,
                'max' => 40,
                'outer' => ['#9c7118', '#d6a432', '#6b4a0c'],
                'ring' => '#d9a832',
                'inner' => ['#6b4a10', '#563a0b', '#352407'],
                'text' => '#fff2cf',
                'accent' => '#f1bd4c',
            ],
            4 => [
                'name' => 'Emerald',
                'min' => 41,
                'max' => 60,
                'outer' => ['#1d6f4a', '#43a06f', '#0d4a30'],
                'ring' => '#42a26e',
                'inner' => ['#165237', '#10422c', '#0a2b1d'],
                'text' => '#d8f6e6',
                'accent' => '#62c28c',
            ],
            5 => [
                'name' => 'Sapphire',
                'min' => 61,
                'max' => 80,
                'outer' => ['#1c4a8f', '#2e76c7', '#0e2c61'],
                'ring' => '#2f78ca',
                'inner' => ['#17376b', '#122d57', '#091b39'],
                'text' => '#dceaff',
                'accent' => '#5c9be0',
            ],
            6 => [
                'name' => 'Amethyst',
                'min' => 81,
                'max' => 99,
                'outer' => ['#6b3290', '#9453ad', '#3c1d62'],
                'ring' => '#9454ad',
                'inner' => ['#4a246b', '#3d1b58', '#251036'],
                'text' => '#f1e2ff',
                'accent' => '#a96ec4',
            ],
            7 => [
                'name' => 'Legendary',
                'min' => 100,
                'max' => 100,
                'outer' => ['#7c1600', '#f07a24', '#4a1000'],
                'ring' => '#f4a02c',
                'inner' => ['#4a1500', '#3e1000', '#210a00'],
                'text' => '#fff2cf',
                'accent' => '#ffb648',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function tierFor(int $level): array
    {
        $tier = array_values(array_filter(
            static::tiers(),
            fn (array $tier): bool => $level >= $tier['min'] && $level <= $tier['max']
        ));

        return $tier[0] ?? array_values(static::tiers())[0];
    }

    public static function filenameFor(int $level): string
    {
        return sprintf('badges/level-%03d.svg', $level);
    }

    public static function render(int $level): string
    {
        $tier = static::tierFor($level);
        $outer = $tier['outer'];
        $inner = $tier['inner'];
        $accent = $tier['accent'];
        $text = $tier['text'];
        $tierName = $tier['name'];
        $isFinal = $level === static::MAX_LEVEL;
        $isMilestone = in_array($level, [10, 25, 50, 75, 100], true);

        // 16-point star burst behind the number.
        $starPoints = [];
        for ($index = 0; $index < 16; $index++) {
            $radius = $index % 2 === 0 ? 78 : 58;
            $angle = $index * M_PI / 8 - M_PI / 2;
            $starPoints[] = sprintf(
                '%.1f,%.1f',
                128 + $radius * cos($angle),
                128 + $radius * sin($angle)
            );
        }

        // 24 dots around the outer rim for a crafted medal look.
        $rimDots = [];
        for ($index = 0; $index < 24; $index++) {
            $angle = $index * 2 * M_PI / 24;
            $rimDots[] = sprintf(
                '<circle cx="%.1f" cy="%.1f" r="2.2" fill="%s" opacity="0.6"/>',
                128 + 112 * cos($angle),
                128 + 112 * sin($angle),
                $accent
            );
        }

        $milestoneBadge = '';
        if ($isFinal) {
            $milestoneBadge = '<g opacity="0.95">'
                .sprintf(
                    '<path d="M97 92 L112 79 L128 92 L144 79 L159 92 L154 109 L102 109 Z" fill="%s"/>',
                    $accent
                )
                .'</g>';
        } elseif ($isMilestone) {
            $milestoneBadge = sprintf(
                '<g opacity="0.95"><path d="M119 88 L128 80 L137 88 L133 99 L123 99 Z" fill="%s"/></g>',
                $accent
            );
        }

        $fontSize = $level < 10 ? 72 : ($level < 100 ? 64 : 56);
        $levelNumber = (string) $level;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256" role="img" aria-label="'.'Level '.$level.' badge'.'">'
            .'<defs>'
            .sprintf(
                '<linearGradient id="outer" x1="0%%" y1="0%%" x2="100%%" y2="100%%">'
                .'<stop offset="0%%" stop-color="%s"/>'
                .'<stop offset="55%%" stop-color="%s"/>'
                .'<stop offset="100%%" stop-color="%s"/>'
                .'</linearGradient>',
                $outer[0],
                $outer[1],
                $outer[2]
            )
            .sprintf(
                '<radialGradient id="inner" cx="50%%" cy="38%%" r="74%%">'
                .'<stop offset="0%%" stop-color="%s"/>'
                .'<stop offset="58%%" stop-color="%s"/>'
                .'<stop offset="100%%" stop-color="%s"/>'
                .'</radialGradient>',
                $inner[0],
                $inner[1],
                $inner[2]
            )
            .sprintf(
                '<linearGradient id="ring" x1="0%%" y1="0%%" x2="100%%" y2="100%%">'
                .'<stop offset="0%%" stop-color="%s"/>'
                .'<stop offset="50%%" stop-color="%s"/>'
                .'<stop offset="100%%" stop-color="%s"/>'
                .'</linearGradient>',
                $accent,
                $text,
                $accent
            )
            .sprintf(
                '<radialGradient id="sheen" cx="50%%" cy="50%%" r="50%%">'
                .'<stop offset="0%%" stop-color="#ffffff" stop-opacity="0.9"/>'
                .'<stop offset="100%%" stop-color="#ffffff" stop-opacity="0"/>'
                .'</radialGradient>'
            )
            .'</defs>'

            // Outer disc and rim.
            .'<circle cx="128" cy="128" r="118" fill="url(#outer)" stroke="#ffffff" stroke-width="3" stroke-opacity="0.18"/>'
            .'<circle cx="128" cy="128" r="108" fill="none" stroke="'.$accent.'" stroke-width="2" stroke-opacity="0.55"/>'
            .implode('', $rimDots)

            // Inner medal face.
            .'<circle cx="128" cy="128" r="93" fill="url(#inner)" stroke="url(#ring)" stroke-width="5"/>'
            .'<circle cx="128" cy="128" r="84" fill="none" stroke="'.$accent.'" stroke-width="1.2" stroke-opacity="0.55"/>'
            .'<polygon points="'.implode(' ', $starPoints).'" fill="'.$accent.'" opacity="0.20"/>'

            // Milestone crown / gem.
            .$milestoneBadge

            // Glass sheen.
            .'<ellipse cx="128" cy="72" rx="72" ry="38" fill="url(#sheen)" opacity="0.20"/>'

            // Text.
            .sprintf(
                '<text x="128" y="90" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="4">LEVEL</text>',
                $text
            )
            .sprintf(
                '<text x="128" y="158" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="%d" font-weight="800">%s</text>',
                $text,
                $fontSize,
                $levelNumber
            )
            .sprintf(
                '<text x="128" y="190" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="13" font-weight="700" letter-spacing="2">%s</text>',
                $accent,
                strtoupper($tierName)
            )
            .'</svg>';
    }

    public static function generateForLevel(int $level): string
    {
        $path = static::filenameFor($level);
        Storage::disk('public')->put($path, static::render($level));

        return $path;
    }

    /**
     * Generate every level badge. Returns the number of files written.
     *
     * @param  callable(int $level, int $count):void|null  $onEach
     */
    public static function generateAll(?callable $onEach = null): int
    {
        $count = 0;

        foreach (range(static::MIN_LEVEL, static::MAX_LEVEL) as $level) {
            static::generateForLevel($level);
            $count++;

            if ($onEach !== null) {
                $onEach($level, $count);
            }
        }

        return $count;
    }
}
