<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A directorate's task groups: titled lists of tasks, each task a title and an
 * optional short body. Stored per locale in directorate_translations.task_groups_json.
 */
final class DirectorateTaskGroups
{
    /**
     * Keeps only well-formed groups and tasks, as plain lists: a group needs a
     * title or a task, a task needs a title.
     *
     * @return array<int, array{title: string, summary: string, items: array<int, array{title: string, body: string}>}>
     */
    public static function normalize(mixed $groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        $normalized = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $items = [];
            foreach (is_array($group['items'] ?? null) ? $group['items'] : [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $title = self::text($item['title'] ?? null);
                if ($title === '') {
                    continue;
                }
                $items[] = ['title' => $title, 'body' => self::text($item['body'] ?? null)];
            }

            $title = self::text($group['title'] ?? null);
            if ($title === '' && $items === []) {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'summary' => self::text($group['summary'] ?? null),
                'items' => $items,
            ];
        }

        return $normalized;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
