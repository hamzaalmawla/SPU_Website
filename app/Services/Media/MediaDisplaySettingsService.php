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
        return implode("\n", array_column($this->rules(), 'css'));
    }

    public function stylesheetFor(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $css = [];

        foreach ($this->rules() as $rule) {
            foreach ($rule['paths'] as $path) {
                // Alpine-rendered images carry their URL in a JSON attribute,
                // where json_encode escapes the slashes.
                if (str_contains($html, $path) || str_contains($html, str_replace('/', '\/', $path))) {
                    $css[] = $rule['css'];

                    break;
                }
            }
        }

        return implode("\n", $css);
    }

    public function version(): string
    {
        return $this->cacheService->tags(['media', 'public-pages'])->remember(
            'media:display-settings:version:v1',
            fn (): string => substr(hash('sha256', $this->stylesheet()), 0, 16),
            3600,
        );
    }

    /** @return list<array{paths: list<string>, css: string}> */
    private function rules(): array
    {
        return $this->cacheService->tags(['media', 'public-pages'])->remember('media:display-settings:v2', function (): array {
            return MediaAsset::query()
                ->where('media_type', 'image')
                ->where('library_scope', 'main')
                ->where(function ($query): void {
                    $query->where('display_fit', '!=', 'cover')
                        ->orWhere('focal_x', '!=', 50)
                        ->orWhere('focal_y', '!=', 50);
                })
                ->get(['path', 'webp_path', 'disk', 'focal_x', 'focal_y', 'display_fit'])
                ->map(function (MediaAsset $asset): ?array {
                    $paths = array_values(array_unique(array_map(
                        fn (string $url): string => $this->urlPath($url),
                        array_filter([
                            MediaUrlResolver::resolve($asset->path, $asset->disk),
                            MediaUrlResolver::resolve($asset->webp_path, $asset->disk),
                        ]),
                    )));

                    if ($paths === []) {
                        return null;
                    }

                    $selectors = array_map(
                        fn (string $path): string => 'img[src*="'.$this->escapeCssString($path).'"]',
                        $paths,
                    );

                    $fit = in_array($asset->display_fit, ['cover', 'contain'], true) ? $asset->display_fit : 'cover';
                    $x = max(0, min(100, (float) $asset->focal_x));
                    $y = max(0, min(100, (float) $asset->focal_y));

                    return [
                        'paths' => $paths,
                        'css' => implode(',', $selectors).'{object-fit:'.$fit.'!important;object-position:'.$x.'% '.$y.'%!important;}',
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }, 3600);
    }

    private function urlPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    private function escapeCssString(string $value): string
    {
        // The rules are inlined in a <style> element, where `<` could close it.
        return str_replace('<', '\3c ', addcslashes($value, "\\\"\n\r\f"));
    }
}
