<?php

declare(strict_types=1);

namespace App\Filament\Components;

use App\Contracts\Navigation\PageLinkOptionServiceInterface;
use Filament\Forms\Components\Select;

/**
 * Reusable page URL dropdown for Filament forms.
 *
 * Options come from PageLinkOptionServiceInterface: every published internal
 * page at any depth, labelled with its trail ("Campus Life › Career
 * Development › Job Board"). The stored value is the locale-prefixed path.
 */
final class PageUrlSelect
{
    public static function make(string $name, string $label, ?string $locale = null, bool $required = false): Select
    {
        $locale = self::resolveLocale($locale);

        return Select::make($name)
            ->label($label)
            ->searchable()
            ->native(false)
            ->placeholder('Select a page...')
            ->formatStateUsing(fn (mixed $state): mixed => self::normalizeUrl($state, $locale))
            ->dehydrateStateUsing(fn (mixed $state): mixed => self::normalizeUrl($state, $locale))
            ->getSearchResultsUsing(fn (string $search): array => self::searchOptions($search, $locale))
            ->getOptionLabelUsing(fn (mixed $value): ?string => self::optionLabel($value, $locale))
            ->helperText('Search and choose an internal page. The URL is filled in automatically.')
            ->required($required);
    }

    /**
     * @return array<string, string>
     */
    public static function searchOptions(string $search, ?string $locale = null): array
    {
        return self::optionService()->search(self::resolveLocale($locale), $search);
    }

    /**
     * Every option for the locale, keyed by stored path.
     *
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        return self::optionService()->options(self::resolveLocale($locale));
    }

    private static function optionLabel(mixed $value, ?string $locale): ?string
    {
        $value = self::normalizeUrl($value, $locale);

        if (! is_string($value) || $value === '') {
            return null;
        }

        // A saved link that is no longer a known page (or an external URL)
        // still shows, as itself, so the editor can see and replace it.
        return self::options($locale)[$value] ?? $value;
    }

    private static function optionService(): PageLinkOptionServiceInterface
    {
        return app(PageLinkOptionServiceInterface::class);
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

        $path = '/'.ltrim($path, '/');
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
}
