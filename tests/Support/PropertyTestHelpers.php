<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Provides random data generation methods for property-based testing via PHPUnit data providers.
 *
 * Each method generates random valid inputs to achieve 100+ iteration coverage
 * when used in data providers.
 */
trait PropertyTestHelpers
{
    /**
     * Return a random supported locale.
     */
    protected static function randomLocale(): string
    {
        return ['ar', 'en'][random_int(0, 1)];
    }

    /**
     * Generate a random valid slug path with 1–3 segments.
     */
    protected static function randomSlugPath(): string
    {
        $segmentCount = random_int(1, 3);
        $segments = [];

        for ($i = 0; $i < $segmentCount; $i++) {
            $segments[] = self::randomSlugSegment();
        }

        return implode('/', $segments);
    }

    /**
     * Generate a single random slug segment (lowercase alpha-numeric with hyphens).
     */
    protected static function randomSlugSegment(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $length = random_int(3, 12);
        $slug = '';

        for ($i = 0; $i < $length; $i++) {
            $slug .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Optionally insert a hyphen in the middle
        if ($length > 5 && random_int(0, 1) === 1) {
            $pos = random_int(2, $length - 2);
            $slug = substr($slug, 0, $pos).'-'.substr($slug, $pos);
        }

        return $slug;
    }

    /**
     * Generate random SEO field combinations with nullable fields.
     *
     * @return array{meta_title: ?string, meta_description: ?string, og_title: ?string, og_description: ?string, og_image: ?string, canonical_url: ?string, robots: ?string}
     */
    protected static function randomSeoFields(): array
    {
        return [
            'meta_title' => random_int(0, 3) > 0 ? self::randomSentence() : null,
            'meta_description' => random_int(0, 2) > 0 ? self::randomSentence() : null,
            'og_title' => random_int(0, 2) > 0 ? self::randomSentence() : null,
            'og_description' => random_int(0, 2) > 0 ? self::randomSentence() : null,
            'og_image' => random_int(0, 2) > 0 ? 'https://example.com/images/'.self::randomSlugSegment().'.jpg' : null,
            'canonical_url' => random_int(0, 2) > 0 ? null : 'https://example.com/'.self::randomSlugPath(),
            'robots' => random_int(0, 3) > 0 ? null : self::randomRobotsDirective(),
        ];
    }

    /**
     * Generate random redirect rules (exact and pattern).
     *
     * @return array{exact: list<array{legacy_path: string, destination_url: string, status_code: int, is_active: bool}>, pattern: list<array{pattern: string, replacement: string, status_code: int, priority: int, is_active: bool}>}
     */
    protected static function randomRedirectRules(): array
    {
        $exactCount = random_int(1, 5);
        $patternCount = random_int(0, 3);

        $exact = [];
        for ($i = 0; $i < $exactCount; $i++) {
            $exact[] = [
                'legacy_path' => '/'.self::randomSlugPath(),
                'destination_url' => '/'.self::randomLocale().'/'.self::randomSlugPath(),
                'status_code' => [301, 302][random_int(0, 1)],
                'is_active' => random_int(0, 4) > 0,
            ];
        }

        $pattern = [];
        for ($i = 0; $i < $patternCount; $i++) {
            $segment = self::randomSlugSegment();
            $pattern[] = [
                'pattern' => '#^/'.$segment.'/(.+)$#',
                'replacement' => '/'.self::randomLocale().'/'.$segment.'/$1',
                'status_code' => 301,
                'priority' => ($i + 1) * 100,
                'is_active' => random_int(0, 4) > 0,
            ];
        }

        return ['exact' => $exact, 'pattern' => $pattern];
    }

    /**
     * Generate a random page collection with mixed statuses for sitemap testing.
     *
     * @return list<array{slug: string, type: string, template: string, status: string, is_enabled: bool, is_homepage_shell: bool, published_at: ?string, locales: list<string>}>
     */
    protected static function randomPageCollection(): array
    {
        $count = random_int(3, 8);
        $pages = [];
        $statuses = ['draft', 'published', 'scheduled'];

        for ($i = 0; $i < $count; $i++) {
            $status = $statuses[random_int(0, count($statuses) - 1)];
            $isEnabled = random_int(0, 3) > 0;
            $publishedAt = ($status === 'published' && random_int(0, 4) > 0)
                ? now()->subDays(random_int(1, 30))->toDateTimeString()
                : null;

            // Randomly decide which locales have translations
            $locales = match (random_int(0, 2)) {
                0 => ['ar'],
                1 => ['en'],
                2 => ['ar', 'en'],
            };

            $pages[] = [
                'slug' => self::randomSlugSegment().'-'.$i,
                'type' => 'landing',
                'template' => 'default',
                'status' => $status,
                'is_enabled' => $isEnabled,
                'is_homepage_shell' => false,
                'published_at' => $publishedAt,
                'locales' => $locales,
            ];
        }

        return $pages;
    }

    /**
     * Generate a random short sentence for SEO field values.
     */
    protected static function randomSentence(): string
    {
        $words = ['university', 'education', 'research', 'faculty', 'campus', 'student', 'program', 'academic', 'science', 'technology', 'engineering', 'medicine', 'pharmacy', 'arts', 'business'];
        $count = random_int(3, 8);
        $sentence = [];

        for ($i = 0; $i < $count; $i++) {
            $sentence[] = $words[random_int(0, count($words) - 1)];
        }

        return ucfirst(implode(' ', $sentence));
    }

    /**
     * Generate a random robots directive.
     */
    protected static function randomRobotsDirective(): string
    {
        $directives = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];

        return $directives[random_int(0, count($directives) - 1)];
    }
}
