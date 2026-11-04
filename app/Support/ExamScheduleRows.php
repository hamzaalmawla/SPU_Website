<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Final exam timetable rows (`day`, `date`, `time`, `course`, `year`, `room`) read
 * from rows an editor copied out of a spreadsheet or a Word table and pasted into
 * the admin. Cells are separated by tabs (or, failing that, by commas or "|").
 * A first line naming the columns (اليوم، التاريخ، الساعة، المقرر، السنة، القاعة,
 * or Day, Date, Time, Course, Year, Hall/Room) sets their order; without one the
 * order is Day, Date, Time, Course, Year, Hall. A row without a course is skipped,
 * and a date that can be read is stored as Y-m-d so the date picker shows it.
 */
final class ExamScheduleRows
{
    public const COLUMNS = ['day', 'date', 'time', 'course', 'year', 'room'];

    /** @var array<string, list<string>> folded header names per column */
    private const HEADERS = [
        'day' => ['اليوم', 'يوم', 'day', 'weekday'],
        'date' => ['التاريخ', 'تاريخ', 'date'],
        'time' => ['الساعه', 'الوقت', 'التوقيت', 'ساعه', 'time', 'hour'],
        'course' => ['المقرر', 'الماده', 'اسم المقرر', 'اسم الماده', 'المقررات', 'course', 'subject', 'course name'],
        'year' => ['السنه', 'السنه الدراسيه', 'المستوى', 'الصف', 'year', 'level', 'study year'],
        'room' => ['القاعه', 'المكان', 'القاعات', 'room', 'hall', 'place', 'location'],
    ];

    /**
     * @return list<array{day: string, date: string, time: string, course: string, year: string, room: string}>
     */
    public static function fromPastedText(string $text): array
    {
        $lines = array_values(array_filter(
            array_map('rtrim', preg_split('/\r\n|\r|\n/', $text) ?: []),
            static fn (string $line): bool => trim($line) !== '',
        ));

        if ($lines === []) {
            return [];
        }

        $separator = str_contains($text, "\t") ? "\t" : (str_contains($lines[0], '|') ? '|' : ',');
        $order = self::headerOrder(self::cells($lines[0], $separator));

        if ($order !== null) {
            array_shift($lines);
        } else {
            $order = self::COLUMNS;
        }

        $rows = [];

        foreach ($lines as $line) {
            $cells = self::cells($line, $separator);
            $row = array_fill_keys(self::COLUMNS, '');

            foreach ($order as $position => $column) {
                if ($column !== null && isset($cells[$position])) {
                    $row[$column] = $cells[$position];
                }
            }

            if ($row['course'] === '') {
                continue;
            }

            $iso = ExamScheduleBoard::isoDate($row['date']);
            $row['date'] = $iso !== '' ? $iso : $row['date'];
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The column of each cell when the line names the columns, else null.
     *
     * @param  list<string>  $cells
     * @return list<string|null>|null
     */
    private static function headerOrder(array $cells): ?array
    {
        $order = [];
        $found = 0;

        foreach ($cells as $cell) {
            $folded = trim(mb_strtolower(SearchTextNormalizer::normalize($cell)));
            $column = null;

            foreach (self::HEADERS as $key => $names) {
                if (in_array($folded, array_map(static fn (string $name): string => trim(mb_strtolower(SearchTextNormalizer::normalize($name))), $names), true)) {
                    $column = $key;
                    break;
                }
            }

            $found += $column !== null ? 1 : 0;
            $order[] = $column;
        }

        return $found >= 2 && in_array('course', $order, true) ? $order : null;
    }

    /** @return list<string> */
    private static function cells(string $line, string $separator): array
    {
        return array_map(static fn (string $cell): string => trim($cell, " \t\"'"), explode($separator, $line));
    }
}
