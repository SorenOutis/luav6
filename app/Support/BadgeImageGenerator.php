<?php

namespace App\Support;

/**
 * Generates the revamped level 1-100 badge image set.
 *
 * Assets ship with the application under public/images/badges so they never
 * depend on uploaded storage or an external bucket:
 *
 *   public/images/badges/level-001.svg ... level-100.svg
 *
 * Tiers:
 *
 *   1-10     Bronze
 *   11-20    Silver
 *   21-40    Gold
 *   41-60    Emerald
 *   61-80    Sapphire
 *   81-99    Amethyst
 *   100      Legendary
 *
 * Each badge is a faceted medallion with a tier icon, a star burst, a glass
 * sheen, milestone gems at 10/25/50/75/100, and the tier pill at the bottom.
 */
class BadgeImageGenerator
{
    public const MIN_LEVEL = 1;

    public const MAX_LEVEL = 100;

    /**
     * Alternate design directions used by the preview gallery.
     *
     * @return array<string, array{label: string, note: string}>
     */
    public static function styles(): array
    {
        return [
            'medallion' => [
                'label' => 'Medallion',
                'note' => 'Faceted round medal with tier icon, star burst and gem milestones.',
            ],
            'shield' => [
                'label' => 'Shield',
                'note' => 'Heraldic shield silhouette for a competitive-achievement feel.',
            ],
            'hex' => [
                'label' => 'Hex',
                'note' => 'Hexagonal gemstone token with a tech/game-achievement vibe.',
            ],
            'orb' => [
                'label' => 'Orb',
                'note' => 'Glassy energy orb with a soft three-dimensional highlight.',
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
        $isFinal = $level === static::MAX_LEVEL;
        $isMilestone = in_array($level, [10, 25, 50, 75, 100], true);

        $facets = [];
        for ($index = 0; $index < 24; $index++) {
            $a0 = ($index * 15 - 7.5) * M_PI / 180 - M_PI / 2;
            $a1 = ($index * 15 + 7.5) * M_PI / 180 - M_PI / 2;
            $outerPoints = [];
            $innerPoints = [];
            foreach ([$a0, $a1] as $angle) {
                $outerPoints[] = [128 + 125 * cos($angle), 128 + 125 * sin($angle)];
                $innerPoints[] = [128 + 112 * cos($angle), 128 + 112 * sin($angle)];
            }

            $facets[] = sprintf(
                '<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="%s" opacity="0.9"/>',
                $outerPoints[0][0],
                $outerPoints[0][1],
                $outerPoints[1][0],
                $outerPoints[1][1],
                $innerPoints[1][0],
                $innerPoints[1][1],
                $innerPoints[0][0],
                $innerPoints[0][1],
                $index % 2 === 0 ? $outer[0] : $outer[2]
            );
        }

        $icon = static::tierIcon($tierName, $accent, $text);

        $milestone = '';
        if ($isFinal) {
            $milestone = sprintf(
                '<g opacity="0.95"><path d="M104 57 L114 44 L128 57 L142 44 L152 57 L147 72 L109 72 Z" fill="%s" stroke="%s" stroke-width="1.4" stroke-opacity="0.6"/></g>',
                $accent,
                $text
            );
        } elseif ($isMilestone) {
            $milestone = static::diamond(128, 57, 8, $accent, $text, 0.95);
        }

        $fontSize = $level < 10 ? 72 : ($level < 100 ? 62 : 54);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256" role="img" aria-label="Level '.$level.' badge"><defs>'
            .'<linearGradient id="o" x1="0%" y1="0%" x2="100%" y2="100%">'
            .sprintf('<stop offset="0%%" stop-color="%s"/><stop offset="52%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/>', $outer[0], $outer[1], $outer[2])
            .'</linearGradient>'
            .sprintf(
                '<radialGradient id="i" cx="50%%" cy="38%%" r="75%%"><stop offset="0%%" stop-color="%s"/><stop offset="58%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></radialGradient>',
                $inner[0],
                $inner[1],
                $inner[2]
            )
            .sprintf(
                '<linearGradient id="r" x1="0%%" y1="0%%" x2="100%%" y2="100%%"><stop offset="0%%" stop-color="%s"/><stop offset="50%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></linearGradient>',
                $accent,
                $text,
                $accent
            )
            .'<radialGradient id="s" cx="50%" cy="48%" r="55%"><stop offset="0%" stop-color="#ffffff" stop-opacity="0.5"/><stop offset="100%" stop-color="#ffffff" stop-opacity="0"/></radialGradient>'
            .'</defs>'
            .implode('', $facets)
            .'<circle cx="128" cy="128" r="112" fill="url(#o)" stroke="#ffffff" stroke-width="2" stroke-opacity="0.16"/>'
            .'<circle cx="128" cy="128" r="104" fill="url(#i)" stroke="url(#r)" stroke-width="5"/>'
            .'<circle cx="128" cy="128" r="96" fill="none" stroke="'.$accent.'" stroke-width="1.4" stroke-opacity="0.65"/>'
            .static::starPolygon(128, 128, 8, 88, 68, $accent, 0.16)
            .static::rimDots(128, 128, 100, 16, $accent)
            .$milestone
            .$icon
            .'<ellipse cx="128" cy="66" rx="70" ry="35" fill="url(#s)" opacity="0.55"/>'
            .sprintf('<text x="128" y="105" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="12" font-weight="700" letter-spacing="4" opacity="0.92">LEVEL</text>', $text)
            .sprintf('<text x="128" y="168" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="%d" font-weight="800">%d</text>', $text, $fontSize, $level)
            .'<rect x="76" y="182" width="104" height="19" rx="9.5" fill="'.$inner[2].'" stroke="'.$accent.'" stroke-width="1.4" opacity="0.92"/>'
            .sprintf('<text x="128" y="197" text-anchor="middle" fill="%s" font-family="Inter, Arial, sans-serif" font-size="12" font-weight="800" letter-spacing="2.2">%s</text>', $accent, strtoupper($tierName))
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

    private static function starPolygon(float $centerX, float $centerY, int $points, float $outerRadius, float $innerRadius, string $fill, float $opacity): string
    {
        $coordinates = [];

        for ($index = 0; $index < $points * 2; $index++) {
            $radius = $index % 2 === 0 ? $outerRadius : $innerRadius;
            $angle = $index * (180 / $points) * M_PI / 180 - M_PI / 2;
            $coordinates[] = sprintf(
                '%.1f,%.1f',
                $centerX + $radius * cos($angle),
                $centerY + $radius * sin($angle)
            );
        }

        return sprintf(
            '<polygon points="%s" fill="%s" opacity="%s"/>',
            implode(' ', $coordinates),
            $fill,
            $opacity
        );
    }

    private static function rimDots(float $centerX, float $centerY, float $radius, int $count, string $color): string
    {
        $dots = [];

        for ($index = 0; $index < $count; $index++) {
            $angle = $index * (360 / $count) * M_PI / 180 - M_PI / 2;
            $dots[] = sprintf(
                '<circle cx="%.1f" cy="%.1f" r="2" fill="%s" opacity="0.7"/>',
                $centerX + $radius * cos($angle),
                $centerY + $radius * sin($angle),
                $color
            );
        }

        return implode('', $dots);
    }

    private static function diamond(float $centerX, float $centerY, float $size, string $fill, string $stroke, float $opacity): string
    {
        return sprintf(
            '<polygon points="%.1f,%.1f %.1f,%.1f %.1f,%.1f %.1f,%.1f" fill="%s" stroke="%s" stroke-width="1.5" opacity="%s"/>',
            $centerX - $size,
            $centerY,
            $centerX,
            $centerY - $size,
            $centerX + $size,
            $centerY,
            $centerX,
            $centerY + $size,
            $fill,
            $stroke,
            $opacity
        );
    }

    private static function tierIcon(string $tierName, string $accent, string $text): string
    {
        if ($tierName === 'Bronze') {
            return '<path d="M128 66 C 117 78 124 83 120 90 C 113 96 119 106 128 105 C 138 106 144 96 137 90 C 132 85 140 82 128 66 Z" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.5" stroke-opacity="0.5"/>';
        }

        if ($tierName === 'Silver') {
            return '<polygon points="128,64 133,78 147,82 133,86 128,100 123,86 109,82 123,78" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.5" stroke-opacity="0.5"/>';
        }

        if ($tierName === 'Gold') {
            return '<path d="M113 78 L119 70 L128 80 L137 70 L143 78 L140 92 L116 92 Z" fill="'.$accent.'" stroke="'.$text.'" stroke-width="1.3" stroke-opacity="0.55"/>';
        }

        if ($tierName === 'Emerald') {
            return '<g><path d="M132 66 C 120 77 121 91 130 99 C 142 91 147 77 132 66 Z" fill="'.$accent.'"/><path d="M129 69 Q123 84 130 99" fill="none" stroke="'.$text.'" stroke-width="1.6" stroke-opacity="0.65"/></g>';
        }

        if ($tierName === 'Sapphire') {
            return '<g><polygon points="128,65 139,77 128,100 117,77" fill="'.$accent.'"/><path d="M117 77 L139 77" fill="none" stroke="'.$text.'" stroke-width="1.6" stroke-opacity="0.65"/></g>';
        }

        if ($tierName === 'Amethyst') {
            return '<g><polygon points="121,67 135,67 141,84 128,99 115,84" fill="'.$accent.'"/><path d="M121 75 L135 75" fill="none" stroke="'.$text.'" stroke-width="1.5" stroke-opacity="0.6"/></g>';
        }

        return '<g><circle cx="128" cy="83" r="11" fill="'.$accent.'" stroke="'.$text.'" stroke-width="2" stroke-opacity="0.7"/></g>';
    }
}
