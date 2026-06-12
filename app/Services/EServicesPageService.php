<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EServicesPageServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\DTOs\EServicesPageContentDTO;
use App\DTOs\EServicesPageDTO;
use App\DTOs\SettingsDTO;
use App\DTOs\SettingValueDTO;

final class EServicesPageService implements EServicesPageServiceInterface
{
    public function __construct(
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function getPage(string $locale): EServicesPageDTO
    {
        $content = $this->getContent($locale);

        return new EServicesPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            hero: $content->hero,
            digitalServices: $content->digitalServices,
            supportCards: $content->supportCards,
            seoTitle: $content->seoTitle,
            seoDescription: $content->seoDescription,
            seoImage: $content->seoImage,
        );
    }

    public function getContent(string $locale): EServicesPageContentDTO
    {
        $settings = $this->settingsService->getGroup('e_services_page', $locale);
        $content = collect($settings->values)->firstWhere('key', 'content')?->jsonValue ?? [];

        return $this->contentFromArray(is_array($content) ? $content : []);
    }

    public function updatePage(string $locale, EServicesPageContentDTO $content, int $userId): bool
    {
        return $this->settingsService->updateGroup(
            new SettingsDTO('e_services_page', $locale, [
                new SettingValueDTO(
                    key: 'content',
                    type: 'json',
                    jsonValue: $this->contentToArray($content),
                    isPublic: true,
                ),
            ]),
            $userId,
        );
    }

    /** @param array<string, mixed> $content */
    private function contentFromArray(array $content): EServicesPageContentDTO
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $digital = is_array($content['digitalServices'] ?? null) ? $content['digitalServices'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return new EServicesPageContentDTO(
            hero: [
                'eyebrow' => $this->stringValue($hero, 'eyebrow'),
                'title' => $this->stringValue($hero, 'title'),
                'summary' => $this->stringValue($hero, 'summary'),
                'imageHero' => $this->stringValue($hero, 'imageHero'),
                'imageLeft' => $this->stringValue($hero, 'imageLeft'),
                'imageRight' => $this->stringValue($hero, 'imageRight'),
            ],
            digitalServices: [
                'title' => $this->stringValue($digital, 'title'),
                'services' => $this->services($digital['services'] ?? []),
            ],
            supportCards: $this->supportCards($content['supportCards'] ?? []),
            seoTitle: $this->stringValue($seo, 'title'),
            seoDescription: $this->stringValue($seo, 'description'),
            seoImage: $this->stringValue($seo, 'image'),
        );
    }

    /** @return array<string, mixed> */
    private function contentToArray(EServicesPageContentDTO $content): array
    {
        return [
            'hero' => $content->hero,
            'digitalServices' => $content->digitalServices,
            'supportCards' => $content->supportCards,
            'seo' => [
                'title' => $content->seoTitle,
                'description' => $content->seoDescription,
                'image' => $content->seoImage,
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function services(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'title' => $this->stringValue($item, 'title'),
                'summary' => $this->stringValue($item, 'summary'),
                'icon' => $this->stringValue($item, 'icon'),
                'url' => $this->stringValue($item, 'url'),
                'button' => $this->stringValue($item, 'button'),
            ];
        }, $items)));
    }

    /** @return array<int, array<string, string>> */
    private function supportCards(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'id' => $this->stringValue($item, 'id'),
                'eyebrow' => $this->stringValue($item, 'eyebrow'),
                'title' => $this->stringValue($item, 'title'),
                'summary' => $this->stringValue($item, 'summary'),
            ];
        }, $items)));
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
