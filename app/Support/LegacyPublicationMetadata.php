<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reads the author, publisher, abstract and keyword sections that legacy
 * research publications carry inside one labelled abstract blob.
 */
final class LegacyPublicationMetadata
{
    /**
     * Fills fields the abstract lacks from the other translations' abstracts.
     *
     * @param  array{author: string, publisher: string, abstract: string, keywords: list<string>}  $metadata
     * @param  array<int, string|null>  $abstracts
     * @return array{author: string, publisher: string, abstract: string, keywords: list<string>}
     */
    public static function withFallback(array $metadata, array $abstracts): array
    {
        if ($metadata['author'] !== '' && $metadata['publisher'] !== '' && $metadata['abstract'] !== '' && $metadata['keywords'] !== []) {
            return $metadata;
        }

        foreach ($abstracts as $abstract) {
            $fallback = self::parse($abstract);
            $metadata = [
                'author' => $metadata['author'] !== '' ? $metadata['author'] : $fallback['author'],
                'publisher' => $metadata['publisher'] !== '' ? $metadata['publisher'] : $fallback['publisher'],
                'abstract' => $metadata['abstract'] !== '' ? $metadata['abstract'] : $fallback['abstract'],
                'keywords' => $metadata['keywords'] !== [] ? $metadata['keywords'] : $fallback['keywords'],
            ];
        }

        return $metadata;
    }

    /** @return array{author: string, publisher: string, abstract: string, keywords: list<string>} */
    public static function parse(?string $value): array
    {
        $lines = self::lines($value);

        return [
            'author' => self::sectionText($lines, ['author', 'authors', 'author:', 'authors:', 'المؤلف', 'المؤلفون'], ['published in', 'abstract', 'keywords', 'نشر في', 'الملخص', 'الكلمات المفتاحية']),
            'publisher' => self::sectionText($lines, ['published in', 'published in:', 'publisher', 'publisher:', 'نشر في', 'الناشر'], ['abstract', 'keywords', 'الملخص', 'الكلمات المفتاحية']),
            'abstract' => self::sectionText($lines, ['abstract', 'abstract:', 'الملخص'], ['keywords', 'الكلمات المفتاحية']),
            'keywords' => self::keywords(self::sectionText($lines, ['keywords', 'keywords:', 'key words', 'key words:', 'الكلمات المفتاحية'], [])),
        ];
    }

    /** @return list<string> */
    private static function lines(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
        $value = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $value) ?? $value;
        $value = strip_tags($value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), preg_split('/\R/u', $value) ?: []),
            static fn (string $line): bool => $line !== ''
        ));
    }

    /** @param list<string> $lines @param list<string> $startLabels @param list<string> $stopLabels */
    private static function sectionText(array $lines, array $startLabels, array $stopLabels): string
    {
        $capturing = false;
        $captured = [];

        foreach ($lines as $line) {
            $normalized = self::sectionLabel($line);

            if (! $capturing && in_array($normalized, $startLabels, true)) {
                $capturing = true;

                continue;
            }

            if ($capturing && in_array($normalized, $stopLabels, true)) {
                break;
            }

            if ($capturing) {
                $captured[] = $line;
            }
        }

        $text = preg_replace('/\s+/u', ' ', implode(' ', $captured)) ?? implode(' ', $captured);

        return trim($text);
    }

    private static function sectionLabel(string $line): string
    {
        $line = trim($line);
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
        $line = trim($line, " \t\n\r\0\x0B:：.-");

        return strtolower($line);
    }

    /** @return list<string> */
    private static function keywords(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[,;،]+/u', $value) ?: [];

        return array_slice(array_values(array_filter(
            array_map(static fn (string $part): string => trim($part), $parts),
            static fn (string $part): bool => $part !== ''
        )), 0, 12);
    }
}
