<?php

namespace App\Support;

/**
 * Generates the friendly Echo-hex level 1-100 badge image set.
 *
 * Assets ship with the application under public/images/badges so they never
 * depend on uploaded storage or an external bucket:
 *
 *   public/images/badges/level-001.svg ... level-100.svg
 *
 * Design:
 *   - Rounded hexagonal "token" frame with soft, approachable tier colors.
 *   - Echo, the fox companion, is the friendly face on every badge.
 *   - A clean level number, tier pill, milestone sparkles, and a crown at
 *     Level 100.
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
            'echo-hex' => [
                'label' => 'Echo Hex',
                'note' => 'Friendly rounded hexagon featuring Echo the fox companion.',
            ],
            'hex' => [
                'label' => 'Hex',
                'note' => 'Clean hexagonal gemstone token without the companion.',
            ],
            'medallion' => [
                'label' => 'Medallion',
                'note' => 'Classic faceted round medal.',
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
                'inner' => ['#fff4e4', '#ffe6c8', '#ffe6c8'],
                'text' => '#8c4f28',
                'accent' => '#e98f4b',
            ],
            2 => [
                'name' => 'Silver',
                'min' => 11,
                'max' => 20,
                'outer' => ['#bdc9d6', '#e6edf5', '#8c99a9'],
                'ring' => '#eef3f9',
                'inner' => ['#f6f9fc', '#e6edf4', '#e6edf4'],
                'text' => '#54616f',
                'accent' => '#9faec0',
            ],
            3 => [
                'name' => 'Gold',
                'min' => 21,
                'max' => 40,
                'outer' => ['#f0b94e', '#fbd982', '#c78e24'],
                'ring' => '#ffe9ad',
                'inner' => ['#fff7e0', '#ffe9b8', '#ffe9b8'],
                'text' => '#8a5a10',
                'accent' => '#dca62e',
            ],
            4 => [
                'name' => 'Emerald',
                'min' => 41,
                'max' => 60,
                'outer' => ['#6cbd90', '#a3ddbb', '#3f9468'],
                'ring' => '#d6f5e3',
                'inner' => ['#eefbf2', '#d9f2e2', '#d9f2e2'],
                'text' => '#255f43',
                'accent' => '#57ab7c',
            ],
            5 => [
                'name' => 'Sapphire',
                'min' => 61,
                'max' => 80,
                'outer' => ['#6ea4d8', '#a9cbec', '#3f7bb2'],
                'ring' => '#dcecfa',
                'inner' => ['#eff7fd', '#e0eef9', '#e0eef9'],
                'text' => '#274d6e',
                'accent' => '#5b93c6',
            ],
            6 => [
                'name' => 'Amethyst',
                'min' => 81,
                'max' => 99,
                'outer' => ['#a887d8', '#cbb3ee', '#7a5cb2'],
                'ring' => '#e7dbf7',
                'inner' => ['#f6f0fc', '#e9ddf5', '#e9ddf5'],
                'text' => '#523975',
                'accent' => '#8f72c1',
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
        $ring = $tier['ring'];
        $inner = $tier['inner'];
        $accent = $tier['accent'];
        $text = $tier['text'];
        $tierName = $tier['name'];
        $isFinal = $level === static::MAX_LEVEL;
        $isMilestone = in_array($level, [10, 25, 50, 75, 100], true);

        $fontSize = $level < 10 ? 74 : ($level < 100 ? 66 : 60);

        $milestoneAccents = '';
        if ($isFinal) {
            $milestoneAccents = static::sparkle(56, 150, 9, $accent, 0.95)
                .static::sparkle(198, 150, 9, $accent, 0.95);
        } elseif ($isMilestone) {
            $milestoneAccents = static::sparkle(192, 150, 8, $accent, 0.95);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256" role="img" aria-label="Level '.$level.' badge"><defs>'
            .'<linearGradient id="o" x1="0%" y1="0%" x2="100%" y2="100%">'
            .sprintf('<stop offset="0%%" stop-color="%s"/><stop offset="55%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/>', $outer[0], $outer[1], $outer[2])
            .'</linearGradient>'
            .sprintf(
                '<radialGradient id="i" cx="50%%" cy="34%%" r="80%%"><stop offset="0%%" stop-color="%s"/><stop offset="70%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></radialGradient>',
                $inner[0],
                $inner[1],
                $inner[2]
            )
            .sprintf(
                '<linearGradient id="r" x1="0%%" y1="0%%" x2="100%%" y2="100%%"><stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></linearGradient>',
                $ring,
                $accent
            )
            .'<filter id="soft" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#000000" flood-opacity="0.22"/></filter>'
            .'</defs>'

            .static::roundedHex(128, 128, 6, 120, 14, 'url(#o)', 'url(#o)')
            .static::roundedHex(128, 128, 6, 100, 8, 'url(#i)', 'url(#r)')
            .static::roundedHex(128, 128, 6, 92, 6, 'none', '#ffffff')

            .static::sparkle(52, 52, 8, '#ffffff', 0.9)
            .static::sparkle(204, 62, 6, '#ffffff', 0.9)
            .static::sparkle(62, 188, 6, '#ffffff', 0.9)
            .static::sparkle(196, 192, 8, '#ffffff', 0.9)

            .'<g filter="url(#soft)">'.static::echoFox().'</g>'

            .sprintf(
                '<text x="128" y="168" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="%d" font-weight="800" stroke="%s" stroke-width="1.5" paint-order="stroke">%d</text>',
                $text,
                $fontSize,
                $inner[0],
                $level
            )
            .'<rect x="70" y="185" width="116" height="22" rx="11" fill="'.$inner[0].'" stroke="'.$accent.'" stroke-width="1.6"/>'
            .sprintf(
                '<text x="128" y="201" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="12" font-weight="800" letter-spacing="2.2">%s</text>',
                $accent,
                strtoupper($tierName)
            )
            .$milestoneAccents
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

    private static function roundedHex(float $centerX, float $centerY, int $points, float $radius, int $strokeWidth, string $fill, string $stroke): string
    {
        $coordinates = [];

        for ($index = 0; $index < $points; $index++) {
            $angle = ($index * 360 / $points - 90) * M_PI / 180;
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
        return sprintf(
            '<path d="M%s %s L%s %s L%s %s L%s %s L%s %s L%s %s L%s %s L%s %s Z" fill="%s" opacity="%s"/>',
            $x,
            $y - $size,
            $x + $size * 0.22,
            $y - $size * 0.22,
            $x + $size,
            $y,
            $x + $size * 0.22,
            $y + $size * 0.22,
            $x,
            $y + $size,
            $x - $size * 0.22,
            $y + $size * 0.22,
            $x - $size,
            $y,
            $x - $size * 0.22,
            $y - $size * 0.22,
            $color,
            $opacity
        );
    }

    /**
     * Echo the fox companion - a friendly vector head emblem.
     */
    private static function echoFox(): string
    {
        $outline = '#5b2f26';
        $orange = '#e8915b';
        $cream = '#ffe9d4';
        $pink = '#f5a88f';

        return '<g id="echo">'
            .'<path d="M102 60 L76 30 L114 45 Z" fill="'.$orange.'" stroke="'.$outline.'" stroke-width="3" stroke-linejoin="round"/>'
            .'<path d="M154 60 L180 30 L142 45 Z" fill="'.$orange.'" stroke="'.$outline.'" stroke-width="3" stroke-linejoin="round"/>'
            .'<path d="M96 52 L81 35 L106 47 Z" fill="'.$cream.'"/>'
            .'<path d="M160 52 L175 35 L150 47 Z" fill="'.$cream.'"/>'
            .'<path d="M103 57 Q128 44 153 57 L170 96 Q128 128 86 96 Z" fill="'.$orange.'" stroke="'.$outline.'" stroke-width="3" stroke-linejoin="round"/>'
            .'<path d="M101 96 Q128 118 155 96 L149 109 Q128 130 107 109 Z" fill="'.$cream.'" stroke="'.$outline.'" stroke-width="2.5" stroke-linejoin="round"/>'
            .'<circle cx="110" cy="79" r="4.2" fill="#3c2620"/>'
            .'<circle cx="146" cy="79" r="4.2" fill="#3c2620"/>'
            .'<circle cx="111.6" cy="77.4" r="1.4" fill="#ffffff"/>'
            .'<circle cx="147.6" cy="77.4" r="1.4" fill="#ffffff"/>'
            .'<path d="M123 101 Q128 98 133 101 L131 107 Q128 110 125 107 Z" fill="#3c2620"/>'
            .'<path d="M128 110 Q122 116 116 113 M128 110 Q134 116 140 113" fill="none" stroke="#3c2620" stroke-width="2" stroke-linecap="round"/>'
            .'<ellipse cx="104" cy="92" rx="6" ry="4" fill="'.$pink.'" opacity="0.85"/>'
            .'<ellipse cx="152" cy="92" rx="6" ry="4" fill="'.$pink.'" opacity="0.85"/>'
            .'</g>';
    }
}
