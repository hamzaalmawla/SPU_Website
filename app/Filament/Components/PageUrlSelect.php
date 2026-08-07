<?php

declare(strict_types=1);

namespace App\Filament\Components;

use App\Contracts\Seo\SitemapServiceInterface;
use Filament\Forms\Components\Select;

/**
 * Reusable page URL dropdown for Filament forms.
 *
 * Sources its options from SitemapServiceInterface::generateEntries() so every
 * URL in the dropdown is a real published page guaranteed to resolve.
 */
final class PageUrlSelect
{
    public static function make(string $name, string $label, ?string $locale = null, bool $required = false): Select
    {
        $locale = self::resolveLocale($locale);

        return Select::make($name)
            ->label($label)
            ->searchable()
            ->preload()
            ->native(false)
            ->placeholder('Select a page...')
            ->formatStateUsing(fn (mixed $state): mixed => self::normalizeUrl($state, $locale))
            ->dehydrateStateUsing(fn (mixed $state): mixed => self::normalizeUrl($state, $locale))
            ->options(fn (?string $state): array => self::buildOptions($state, $locale))
            ->helperText('Search and choose an internal page. The URL is filled in automatically.')
            ->required($required);
    }

    /**
     * @return array<string, string>
     */
    public static function searchOptions(string $search, ?string $locale = null): array
    {
        $locale = self::resolveLocale($locale);
        $search = mb_strtolower(trim($search));

        if ($search === '') {
            return [];
        }

        $flat = self::flattenGroups(self::buildOptions(null, $locale));

        return collect($flat)
            ->filter(fn (string $label, string $url): bool =>
                str_contains(mb_strtolower($label), $search) || str_contains(mb_strtolower($url), $search)
            )
            ->take(30)
            ->all();
    }

    /**
     * Build the full grouped option map from the sitemap.
     *
     * @return array<string, array<string, string>|string>
     */
    private static function buildOptions(?string $currentValue, ?string $locale): array
    {
        $locale = self::resolveLocale($locale);
        $currentValue = self::normalizeUrl($currentValue, $locale);

        /** @var SitemapServiceInterface $sitemapService */
        $sitemapService = app(SitemapServiceInterface::class);
        $entries = $sitemapService->generateEntries();

        $groups = [];
        foreach ($entries as $entry) {
            $path = parse_url($entry->loc, PHP_URL_PATH);
            if (! is_string($path)) {
                continue;
            }

            // Path is always /{locale}/... — extract locale from second segment
            $parts = explode('/', $path);
            $entryLocale = $parts[1] ?? '';
            if ($entryLocale !== $locale) {
                continue;
            }

            // Strip locale prefix: /ar/campus-life/hospital → /campus-life/hospital
            $relativePath = '/' . implode('/', array_slice($parts, 2));
            $publicPath = '/' . $locale . ($relativePath === '/' ? '' : $relativePath);
            if ($relativePath === '/') {
                $groups['Homepage'][$publicPath] = __('Homepage');
                continue;
            }

            $segments = array_values(array_filter(explode('/', $relativePath)));
            $firstSegment = $segments[0] ?? '';
            $groupName = self::groupName($firstSegment);
            $label = self::buildLabel($segments, $locale);

            $groups[$groupName][$publicPath] = $label;
        }

        ksort($groups);

        // Preserve existing custom/external values at the top
        if ($currentValue !== null && $currentValue !== '') {
            $flat = self::flattenGroups($groups);
            if (! array_key_exists($currentValue, $flat)) {
                $groups = ['Custom / External' => [$currentValue => $currentValue . ' (custom/external link)']] + $groups;
            }
        }

        return $groups;
    }

    private static function normalizeUrl(mixed $value, ?string $locale): mixed
    {
        if (! is_string($value) || trim($value) === '' || $locale === null || $locale === '') {
            return $value;
        }

        $value = trim($value);
        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (is_string($scheme) && $scheme !== '') {
            return $value;
        }

        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $value;
        }

        $path = '/' . ltrim($path, '/');
        if (str_starts_with($path, '/'.$locale.'/') || $path === '/'.$locale) {
            return $path;
        }

        if (str_starts_with($path, '/ar/') || str_starts_with($path, '/en/') || $path === '/ar' || $path === '/en') {
            return $path;
        }

        return '/'.$locale.$path;
    }

    private static function resolveLocale(?string $locale): string
    {
        return in_array($locale, ['ar', 'en'], true) ? $locale : app()->getLocale();
    }

    private static function groupName(string $firstSegment): string
    {
        return match ($firstSegment) {
            'campus-life' => 'Campus Life',
            'about' => 'About',
            'facilities' => 'Faculties',
            'admissions' => 'Admissions',
            'research' => 'Research',
            'e-services' => 'E-Services',
            'contact' => 'Contact',
            'news' => 'News',
            'virtual-tour' => 'Virtual Tour',
            'events' => 'Events',
            default => ucfirst(str_replace('-', ' ', $firstSegment)),
        };
    }

    private static function buildLabel(array $segments, string $locale): string
    {
        // Special-case known pages for clean labels
        $path = '/' . implode('/', $segments);
        $knownLabels = [
            '/about' => 'About SPU',
            '/about/vision-mission' => 'Vision & Mission',
            '/about/history' => 'History',
            '/about/leadership' => 'Leadership',
            '/about/directorates' => 'Directorates',
            '/about/directorates/staff' => 'Directorates — Staff',
            '/about/partnerships' => 'Partnerships',
            '/about/accreditation' => 'Accreditation',
            '/about/why-spu' => 'Why SPU',
            '/about/quality-policy' => 'Quality Policy',
            '/about/ethical-charter' => 'Ethical Charter',
            '/about/organizational-structure' => 'Organizational Structure',
            '/campus-life' => 'Campus Life',
            '/virtual-tour' => 'Virtual Tour',
            '/admissions' => 'Admissions',
            '/e-services' => 'E-Services',
            '/e-services/library' => 'Library',
            '/e-services/staff-email' => 'Staff Email',
            '/e-services/it-support' => 'IT Support',
            '/e-services/suggestions-complaints' => 'Suggestions & Complaints',
            '/contact' => 'Contact Us',
            '/news/articles' => 'News Articles',
            '/research/centers' => 'Research Centers',
            '/research/projects' => 'Research Projects',
            '/research/themes' => 'Research Themes',
            '/facilities/pharmacy/training' => 'Pharmacy Training',
        ];

        if (isset($knownLabels[$path])) {
            return $knownLabels[$path];
        }

        // For dynamic pages (directorates, people, news articles, research items, faculties):
        // Use the last segment humanized, prefixed by parent context
        $last = end($segments);
        $humanized = ucwords(str_replace('-', ' ', $last));

        // Prefix with parent context for nested pages
        if (count($segments) >= 2) {
            $parent = $segments[count($segments) - 2];

            // Directorates sub-page
            if ($segments[0] === 'about' && $segments[1] === 'directorates' && count($segments) === 3) {
                return 'Directorate — ' . $humanized;
            }

            // Person / Faculty-member profile
            if ($segments[0] === 'about' && ($segments[1] ?? '') === 'profile') {
                $type = $segments[2] === 'faculty-member' ? 'Faculty' : 'Staff';
                return $type . ' Profile — ' . $humanized;
            }

            // News article: /news/{id}
            if ($segments[0] === 'news' && count($segments) === 2) {
                return 'News Article — ' . $humanized;
            }

            // Faculty page: /facilities/{slug}/research
            if ($segments[0] === 'facilities' && count($segments) === 3) {
                return ucwords(str_replace('-', ' ', $segments[1])) . ' — ' . $humanized;
            }

            // Research item: /research/{type}/{slug}
            if ($segments[0] === 'research' && count($segments) === 3) {
                return ucwords(str_replace('-', ' ', $parent)) . ' — ' . $humanized;
            }
        }

        return $humanized;
    }

    /**
     * @param  array<string, array<string, string>>  $groups
     * @return array<string, string>
     */
    private static function flattenGroups(array $groups): array
    {
        $flat = [];
        foreach ($groups as $group) {
            if (is_array($group)) {
                foreach ($group as $key => $value) {
                    $flat[$key] = $value;
                }
            }
        }

        return $flat;
    }
}
