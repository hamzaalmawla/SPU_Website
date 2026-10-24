<?php

declare(strict_types=1);

namespace App\Support\News;

/**
 * The sample events shipped in resources/data/news-events-content.json.
 */
final class SampleCalendarEvents
{
    /**
     * IDs of sample events in an Events page payload that still carry their
     * sample title in both languages. One an editor rewrote is real content.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function untouchedIds(array $payload): array
    {
        $path = resource_path('data/news-events-content.json');
        $sample = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $titles = [];
        $matches = ['ar' => [], 'en' => []];

        foreach (['ar', 'en'] as $locale) {
            foreach (['upcoming', 'past'] as $list) {
                foreach (is_array($sample['translations'][$locale][$list] ?? null) ? $sample['translations'][$locale][$list] : [] as $event) {
                    $titles[$locale][(string) ($event['id'] ?? '')] = trim((string) ($event['title'] ?? ''));
                }

                foreach (is_array($payload['translations'][$locale][$list] ?? null) ? $payload['translations'][$locale][$list] : [] as $event) {
                    $id = is_array($event) ? (string) ($event['id'] ?? '') : '';
                    $matches[$locale][$id] = isset($titles[$locale][$id]) && $titles[$locale][$id] === trim((string) ($event['title'] ?? ''));
                }
            }
        }

        return array_values(array_keys(array_filter(
            $matches['ar'],
            static fn (bool $unchanged, string $id): bool => $unchanged && ($matches['en'][$id] ?? false),
            ARRAY_FILTER_USE_BOTH,
        )));
    }
}
