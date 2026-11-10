<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\ProjectFieldBlockParserInterface;
use App\DTOs\Faculty\ProjectFieldBlockDTO;

/**
 * Reads the field block the legacy site wrote at the top of student project
 * bodies:
 *
 *     اعداد
 *     Farah fares
 *     Ahmad shekha
 *     تاريخ
 *     2025-2026
 *
 * The importer assumed one value line per label, so the second name became the
 * project summary, the team kept only the first name, and the whole block was
 * rendered again as the description. A label here owns every following line
 * that reads as a name, until the next label, a year, or prose.
 */
final class ProjectFieldBlockParser implements ProjectFieldBlockParserInterface
{
    // اعداد and اشراف are written without the hamza throughout the legacy
    // content, so both alef spellings are accepted.
    private const TEAM_LABEL = '[إا]عداد(?:\s+(?:الطالب(?:ة|ات|ين)?|الطلاب))?|فريق العمل|الفريق|prepared\s+by|team(?:\s+members)?';

    private const SUPERVISOR_LABEL = '[إا]شراف|المشرف(?:ة)?|supervised\s+by|supervisor';

    // The legacy bodies misspell تاريخ as تاريح, تاربخ and الناريخ; all of them
    // sit directly above a year, so the pattern tolerates those letters.
    private const DATE_LABEL = '(?:ال)?[تن]ا?ر[يىبئ][خحج]|العام الدراسي|السنة الدراسية|academic\s+year|date';

    // Labels that can only introduce a value. الفريق and team also open
    // ordinary sentences ("الفريق قام بتطوير النظام"), so they need a colon.
    private const UNAMBIGUOUS_LABEL = '/^(?:[إا]عداد|[إا]شراف)/u';

    private const NAME_SEPARATORS = '/\s*[,،؛;]\s*|\s+[-–]\s+/u';

    public function parse(array $paragraphs): ProjectFieldBlockDTO
    {
        $lines = array_values(array_filter(array_map(
            static fn (mixed $paragraph): string => is_string($paragraph)
                ? trim(preg_replace('/\s+/u', ' ', $paragraph) ?? $paragraph)
                : '',
            $paragraphs,
        ), static fn (string $line): bool => $line !== ''));

        $description = [];
        $fieldLines = [];
        $team = [];
        $supervisors = [];
        $year = null;
        $mode = null;

        foreach ($lines as $line) {
            $label = $this->label($line);
            if ($label !== null) {
                [$mode, $value] = $label;
                $fieldLines[] = $line;

                if ($value !== '' && $mode === 'date') {
                    $year ??= $this->normalizedYear($value);
                    $mode = null;
                } elseif ($value !== '' && $mode === 'team') {
                    array_push($team, ...$this->splitNames($value));
                } elseif ($value !== '') {
                    array_push($supervisors, ...$this->splitNames($value));
                }

                continue;
            }

            if ($mode !== null) {
                if ($this->isYear($line)) {
                    $year ??= $this->normalizedYear($line);
                    $fieldLines[] = $line;
                    $mode = null;

                    continue;
                }

                if ($mode !== 'date' && $this->isNameList($line)) {
                    $names = $this->splitNames($line);
                    $mode === 'team' ? array_push($team, ...$names) : array_push($supervisors, ...$names);
                    $fieldLines[] = $line;

                    continue;
                }

                $mode = null;
            }

            $description[] = $line;
        }

        $supervisors = array_values(array_unique($supervisors));

        return new ProjectFieldBlockDTO(
            description: $description,
            team: array_values(array_unique($team)),
            supervisor: $supervisors === [] ? null : implode('، ', $supervisors),
            year: $year,
            fieldLines: $fieldLines,
        );
    }

    public function splitNames(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => trim($name, " \t\n\r\0\x0B-–:："),
            preg_split(self::NAME_SEPARATORS, trim($value)) ?: [],
        ), static fn (string $name): bool => $name !== '')));
    }

    /** @return array{0: 'team'|'supervisor'|'date', 1: string}|null */
    private function label(string $line): ?array
    {
        foreach (['team' => self::TEAM_LABEL, 'supervisor' => self::SUPERVISOR_LABEL, 'date' => self::DATE_LABEL] as $kind => $pattern) {
            if (preg_match('/^(?:'.$pattern.')(?=$|[\s:：]|\d)\s*([:：])?\s*(.*)$/iu', $line, $matches) !== 1) {
                continue;
            }

            $hasColon = ($matches[1] ?? '') !== '';
            $value = trim($matches[2] ?? '');

            if ($value === '' || $hasColon) {
                return [$kind, $value];
            }

            // A value without a colon: "إعداد Sara Ahmad" or "تاريخ 2024 - 2025"
            // are fields; "تاريخ الصيدلة يعود ..." is a sentence about history.
            if ($kind === 'date' && $this->isYear($value)) {
                return [$kind, $value];
            }

            if ($kind !== 'date' && preg_match(self::UNAMBIGUOUS_LABEL, $line) === 1 && $this->isShortName($value)) {
                return [$kind, $value];
            }
        }

        return null;
    }

    private function isYear(string $value): bool
    {
        return preg_match('/^(?:\d{4}(?:\s*[-–\/]\s*\d{4})?|\d{8})$/u', trim($value)) === 1;
    }

    private function normalizedYear(string $value): ?string
    {
        $value = trim($value);
        if (! $this->isYear($value)) {
            return null;
        }

        if (preg_match('/^\d{8}$/', $value) === 1) {
            return substr($value, 0, 4).'-'.substr($value, 4);
        }

        return preg_replace('/\s*[-–\/]\s*/u', '-', $value) ?? $value;
    }

    /** A line holding one or more names, not a sentence or a heading. */
    private function isNameList(string $line): bool
    {
        if (mb_strlen($line) > 200 || preg_match('/[:：؟?!]\s*$|[؟?!]/u', $line) === 1) {
            return false;
        }

        $names = $this->splitNames($line);

        return $names !== [] && collect($names)->every(fn (string $name): bool => $this->isShortName($name, 6));
    }

    private function isShortName(string $value, int $maxWords = 4): bool
    {
        $value = trim($value, " \t\n\r\0\x0B-–");

        return $value !== ''
            && mb_strlen($value) <= 60
            && count(preg_split('/\s+/u', $value) ?: []) <= $maxWords
            && preg_match('/\.\s*$|[؟?!:：]/u', $value) === 0;
    }
}
