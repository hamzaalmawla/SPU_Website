<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Darkens a colour until it is legible on a light surface.
 *
 * WHY THIS EXISTS
 *
 * Faculty accent colours come from the CMS, and they are chosen to look right as
 * a badge tint or a rule — not to be read as 11px uppercase text. A rendered
 * audit of the faculties hub on 2 September measured three of them as body text
 * on a white card: #5EBE7B at 2.3:1, #CAA949 at 2.26:1 and #7F8C8D at 3.48:1,
 * against the 4.5:1 that WCAG AA asks for at that size.
 *
 * Correcting those three values would have fixed the page and not the problem.
 * The next accent an editor picks is equally likely to fail, nothing would catch
 * it, and the person picking it is choosing a brand colour rather than making a
 * contrast judgement. So the fix belongs where the colour is used as text.
 *
 * The hue is preserved and only lightness is reduced, so a faculty's colour
 * still reads as its own — a green stays green, it just stops being pale.
 */
final class AccessibleColor
{
    /** WCAG AA for normal-sized text. */
    private const TARGET_RATIO = 4.5;

    /**
     * Returns $hex darkened just enough to reach $ratio against $background.
     *
     * Returns the input unchanged when it already passes, and when it cannot be
     * parsed — a malformed colour is the CMS's problem to surface, and silently
     * substituting black here would hide it.
     */
    public static function onLight(?string $hex, string $background = '#ffffff', float $ratio = self::TARGET_RATIO): string
    {
        $foreground = self::parse($hex);
        $surface = self::parse($background);

        if ($foreground === null || $surface === null) {
            return (string) $hex;
        }

        if (self::ratio($foreground, $surface) >= $ratio) {
            return self::format($foreground);
        }

        // Scale toward black in small steps rather than solving analytically:
        // the relationship between a multiplier and the resulting ratio is not
        // linear, and 40 steps of 2.5% resolve it closely enough that no one can
        // see the difference from an exact answer.
        for ($step = 1; $step <= 40; $step++) {
            $factor = 1.0 - ($step * 0.025);
            $candidate = [
                (int) round($foreground[0] * $factor),
                (int) round($foreground[1] * $factor),
                (int) round($foreground[2] * $factor),
            ];

            if (self::ratio($candidate, $surface) >= $ratio) {
                return self::format($candidate);
            }
        }

        return '#000000';
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function parse(?string $hex): ?array
    {
        if (! is_string($hex)) {
            return null;
        }

        $value = ltrim(trim($hex), '#');

        if (strlen($value) === 3) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $value) !== 1) {
            return null;
        }

        return [
            (int) hexdec(substr($value, 0, 2)),
            (int) hexdec(substr($value, 2, 2)),
            (int) hexdec(substr($value, 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function format(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $a
     * @param  array{0: int, 1: int, 2: int}  $b
     */
    private static function ratio(array $a, array $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /**
     * Relative luminance, per WCAG 2.1.
     *
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private static function luminance(array $rgb): float
    {
        $channels = [];

        foreach ($rgb as $channel) {
            $c = max(0, min(255, $channel)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
