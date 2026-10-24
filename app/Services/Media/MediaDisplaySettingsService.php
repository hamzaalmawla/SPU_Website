<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Media\MediaDisplaySettingsServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Media\MediaAsset;
use App\Support\MediaUrlResolver;

final class MediaDisplaySettingsService implements MediaDisplaySettingsServiceInterface
{
    public function __construct(private readonly CacheServiceInterface $cacheService) {}

    public function stylesheet(): string
    {
        return $this->cacheService->tags(['media', 'public-pages'])->remember('media:display-settings:v1', function (): string {
            return MediaAsset::query()
                ->where('media_type', 'image')
                ->where('library_scope', 'main')
                ->where(function ($query): void {
                    $query->where('display_fit', '!=', 'cover')
                        ->orWhere('focal_x', '!=', 50)
                        ->orWhere('focal_y', '!=', 50);
                })
                ->get(['path', 'webp_path', 'disk', 'focal_x', 'focal_y', 'display_fit'])
                ->map(function (MediaAsset $asset): string {
                    $urls = array_values(array_unique(array_filter([
                        MediaUrlResolver::resolve($asset->path, $asset->disk),
                        MediaUrlResolver::resolve($asset->webp_path, $asset->disk),
                    ])));
                    $selectors = array_map(
                        fn (string $url): string => 'img[src*="'.$this->escapeCssString($this->urlPath($url)).'"]',
                        $urls,
                    );

                    if ($selectors === []) {
                        return '';
                    }

                    $fit = in_array($asset->display_fit, ['cover', 'contain'], true) ? $asset->display_fit : 'cover';
                    $x = max(0, min(100, (float) $asset->focal_x));
                    $y = max(0, min(100, (float) $asset->focal_y));

                    return implode(',', $selectors).'{object-fit:'.$fit.'!important;object-position:'.$x.'% '.$y.'%!important;}';
                })
                ->filter()
                ->implode("\n");
        }, 3600);
    }

    private function urlPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    private function escapeCssString(string $value): string
    {
        return addcslashes($value, "\\\"\n\r\f");
    }
}
