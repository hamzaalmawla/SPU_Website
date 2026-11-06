<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AccessibleColor;
use PHPUnit\Framework\TestCase;

/**
 * Faculty accent colours come from the CMS and are chosen as brand colours, not
 * as text colours. A rendered audit of the faculties hub found three of the
 * seven failing WCAG AA as 11px text on a white card.
 */
final class AccessibleColorTest extends TestCase
{
    /**
     * The three that actually failed on 2 September, plus the requirement they
     * failed. Asserted as a ratio rather than an expected hex so the test says
     * what it cares about — legibility — instead of pinning one implementation.
     */
    public function test_it_darkens_the_accents_that_failed_on_white(): void
    {
        foreach (['#5EBE7B', '#CAA949', '#7F8C8D'] as $accent) {
            $corrected = AccessibleColor::onLight($accent);

            $this->assertNotSame(strtolower($accent), strtolower($corrected), $accent.' should have been darkened.');
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->ratio($corrected, '#ffffff'),
                $accent.' still fails WCAG AA after correction.',
            );
        }
    }

    /** A colour that already passes must be returned untouched, hue and all. */
    public function test_it_leaves_a_colour_that_already_passes_alone(): void
    {
        foreach (['#8a1c1c', '#1e2652', '#000000'] as $accent) {
            $this->assertSame($accent, AccessibleColor::onLight($accent));
        }
    }

    /**
     * The point is a legible colour that still reads as the faculty's own, so
     * the hue must survive. Green must not come back grey or black.
     */
    public function test_it_preserves_the_hue_it_was_given(): void
    {
        $corrected = AccessibleColor::onLight('#5EBE7B');

        [$r, $g, $b] = sscanf($corrected, '#%02x%02x%02x');

        $this->assertGreaterThan($r, $g, 'A green accent must stay green.');
        $this->assertGreaterThan($b, $g, 'A green accent must stay green.');
    }

    public function test_it_accepts_shorthand_and_missing_hashes(): void
    {
        $this->assertSame(
            AccessibleColor::onLight('#5EBE7B'),
            AccessibleColor::onLight('5EBE7B'),
        );
        $this->assertGreaterThanOrEqual(4.5, $this->ratio(AccessibleColor::onLight('#fc0'), '#ffffff'));
    }

    /**
     * A malformed colour is a CMS data problem and must stay visible as one.
     * Silently substituting black would hide it and make the page look correct.
     */
    public function test_it_returns_an_unparseable_value_unchanged(): void
    {
        $this->assertSame('not-a-color', AccessibleColor::onLight('not-a-color'));
        $this->assertSame('', AccessibleColor::onLight(null));
    }

    /** White on white is the worst case and must still resolve to something legible. */
    public function test_it_handles_a_colour_identical_to_the_surface(): void
    {
        $this->assertGreaterThanOrEqual(4.5, $this->ratio(AccessibleColor::onLight('#ffffff'), '#ffffff'));
    }

    private function ratio(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
            $channels = array_map(static function (int $channel): float {
                $c = $channel / 255;

                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            }, [$r, $g, $b]);

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };

        $first = $lum($a);
        $second = $lum($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }
}
