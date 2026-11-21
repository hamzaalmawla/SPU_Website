<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

final class ImageDimensionGuide
{
    /**
     * Map of keywords to profile identifiers. Order matters: more specific first.
     *
     * @var array<string, list<string>>
     */
    private const KEYWORD_MAP = [
        'icon' => ['icon', 'logo', 'badge', 'symbol'],
        'social' => ['og_image', 'seo_image', 'seoimage', 'social_image', 'default_og_image', 'seo.image'],
        'portrait' => ['photo', 'profile', 'dean', 'speaker', 'manager', 'person', 'member', 'avatar'],
        'gallery' => ['gallery', 'tour', 'scene', 'campus_image', 'students_image', 'timeline_image'],
        'hero' => ['hero', 'banner', 'background', 'imageprimary', 'imagesecondary', 'imageoverview', 'founding', 'bg_image'],
        'card' => [
            'cover', 'article', 'event', 'news', 'feature', 'card', 'project',
            'laboratory', 'center', 'achievement', 'partnership', 'publication',
            'service', 'job', 'highlight', 'specification',
        ],
    ];

    /**
     * Get the full guidance configuration array for a given state path and type.
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     dimensions: string,
     *     ratio: string,
     *     min: string,
     *     formats: string,
     *     size: string,
     *     tip: string,
     *     compact_hint: string
     * }
     */
    public static function forSlot(string $statePath, string $type = 'image'): array
    {
        $profileKey = self::detectProfile($statePath, $type);

        return self::getProfileData($profileKey);
    }

    /**
     * Detect the profile key from the field state path and media type.
     */
    public static function detectProfile(string $statePath, string $type = 'image'): string
    {
        if ($type === 'icon') {
            return 'icon';
        }

        $normalized = strtolower(str_replace(['.', '-', '_', ' '], '', $statePath));

        foreach (self::KEYWORD_MAP as $profile => $keywords) {
            foreach ($keywords as $keyword) {
                $cleanKeyword = strtolower(str_replace(['.', '-', '_', ' '], '', $keyword));
                if (str_contains($normalized, $cleanKeyword)) {
                    return $profile;
                }
            }
        }

        return 'general';
    }

    /**
     * Return localized guidance data for a specific profile key.
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     dimensions: string,
     *     ratio: string,
     *     min: string,
     *     formats: string,
     *     size: string,
     *     tip: string,
     *     compact_hint: string
     * }
     */
    public static function getProfileData(string $profileKey): array
    {
        $prefix = "admin.image_guidance.profiles.{$profileKey}";

        $dimensions = (string) __("{$prefix}.dimensions");
        $ratio = (string) __("{$prefix}.ratio");
        $size = (string) __("{$prefix}.size");

        return [
            'key' => $profileKey,
            'name' => (string) __("{$prefix}.name"),
            'dimensions' => $dimensions,
            'ratio' => $ratio,
            'min' => (string) __("{$prefix}.min"),
            'formats' => (string) __("{$prefix}.formats"),
            'size' => $size,
            'tip' => (string) __("{$prefix}.tip"),
            'compact_hint' => (string) __('admin.image_guidance.compact_hint', [
                'dimensions' => $dimensions,
                'ratio' => $ratio,
                'size' => $size,
            ]),
        ];
    }

    /**
     * Returns a list of all defined profiles with their full localized guidance data.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     dimensions: string,
     *     ratio: string,
     *     min: string,
     *     formats: string,
     *     size: string,
     *     tip: string,
     *     compact_hint: string
     * }>
     */
    public static function allProfiles(): array
    {
        return [
            self::getProfileData('hero'),
            self::getProfileData('card'),
            self::getProfileData('portrait'),
            self::getProfileData('social'),
            self::getProfileData('icon'),
            self::getProfileData('gallery'),
            self::getProfileData('general'),
        ];
    }

    /**
     * Concise single-line summary text for use in Filament helperText or FileUpload description.
     */
    public static function compactSummary(string $statePath, string $type = 'image'): string
    {
        return self::forSlot($statePath, $type)['compact_hint'];
    }

    /**
     * Micro HTML badge for subtle placement next to preview or field helper text.
     */
    public static function compactBadgeHtml(string $statePath, string $type = 'image'): HtmlString
    {
        $data = self::forSlot($statePath, $type);
        $title = e(__('admin.image_guidance.suggest_badge'));
        $dimensions = e($data['dimensions']);
        $ratio = e($data['ratio']);

        $html = '<span class="spu-image-hint-pill" title="'.$title.'">'
            .'<svg class="spu-image-hint-pill__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
            .'<path fill-rule="evenodd" d="M10 1c3.866 0 7 3.134 7 7 0 2.386-1.192 4.492-3.013 5.761-.308.215-.487.568-.487.94v.799c0 .828-.672 1.5-1.5 1.5h-4c-.828 0-1.5-.672-1.5-1.5v-.799c0-.372-.179-.725-.487-.94C4.192 12.492 3 10.386 3 8c0-3.866 3.134-7 7-7zm-2 16.5a1.5 1.5 0 003 0h-3z" clip-rule="evenodd" />'
            .'</svg>'
            .'<span class="spu-image-hint-pill__label">'.$title.':</span> '
            .'<strong class="spu-image-hint-pill__specs">'.$dimensions.' ('.$ratio.')</strong>'
            .'</span>';

        return new HtmlString($html);
    }
}
