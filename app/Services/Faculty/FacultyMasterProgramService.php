<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyMasterProgramServiceInterface;

final class FacultyMasterProgramService implements FacultyMasterProgramServiceInterface
{
    private const COVERAGE_LEVELS = ['I', 'R', 'M'];

    /** @var array<string, array<string, mixed>|null> */
    private array $defaults = [];

    public function isMasterProgramTarget(string $targetKey): bool
    {
        return preg_match('/^facilities\.[a-z0-9-]+\.master-program$/', $targetKey) === 1;
    }

    public function defaultContent(string $facultySlug, string $locale): ?array
    {
        if (preg_match('/^[a-z0-9-]+$/', $facultySlug) !== 1) {
            return null;
        }

        if (! array_key_exists($facultySlug, $this->defaults)) {
            $path = database_path('seeders/Data/master_programs/'.$facultySlug.'.json');
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $this->defaults[$facultySlug] = is_array($decoded) ? $decoded : null;
        }

        $content = $this->defaults[$facultySlug][$locale] ?? null;

        return is_array($content) ? $content : null;
    }

    public function program(array $payload): array
    {
        $payload = $this->normalizePayload($payload);
        $titles = [];

        foreach ($payload['terms'] as $term) {
            foreach ($term['courses'] as $course) {
                $titles[$course['code']] = $course['title'];
            }
        }

        $unlocks = [];

        foreach ($payload['terms'] as $term) {
            foreach ($term['courses'] as $course) {
                foreach ($course['prerequisites'] as $code) {
                    $unlocks[$code][] = $course['code'];
                }
            }
        }

        $link = static fn (string $code): array => ['code' => $code, 'title' => $titles[$code] ?? ''];
        $totals = ['courses' => 0, 'credits' => 0, 'theory' => 0, 'practical' => 0];
        $plos = array_values(array_filter(array_map(static fn (array $outcome): string => $outcome['code'], $payload['outcomes'])));
        $coverageRows = [];
        $terms = [];

        foreach ($payload['terms'] as $term) {
            $courses = array_map(fn (array $course): array => [
                ...$course,
                'prerequisites' => array_values(array_map($link, array_filter($course['prerequisites'], static fn (string $code): bool => isset($titles[$code])))),
                'unlocks' => array_map($link, $unlocks[$course['code']] ?? []),
                'outcomes' => $this->texts($course['outcomes']),
            ], $term['courses']);

            $termTotals = [
                'credits' => array_sum(array_column($courses, 'credits')),
                'theory' => array_sum(array_column($courses, 'theory')),
                'practical' => array_sum(array_column($courses, 'practical')),
            ];

            foreach ($termTotals as $key => $value) {
                $totals[$key] += $value;
            }

            $totals['courses'] += count($courses);

            foreach ($courses as $course) {
                if ($course['coverage'] !== []) {
                    $coverageRows[] = ['code' => $course['code'], 'title' => $course['title'], 'levels' => $course['coverage']];
                }
            }

            $terms[] = [...$term, ...$termTotals, 'courses' => $courses];
        }

        return [
            'overview' => $this->texts($payload['overview']),
            'facts' => $payload['facts'],
            'coding' => $payload['coding'],
            'terms' => $terms,
            'totals' => $totals,
            'outcomes' => $payload['outcomes'],
            'coverage' => [
                'plos' => $plos,
                'rows' => $plos === [] ? [] : $coverageRows,
                'legend' => $payload['coverageLegend'],
            ],
            'assessment' => $payload['assessment'],
            'projectIntro' => $payload['projectIntro'],
            'projectRules' => $this->texts($payload['projectRules']),
            'admission' => [
                ...$payload['admission'],
                'majors' => $this->texts($payload['admission']['majors']),
            ],
            'degreeRequirements' => $this->texts($payload['degreeRequirements']),
            'teachingNote' => $payload['teachingNote'],
        ];
    }

    public function normalizePayload(array $payload): array
    {
        $admission = is_array($payload['admission'] ?? null) ? $payload['admission'] : [];
        $terms = $this->list($payload['terms'] ?? []);

        return [
            ...$payload,
            'overview' => $this->textItems($payload['overview'] ?? []),
            'facts' => $this->rows($payload['facts'] ?? [], ['label', 'value'], 'value'),
            'coding' => $this->string($payload['coding'] ?? null),
            'terms' => array_map(fn (array $term, int $index): array => [
                'id' => $this->string($term['id'] ?? null) ?: 'term-'.($index + 1),
                'label' => $this->string($term['label'] ?? null),
                'theme' => $this->string($term['theme'] ?? null),
                'courses' => array_map(fn (array $course): array => $this->course($course), $this->list($term['courses'] ?? [])),
            ], $terms, array_keys($terms)),
            'outcomes' => array_map(fn (array $outcome): array => [
                'code' => strtoupper($this->string($outcome['code'] ?? null)),
                'text' => $this->string($outcome['text'] ?? null),
            ], $this->list($payload['outcomes'] ?? [])),
            'coverageLegend' => $this->string($payload['coverageLegend'] ?? null),
            'assessment' => $this->rows($payload['assessment'] ?? [], ['type', 'weight', 'description'], 'type'),
            'projectIntro' => $this->string($payload['projectIntro'] ?? null),
            'projectRules' => $this->textItems($payload['projectRules'] ?? []),
            'admission' => [
                'intro' => $this->string($admission['intro'] ?? null),
                'majors' => $this->textItems($admission['majors'] ?? []),
                'criteria' => $this->rows($admission['criteria'] ?? [], ['label', 'weight', 'description'], 'label'),
                'note' => $this->string($admission['note'] ?? null),
            ],
            'degreeRequirements' => $this->textItems($payload['degreeRequirements'] ?? []),
            'teachingNote' => $this->string($payload['teachingNote'] ?? null),
        ];
    }

    public function validationErrors(string $targetKey, array $payload): array
    {
        if (! $this->isMasterProgramTarget($targetKey)) {
            return [];
        }

        $errors = [];
        $codesByLocale = [];

        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $program = $this->normalizePayload(is_array($translation['payload'] ?? null) ? $translation['payload'] : []);
            $codes = [];

            foreach ($program['terms'] as $term) {
                foreach ($term['courses'] as $course) {
                    if ($course['code'] === '') {
                        $errors[$locale][] = 'Every course needs a code.';
                    } elseif (in_array($course['code'], $codes, true)) {
                        $errors[$locale][] = "The course code {$course['code']} is used more than once.";
                    }

                    if ($course['title'] === '') {
                        $errors[$locale][] = 'Every course needs a title.';
                    }

                    $codes[] = $course['code'];
                }
            }

            foreach ($program['terms'] as $term) {
                foreach ($term['courses'] as $course) {
                    foreach ($course['prerequisites'] as $prerequisite) {
                        if ($prerequisite === $course['code'] || ! in_array($prerequisite, $codes, true)) {
                            $errors[$locale][] = "{$course['code']} lists {$prerequisite} as a prerequisite, which is not another course of the program.";
                        }
                    }
                }
            }

            $codesByLocale[$locale] = $codes;
        }

        $arCodes = array_values(array_unique(array_filter($codesByLocale['ar'])));
        $enCodes = array_values(array_unique(array_filter($codesByLocale['en'])));
        sort($arCodes);
        sort($enCodes);

        if ($arCodes !== $enCodes) {
            $errors['en'][] = 'The Arabic and English pages must list the same course codes.';
        }

        return array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors);
    }

    /** @param array<string, mixed> $course @return array<string, mixed> */
    private function course(array $course): array
    {
        $coverage = [];

        foreach (is_array($course['coverage'] ?? null) ? $course['coverage'] : [] as $plo => $level) {
            $plo = strtoupper(trim((string) $plo));
            $level = strtoupper(trim(is_scalar($level) ? (string) $level : ''));

            if ($plo !== '' && in_array($level, self::COVERAGE_LEVELS, true)) {
                $coverage[$plo] = $level;
            }
        }

        $prerequisites = array_map(
            fn (mixed $code): string => strtoupper($this->string(is_array($code) ? ($code['code'] ?? null) : $code)),
            is_array($course['prerequisites'] ?? null) ? array_values($course['prerequisites']) : [],
        );

        return [
            'code' => strtoupper($this->string($course['code'] ?? null)),
            'title' => $this->string($course['title'] ?? null),
            'titleAlt' => $this->string($course['titleAlt'] ?? null),
            'credits' => $this->number($course['credits'] ?? null),
            'theory' => $this->number($course['theory'] ?? null),
            'practical' => $this->number($course['practical'] ?? null),
            'prerequisites' => array_values(array_unique(array_filter($prerequisites, static fn (string $code): bool => $code !== ''))),
            'prerequisiteNote' => $this->string($course['prerequisiteNote'] ?? null),
            'description' => $this->string($course['description'] ?? null),
            'outcomes' => $this->textItems($course['outcomes'] ?? []),
            'grading' => $this->string($course['grading'] ?? null),
            'coverage' => $coverage,
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<int, array<string, string>>
     */
    private function rows(mixed $items, array $fields, string $required): array
    {
        $rows = array_map(function (array $item) use ($fields): array {
            $row = [];

            foreach ($fields as $field) {
                $row[$field] = $this->string($item[$field] ?? null);
            }

            return $row;
        }, $this->list($items));

        return array_values(array_filter($rows, static fn (array $row): bool => $row[$required] !== ''));
    }

    /** @return array<int, array{text: string}> */
    private function textItems(mixed $items): array
    {
        $texts = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $text = $this->string(is_array($item) ? ($item['text'] ?? null) : $item);

            if ($text !== '') {
                $texts[] = ['text' => $text];
            }
        }

        return $texts;
    }

    /** @param array<int, array{text: string}> $items @return array<int, string> */
    private function texts(array $items): array
    {
        return array_column($items, 'text');
    }

    /** @return array<int, array<string, mixed>> */
    private function list(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function number(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
