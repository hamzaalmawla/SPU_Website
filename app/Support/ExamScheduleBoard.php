<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lays out a final exam timetable (the `rows` of one schedule on a faculty's
 * final-exams page) as a board: one column per exam day, in date order, each
 * exam tagged with its study year so the public page can filter by year, link
 * a year's exams across the days and open each one's details.
 *
 * Rows are free text typed by editors, so nothing here is required: an exam
 * without a day or date lands in a last "other" column, a date that can't be
 * read keeps the editor's order, and an exam without a year has no year tag.
 */
final class ExamScheduleBoard
{
    /** Year colours, in order of the year's first exam. */
    public const YEAR_COLORS = ['#2563eb', '#d97706', '#059669', '#db2777', '#7c3aed', '#0891b2', '#65a30d', '#dc2626'];

    /**
     * @param  array<int, mixed>  $rows
     * @return array{
     *     days: list<array{key: string, day: string, date: string, iso: string, exams: list<array{id: string, course: string, time: string, year: string, yearKey: string, room: string, day: string, date: string, iso: string, previous: string}>}>,
     *     years: list<array{key: string, label: string, color: string, count: int}>,
     *     total: int
     * }
     */
    public static function build(array $rows, string $idPrefix = 'exam'): array
    {
        $days = [];
        $years = [];
        $total = 0;

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $course = self::text($row['course'] ?? null);
            if ($course === '') {
                continue;
            }

            $day = self::text($row['day'] ?? null);
            $date = self::text($row['date'] ?? null);
            $iso = self::isoDate($date);
            $dayKey = $iso !== '' ? $iso : SearchTextNormalizer::normalize($day.' '.$date);

            $year = self::text($row['year'] ?? null);
            $yearKey = '';
            if ($year !== '') {
                $normalizedYear = SearchTextNormalizer::normalize($year);
                if (! isset($years[$normalizedYear])) {
                    $position = count($years);
                    $years[$normalizedYear] = [
                        'key' => 'y'.($position + 1),
                        'label' => $year,
                        'color' => self::YEAR_COLORS[$position % count(self::YEAR_COLORS)],
                        'count' => 0,
                    ];
                }
                $years[$normalizedYear]['count']++;
                $yearKey = $years[$normalizedYear]['key'];
            }

            if (! isset($days[$dayKey])) {
                $days[$dayKey] = ['key' => '', 'day' => $day, 'date' => $date, 'iso' => $iso, 'exams' => []];
            }

            $days[$dayKey]['exams'][] = [
                'id' => $idPrefix.'-'.($index + 1),
                'course' => $course,
                'time' => self::text($row['time'] ?? null),
                'year' => $year,
                'yearKey' => $yearKey,
                'room' => self::text($row['room'] ?? null),
                'day' => $day,
                'date' => $date,
                'iso' => $iso,
                'previous' => '',
            ];
            $total++;
        }

        $days = array_values($days);

        // Read dates go first, in date order; the editor's order is kept among the rest,
        // and the exams with neither a day nor a date come last.
        usort($days, static function (array $a, array $b): int {
            $rank = static fn (array $group): int => $group['iso'] !== '' ? 0 : (($group['day'] === '' && $group['date'] === '') ? 2 : 1);
            if ($rank($a) !== $rank($b)) {
                return $rank($a) <=> $rank($b);
            }

            return $a['iso'] !== '' && $b['iso'] !== '' && $a['iso'] !== $b['iso'] ? strcmp($a['iso'], $b['iso']) : 0;
        });

        // Each exam points at the same year's exam on the closest earlier day.
        $lastOfYear = [];
        foreach ($days as $position => &$group) {
            $group['key'] = $idPrefix.'-day-'.($position + 1);
            $seenToday = [];
            foreach ($group['exams'] as &$exam) {
                if ($exam['yearKey'] !== '' && isset($lastOfYear[$exam['yearKey']])) {
                    $exam['previous'] = $lastOfYear[$exam['yearKey']];
                }
                if ($exam['yearKey'] !== '') {
                    $seenToday[$exam['yearKey']] = $exam['id'];
                }
            }
            unset($exam);
            $lastOfYear = array_merge($lastOfYear, $seenToday);
        }
        unset($group);

        return ['days' => $days, 'years' => array_values($years), 'total' => $total];
    }

    /** A written date as `Y-m-d`, or '' when it can't be read. Accepts Arabic-Indic digits. */
    public static function isoDate(string $value): string
    {
        $value = strtr(trim($value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $value, $match) === 1) {
            [$year, $month, $day] = [(int) $match[1], (int) $match[2], (int) $match[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $value, $match) === 1) {
            [$day, $month, $year] = [(int) $match[1], (int) $match[2], (int) $match[3]];
        } else {
            return '';
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
