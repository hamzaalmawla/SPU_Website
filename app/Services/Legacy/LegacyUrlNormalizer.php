<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\DTOs\Legacy\LegacyLanguageDTO;
use App\DTOs\Legacy\LegacySubsiteDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;

final class LegacyUrlNormalizer implements LegacyUrlNormalizerInterface
{
    /** @var array<int, string> */
    private const LANGUAGE_SYMBOLS = [
        1 => 'ar',
        2 => 'en',
        3 => 'fr',
        6 => 'sp',
        7 => 'ge',
    ];

    /** @var array<string, array{site_id: int, public_admin?: bool}> */
    private const SUBSITES = [
        'med' => ['site_id' => 2],
        'dent' => ['site_id' => 3],
        'pharm' => ['site_id' => 4],
        'info' => ['site_id' => 5],
        'petrol' => ['site_id' => 6],
        'admin' => ['site_id' => 7, 'public_admin' => true],
        'research' => ['site_id' => 8],
        'hospital' => ['site_id' => 9],
        'dent_clinic' => ['site_id' => 10],
        'alumni' => ['site_id' => 11],
        'clubs' => ['site_id' => 12],
        'members' => ['site_id' => 13],
    ];

    public function normalize(string $path, ?string $queryString = null): NormalizedLegacyUrlDTO
    {
        $normalizedPath = $this->normalizePath($path);
        $params = $this->normalizeParams($queryString);
        $language = $this->language($params['lang'] ?? null, array_key_exists('mylang', $params));
        $subsite = $this->subsite($normalizedPath);
        $entrypoint = $this->entrypoint($normalizedPath);
        $dir = $this->stringOrNull($params['dir'] ?? null);
        $page = $this->stringOrNull($params['page'] ?? null);
        $extension = $this->extension($params['ex'] ?? null);
        $requestType = $this->requestType($normalizedPath, $entrypoint);

        return new NormalizedLegacyUrlDTO(
            path: $normalizedPath,
            queryString: $queryString !== null && trim($queryString) !== '' ? $queryString : null,
            params: $params,
            language: $language,
            subsite: $subsite,
            entrypoint: $entrypoint,
            dir: $dir,
            page: $page,
            extension: $extension,
            service: $this->service($params),
            handlerKey: $this->handlerKey($subsite, $dir, $page, $entrypoint, $requestType),
            requestType: $requestType,
        );
    }

    public function toLogPayload(NormalizedLegacyUrlDTO $url): array
    {
        return [
            'path' => $url->path,
            'query_string' => $url->queryString,
            'params' => $url->params,
            'language' => [
                'old_language_id' => $url->language->oldLanguageId,
                'old_symbol' => $url->language->oldSymbol,
                'locale' => $url->language->locale,
                'is_supported_locale' => $url->language->isSupportedLocale,
                'fallback_locale' => $url->language->fallbackLocale,
            ],
            'subsite' => [
                'key' => $url->subsite->key,
                'path_prefix' => $url->subsite->pathPrefix,
                'site_id' => $url->subsite->siteId,
                'is_public_admin_subsite' => $url->subsite->isPublicAdminSubsite,
            ],
            'entrypoint' => $url->entrypoint,
            'dir' => $url->dir,
            'page' => $url->page,
            'extension' => $url->extension,
            'service' => $url->service,
            'handler_key' => $url->handlerKey,
            'request_type' => $url->requestType,
        ];
    }

    private function normalizePath(string $path): string
    {
        $pathOnly = parse_url($path, PHP_URL_PATH);
        $pathOnly = is_string($pathOnly) && $pathOnly !== '' ? $pathOnly : $path;

        return '/'.ltrim(str_replace('\\', '/', trim($pathOnly)), '/');
    }

    /** @return array<string, string> */
    private function normalizeParams(?string $queryString): array
    {
        if ($queryString === null || trim($queryString) === '') {
            return [];
        }

        parse_str($queryString, $parsed);
        $params = [];

        foreach ($parsed as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $params[$key] = trim((string) $value);
        }

        if (! isset($params['service']) || $params['service'] === '') {
            if (isset($params['ser']) && $params['ser'] !== '') {
                $params['service'] = $params['ser'];
            } elseif (isset($params['Ser']) && $params['Ser'] !== '') {
                $params['service'] = $params['Ser'];
            }
        }
        unset($params['ser'], $params['Ser']);

        if ((! isset($params['cat_id']) || $params['cat_id'] === '') && isset($params['cat']) && $params['cat'] !== '') {
            $params['cat_id'] = $params['cat'];
        }
        unset($params['cat']);

        if (($params['page'] ?? null) === 'show_cat') {
            $params['page'] = 'show';
        }

        if (array_key_exists('mylang', $params) && (! isset($params['lang']) || $params['lang'] === '')) {
            $params['lang'] = '1';
        }
        unset($params['mylang']);

        return $params;
    }

    private function language(?string $rawLang, bool $hasMyLang): LegacyLanguageDTO
    {
        $oldId = is_numeric($rawLang) ? (int) $rawLang : ($hasMyLang ? 1 : 1);
        $symbol = self::LANGUAGE_SYMBOLS[$oldId] ?? self::LANGUAGE_SYMBOLS[1];
        $isSupported = in_array($symbol, ['ar', 'en'], true);
        $locale = $isSupported ? $symbol : 'ar';

        return new LegacyLanguageDTO(
            oldLanguageId: array_key_exists($oldId, self::LANGUAGE_SYMBOLS) ? $oldId : 1,
            oldSymbol: $symbol,
            locale: $locale,
            isSupportedLocale: $isSupported,
            fallbackLocale: $isSupported ? null : 'ar',
        );
    }

    private function subsite(string $path): LegacySubsiteDTO
    {
        $firstSegment = explode('/', trim($path, '/'))[0] ?? '';
        $definition = self::SUBSITES[$firstSegment] ?? null;

        if ($definition === null) {
            return new LegacySubsiteDTO('root', null, 0);
        }

        return new LegacySubsiteDTO(
            key: $firstSegment,
            pathPrefix: '/'.$firstSegment,
            siteId: $definition['site_id'],
            isPublicAdminSubsite: (bool) ($definition['public_admin'] ?? false),
        );
    }

    private function entrypoint(string $path): ?string
    {
        $basename = basename($path);

        return in_array($basename, ['index.php', 'windex.php', 'slider.php', 'sitemap.xml'], true) ? $basename : null;
    }

    private function extension(?string $ex): string
    {
        return match ($ex) {
            '0' => 'html',
            '1' => 'htm',
            default => 'php',
        };
    }

    /** @param array<string, string> $params */
    private function service(array $params): ?string
    {
        return $this->stringOrNull($params['service'] ?? null);
    }

    private function requestType(string $path, ?string $entrypoint): string
    {
        if (str_starts_with($path, '/downloads/files/')) {
            return 'legacy_media_file';
        }

        if ($entrypoint === 'slider.php') {
            return 'legacy_slider';
        }

        if ($entrypoint === 'sitemap.xml') {
            return 'sitemap';
        }

        if ($entrypoint === 'index.php' || $entrypoint === 'windex.php') {
            return 'legacy_router';
        }

        return 'unknown';
    }

    private function handlerKey(LegacySubsiteDTO $subsite, ?string $dir, ?string $page, ?string $entrypoint, string $requestType): ?string
    {
        if ($requestType === 'legacy_media_file') {
            return 'legacy_media_file';
        }

        if ($entrypoint === 'slider.php') {
            return 'slider';
        }

        if ($entrypoint === 'sitemap.xml') {
            return 'sitemap';
        }

        if ($entrypoint !== 'index.php' && $entrypoint !== 'windex.php') {
            return null;
        }

        if ($dir === null && $page === null) {
            return $subsite->key.':home';
        }

        return $subsite->key.':'.($dir ?? 'root').':'.($page ?? 'home');
    }

    private function stringOrNull(?string $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
