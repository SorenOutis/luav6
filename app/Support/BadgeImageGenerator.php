<?php

namespace App\Support;

/**
 * Generates the friendly Hex level 1-100 badge image set (no companion).
 *
 * Assets ship with the application under public/images/badges so they never
 * depend on uploaded storage or an external bucket:
 *
 *   public/images/badges/level-001.svg ... level-100.svg
 *
 * Design:
 *   - Rounded hexagonal achievement token with a clean, approachable look.
 *   - Soft pastel tier gradients, a small shining highlight, corner sparkles
 *     and a simple tier icon (flame, star, crown, leaf, crystal, gem, sun).
 *   - Milestone sparkle at 10/25/50/75/100 and a double sparkle at Level 100.
 *
 * Tiers:
 *   1-10     Bronze
 *   11-20    Silver
 *   21-40    Gold
 *   41-60    Emerald
 *   61-80    Sapphire
 *   81-99    Amethyst
 *   100      Legendary
 */
class BadgeImageGenerator
{
    public const MIN_LEVEL = 1;

    public const MAX_LEVEL = 100;

    /**
     * @return array<string, array{label: string, note: string}>
     */
    public static function styles(): array
    {
        return [
            'hex' => [
                'label' => 'Hex',
                'note' => 'Friendly rounded hexagon achievement token without a companion.',
            ],
            'shield' => [
                'label' => 'Shield',
                'note' => 'Heraldic shield silhouette, available as an alternate direction.',
            ],
            'orb' => [
                'label' => 'Orb',
                'note' => 'Glassy energy orb, available as an alternate direction.',
            ],
        ];
    }

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
                'outer' => ['#f2a967', '#f8cf9a', '#d5824a'],
                'ring' => '#ffe0ba',
                'inner' => ['#fffaf1', '#ffe9cc', '#ffe9cc'],
                'text' => '#8c4f28',
                'accent' => '#e98f4b',
                'icon' => 'flame',
            ],
            2 => [
                'name' => 'Silver',
                'min' => 11,
                'max' => 20,
                'outer' => ['#bdc9d6', '#e6edf5', '#8c99a9'],
                'ring' => '#eef3f9',
                'inner' => ['#f5f9fd', '#e5edf5', '#e5edf5'],
                'text' => '#54616f',
                'accent' => '#9faec0',
                'icon' => 'star',
            ],
            3 => [
                'name' => 'Gold',
                'min' => 21,
                'max' => 40,
                'outer' => ['#f0b94e', '#fbd982', '#c78e24'],
                'ring' => '#ffe9ad',
                'inner' => ['#fff8e3', '#ffe9b8', '#ffe9b8'],
                'text' => '#8a5a10',
                'accent' => '#dca62e',
                'icon' => 'crown',
            ],
            4 => [
                'name' => 'Emerald',
                'min' => 41,
                'max' => 60,
                'outer' => ['#6cbd90', '#a3ddbb', '#3f9468'],
                'ring' => '#d6f5e3',
                'inner' => ['#edfaf1', '#d8f1e1', '#d8f1e1'],
                'text' => '#255f43',
                'accent' => '#57ab7c',
                'icon' => 'leaf',
            ],
            5 => [
                'name' => 'Sapphire',
                'min' => 61,
                'max' => 80,
                'outer' => ['#6ea4d8', '#a9cbec', '#3f7bb2'],
                'ring' => '#dcecfa',
                'inner' => ['#eef7fd', '#dbecf7', '#dbecf7'],
                'text' => '#274d6e',
                'accent' => '#5b93c6',
                'icon' => 'crystal',
            ],
            6 => [
                'name' => 'Amethyst',
                'min' => 81,
                'max' => 99,
                'outer' => ['#a887d8', '#cbb3ee', '#7a5cb2'],
                'ring' => '#e7dbf7',
                'inner' => ['#f5f0fc', '#e8dcf4', '#e8dcf4'],
                'text' => '#523975',
                'accent' => '#8f72c1',
                'icon' => 'gem',
            ],
            7 => [
                'name' => 'Legendary',
                'min' => 100,
                'max' => 100,
                'outer' => ['#ee7d4d', '#ffbd89', '#c9502a'],
                'ring' => '#ffdfbb',
                'inner' => ['#fff3e3', '#ffe0bd', '#ffe0bd'],
                'text' => '#7c2c13',
                'accent' => '#ef8b4f',
                'icon' => 'sun',
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

    /**
     * The path stored on badge records and resolved through PublicFileUrl.
     */
    public static function filenameFor(int $level): string
    {
        return sprintf('images/badges/level-%03d.svg', $level);
    }

    /**
     * The absolute filesystem path used when generating the asset.
     */
    public static function diskPathFor(int $level): string
    {
        return public_path('images/badges/'.sprintf('level-%03d.svg', $level));
    }

    public static function render(int $level): string
    {
        $tier = static::tierFor($level);
        $outer = $tier['outer'];
        $inner = $tier['inner'];
        $accent = $tier['accent'];
        $text = $tier['text'];
        $tierName = $tier['name'];
        $icon = $tier['icon'];
        $isFinal = $level === static::MAX_LEVEL;
        $isMilestone = in_array($level, [10, 25, 50, 75, 100], true);

        $fontSize = $level < 10 ? 76 : ($level < 100 ? 68 : 62);

        $milestone = '';
        if ($isFinal) {
            $milestone = static::sparkle(201, 158, 10, $accent, 0.95)
                .static::sparkle(55, 158, 10, $accent, 0.95);
        } elseif ($isMilestone) {
            $milestone = static::sparkle(201, 158, 9, $accent, 0.95);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256" role="img" aria-label="Level '.$level.' badge"><defs>'
            .'<linearGradient id="o" x1="0%" y1="0%" x2="100%" y2="100%">'
            .sprintf('<stop offset="0%%" stop-color="%s"/><stop offset="55%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/>', $outer[0], $outer[1], $outer[2])
            .'</linearGradient>'
            .sprintf(
                '<linearGradient id="i" x1="0%%" y1="0%%" x2="0%%" y2="100%%"><stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></linearGradient>',
                $inner[0],
                $inner[1]
            )
            .'<radialGradient id="sheen" cx="50%" cy="26%" r="36%"><stop offset="0%" stop-color="#ffffff" stop-opacity="0.85"/><stop offset="100%" stop-color="#ffffff" stop-opacity="0"/></radialGradient>'
            .'<filter id="soft" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="5" stdDeviation="5" flood-color="#000000" flood-opacity="0.16"/></filter>'
            .'</defs>'

            .'<g filter="url(#soft)">'
            .static::roundedHex(128, 128, 118, 10, 'url(#o)', $outer[2])
            .static::roundedHex(128, 128, 102, 6, 'url(#i)', '#ffffff')
            .static::roundedHex(128, 128, 94, 2, 'none', $accent)
            .'<path d="M80 52 L126 82 L176 56 L176 121 Q128 98 80 121 Z" fill="url(#sheen)" opacity="0.5"/>'
            .static::tierIcon($icon, $accent, $text)
            .static::sparkle(52, 52, 7, '#ffffff', 0.95)
            .static::sparkle(204, 57, 5, '#ffffff', 0.95)
            .static::sparkle(58, 190, 5, '#ffffff', 0.95)
            .static::sparkle(200, 194, 7, '#ffffff', 0.95)
            .'</g>'

            .sprintf(
                '<text x="128" y="173" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="%d" font-weight="800" stroke="%s" stroke-width="1.5" paint-order="stroke">%d</text>',
                $text,
                $fontSize,
                $inner[0],
                $level
            )
            .'<rect x="70" y="188" width="116" height="21" rx="10.5" fill="'.$inner[0].'" stroke="'.$accent.'" stroke-width="1.5"/>'
            .sprintf(
                '<text x="128" y="205" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="12" font-weight="800" letter-spacing="2.1">%s</text>',
                $accent,
                strtoupper($tierName)
            )
            .$milestone
            .'</svg>';
    }

    public static function generateForLevel(int $level): string
    {
        file_put_contents(static::diskPathFor($level), static::render($level));

        return static::filenameFor($level);
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

    private static function roundedHex(float $centerX, float $centerY, float $radius, int $strokeWidth, string $fill, string $stroke): string
    {
        $coordinates = [];

        for ($index = 0; $index < 6; $index++) {
            $angle = ($index * 60 - 90) * M_PI / 180;
            $coordinates[] = sprintf(
                '%.1f,%.1f',
                $centerX + $radius * cos($angle),
                $centerY + $radius * sin($angle)
            );
        }

        return sprintf(
            '<polygon points="%s" fill="%s" stroke="%s" stroke-width="%d" stroke-linejoin="round"/>',
            implode(' ', $coordinates),
            $fill,
            $stroke,
            $strokeWidth
        );
    }

    private static function sparkle(float $x, float $y, float $size, string $color, float $opacity): string
    {
        $k = $size * 0.24;

        return sprintf(
            '<path d="M%.1f %.1f L%.1f %.1f L%.1f %.1f L%.1f %.1f L%.1f %.1f L%.1f %.1f L%.1f %.1f L%.1f %.1f Z" fill="%s" opacity="%s"/>',
            $x,
            $y - $size,
            $x + $k,
            $y - $k,
            $x + $size,
            $y,
            $x + $k,
            $y + $k,
            $x,
            $y + $size,
            $x - $k,
            $y + $k,
            $x - $size,
            $y,
            $x - $k,
            $y - $k,
            $color,
            $opacity
        );
    }

    private static function star(float $x, float $y, float $radius, string $color, string $stroke): string
    {
        $coordinates = [];

        for ($index = 0; $index < 10; $index++) {
            $r = $index % 2 === 0 ? $radius : $radius * 0.42;
            $angle = ($index * 36 - 90) * M_PI / 180;
            $coordinates[] = sprintf(
                '%.1f,%.1f',
                $x + $r * cos($angle),
                $y + $r * sin($angle)
            );
        }

        return sprintf(
            '<polygon points="%s" fill="%s" stroke="%s" stroke-width="1.6" stroke-linejoin="round"/>',
            implode(' ', $coordinates),
            $color,
            $stroke
        );
    }

    private static function tierIcon(string $icon, string $accent, string $text): string
    {
        if ($icon === 'flame') {
            return '<path d="M128 46 C 118 55 114 63 116 70 C 112 78 118 86 128 85 C 138 86 144 78 140 70 C 142 63 138 55 128 46 Z" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.6" stroke-linejoin="round"/>'
                .'<path d="M128 57 C 123 62 122 68 125 73 C 127 76 129 76 131 73 C 134 68 133 62 128 57 Z" fill="#ffffff" opacity="0.75"/>';
        }

        if ($icon === 'star') {
            return static::star(128, 66, 20, $accent, $text);
        }

        if ($icon === 'crown') {
            return '<path d="M112 54 L119 45 L128 55 L137 45 L144 54 L141 72 L115 72 Z" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.6" stroke-linejoin="round"/>'
                .'<circle cx="112" cy="54" r="3.2" fill="'.$text.'"/>'
                .'<circle cx="128" cy="45" r="3.2" fill="'.$text.'"/>'
                .'<circle cx="144" cy="54" r="3.2" fill="'.$text.'"/>';
        }

        if ($icon === 'leaf') {
            return '<path d="M128 47 C 116 56 114 72 126 84 C 142 75 146 56 128 47 Z" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.6" stroke-linejoin="round"/>'
                .'<path d="M121 62 Q128 72 132 82" fill="none" stroke="#ffffff" stroke-width="1.8" stroke-opacity="0.8" stroke-linecap="round"/>';
        }

        if ($icon === 'crystal') {
            return '<polygon points="128,45 143,62 128,86 113,62" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.6" stroke-linejoin="round"/>'
                .'<path d="M113 62 L143 62 M128 45 L128 86" fill="none" stroke="#ffffff" stroke-width="1.2" stroke-opacity="0.7"/>';
        }

        if ($icon === 'gem') {
            return '<polygon points="128,44 150,62 139,86 117,86 106,62" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.6" stroke-linejoin="round"/>'
                .'<path d="M117 60 L139 60 M122 72 L134 72" fill="none" stroke="#ffffff" stroke-width="1.4" stroke-opacity="0.7"/>';
        }

        $rays = '';
        for ($index = 0; $index < 12; $index++) {
            $angle = $index * 30 * M_PI / 180;
            $rays .= sprintf(
                '<path d="M%.1f %.1f L%.1f %.1f" stroke="%s" stroke-width="2.6" stroke-linecap="round"/>',
                128 + 15 * cos($angle),
                65 + 15 * sin($angle),
                128 + 24 * cos($angle),
                65 + 24 * sin($angle),
                $accent
            );
        }

        return '<circle cx="128" cy="65" r="13" fill="'.$accent.'" stroke="'.$text.'" stroke-width="2"/>'.$rays;
    }
}
