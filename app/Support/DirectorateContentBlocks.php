<?php

declare(strict_types=1);

namespace App\Support;

use App\DTOs\Content\DirectorateBlockDTO;

/**
 * The blocks an editor builds a directorate's page from, in order, once for
 * both languages: a heading, a text box, an image, or a button that opens a
 * web address or one of the site's own forms. Stored in
 * directorates.content_blocks_json as Filament builder items:
 *
 *   heading: {type, data: {text_ar, text_en}}
 *   text:    {type, data: {body_ar, body_en}}
 *   image:   {type, data: {image, alt_ar, alt_en, caption_ar, caption_en}}
 *   button:  {type, data: {label_ar, label_en, link: url|form, url, formId}}
 *
 * An English field left empty shows the Arabic one.
 */
final class DirectorateContentBlocks
{
    public const TYPES = ['heading', 'text', 'image', 'button'];

    public const LINK_URL = 'url';

    public const LINK_FORM = 'form';

    private const FORM_KEY = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Keeps only blocks that show something, as a plain list. Unsafe
     * addresses are dropped.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public static function normalize(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $normalized = [];
        foreach ($blocks as $block) {
            if (! is_array($block) || ! in_array($block['type'] ?? null, self::TYPES, true)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $clean = match ($block['type']) {
                'heading' => self::pair($data, 'text', 255),
                'text' => self::pair($data, 'body', 20000),
                'image' => self::image($data),
                'button' => self::button($data),
            };

            if ($clean !== null) {
                $normalized[] = ['type' => (string) $block['type'], 'data' => $clean];
            }
        }

        return $normalized;
    }

    /**
     * The blocks in one language, ready for the page.
     *
     * @return list<DirectorateBlockDTO>
     */
    public static function localize(mixed $blocks, string $locale): array
    {
        $locale = $locale === 'en' ? 'en' : 'ar';
        $localized = [];

        foreach (self::normalize($blocks) as $block) {
            $data = $block['data'];
            $dto = match ($block['type']) {
                'heading' => new DirectorateBlockDTO(type: 'heading', text: self::pick($data, 'text', $locale)),
                'text' => new DirectorateBlockDTO(type: 'text', text: self::pick($data, 'body', $locale)),
                'image' => new DirectorateBlockDTO(
                    type: 'image',
                    image: is_string($data['image']) ? $data['image'] : null,
                    alt: self::pick($data, 'alt', $locale),
                    caption: self::pick($data, 'caption', $locale),
                ),
                'button' => self::localizedButton($data, $locale),
            };

            if ($dto !== null) {
                $localized[] = $dto;
            }
        }

        return $localized;
    }

    /**
     * The site forms the buttons open, so a form in use is never deleted.
     *
     * @return list<string>
     */
    public static function formKeys(mixed $blocks): array
    {
        $keys = [];
        foreach (self::normalize($blocks) as $block) {
            if ($block['type'] === 'button' && $block['data']['link'] === self::LINK_FORM && is_string($block['data']['formId'])) {
                $keys[$block['data']['formId']] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * What stops the blocks from publishing, by block number (from 1).
     *
     * @return list<string>
     */
    public static function errors(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $errors = [];
        foreach (array_values($blocks) as $index => $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'button') {
                continue;
            }

            $number = $index + 1;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            if (self::text($data['label_ar'] ?? null) === '' && self::text($data['label_en'] ?? null) === '') {
                $errors[] = "Button {$number} needs a label.";
            }

            if (($data['link'] ?? self::LINK_URL) === self::LINK_FORM) {
                if (preg_match(self::FORM_KEY, self::text($data['formId'] ?? null)) !== 1) {
                    $errors[] = "Button {$number} needs a form.";
                }
            } elseif (self::safeUrl($data['url'] ?? null) === null) {
                $errors[] = "Button {$number} needs a safe web address (https://… or a site path such as /ar/contact).";
            }
        }

        return $errors;
    }

    /** A web address or site path a button may open, or null. */
    public static function safeUrl(mixed $value): ?string
    {
        return UrlSanitizer::sanitize(is_string($value) ? $value : null, ['http', 'https', 'mailto', 'tel']);
    }

    /** @param array<string, mixed> $data @return array<string, string>|null */
    private static function pair(array $data, string $field, int $limit): ?array
    {
        $ar = mb_substr(self::text($data[$field.'_ar'] ?? null), 0, $limit);
        $en = mb_substr(self::text($data[$field.'_en'] ?? null), 0, $limit);

        return $ar === '' && $en === '' ? null : [$field.'_ar' => $ar, $field.'_en' => $en];
    }

    /** @param array<string, mixed> $data @return array<string, string|null>|null */
    private static function image(array $data): ?array
    {
        $image = UrlSanitizer::sanitize(self::text($data['image'] ?? null) ?: null, ['http', 'https']);
        if ($image === null) {
            return null;
        }

        return [
            'image' => $image,
            'alt_ar' => mb_substr(self::text($data['alt_ar'] ?? null), 0, 255),
            'alt_en' => mb_substr(self::text($data['alt_en'] ?? null), 0, 255),
            'caption_ar' => mb_substr(self::text($data['caption_ar'] ?? null), 0, 500),
            'caption_en' => mb_substr(self::text($data['caption_en'] ?? null), 0, 500),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, string|null>|null */
    private static function button(array $data): ?array
    {
        $labelAr = mb_substr(self::text($data['label_ar'] ?? null), 0, 120);
        $labelEn = mb_substr(self::text($data['label_en'] ?? null), 0, 120);
        $link = ($data['link'] ?? null) === self::LINK_FORM ? self::LINK_FORM : self::LINK_URL;
        $formId = self::text($data['formId'] ?? null);

        return [
            'label_ar' => $labelAr,
            'label_en' => $labelEn,
            'link' => $link,
            'url' => $link === self::LINK_URL ? self::safeUrl($data['url'] ?? null) : null,
            'formId' => $link === self::LINK_FORM && preg_match(self::FORM_KEY, $formId) === 1 ? $formId : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function localizedButton(array $data, string $locale): ?DirectorateBlockDTO
    {
        $label = self::pick($data, 'label', $locale);
        $url = is_string($data['url'] ?? null) ? $data['url'] : null;
        $formKey = is_string($data['formId'] ?? null) ? $data['formId'] : null;
        if ($label === '' || ($url === null && $formKey === null)) {
            return null;
        }

        return new DirectorateBlockDTO(
            type: 'button',
            label: $label,
            url: $url,
            formKey: $url === null ? $formKey : null,
            isExternal: $url !== null && preg_match('#^https?://#i', $url) === 1,
        );
    }

    /** @param array<string, mixed> $data */
    private static function pick(array $data, string $field, string $locale): string
    {
        $value = self::text($data[$field.'_'.$locale] ?? null);

        return $value !== '' ? $value : self::text($data[$field.'_ar'] ?? null);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
