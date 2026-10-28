<?php

declare(strict_types=1);

namespace App\Support\StudentLists;

use App\Enums\AcademicSemester;
use App\Enums\GraduationGrade;
use App\Support\SearchTextNormalizer;

/**
 * Turns a faculty council decision (read by DocxTableReader) into student rows.
 *
 * Two formats, both issued every semester:
 *
 * - Graduates (قرار ... خريجين): one table, a header of one or two rows (the
 *   GPA header spans "points" and "percent"), then one row per student.
 * - Top students (قرار ... اوائل): one table in blocks. A full-width title row
 *   ("السنة الرابعة / هندسة البرمجيات ...") starts each block, then a header
 *   row and the ranked students. Empty full-width rows are spacers.
 *
 * Columns are found by their header text, never by position. Rows that cannot
 * be read are returned with issues instead of being dropped silently. Issue
 * values are translation keys under `student_lists.issues`.
 */
final class DecisionListParser
{
    /** Header keyword (normalised) => column role. First match wins. */
    private const GRADUATE_COLUMNS = [
        'الرقم الجامعي' => 'id',
        'اسم الطالب' => 'name',
        'الاختصاص' => 'specialization',
        'النقطي' => 'gpaPoints',
        'المئوي' => 'gpaPercent',
        'التقدير' => 'grade',
        'الفصل' => 'semester',
    ];

    private const TOP_STUDENT_COLUMNS = [
        'الرقم الجامعي' => 'id',
        'اسم الطالب' => 'name',
        'المعدل' => 'gpa',
    ];

    /** Normalised Arabic ordinals of a study year. */
    private const STUDY_YEARS = [
        'الاولي' => 1,
        'الثانيه' => 2,
        'الثالثه' => 3,
        'الرابعه' => 4,
        'الخامسه' => 5,
        'السادسه' => 6,
    ];

    /**
     * @param  array{paragraphs: list<string>, tables: list<list<list<array{text: string, span: int, continuesAbove: bool}>>>}  $document
     * @return array{meta: array{decisionNumber: ?string, academicYear: ?string, semester: ?string}, rows: list<array<string, mixed>>}
     */
    public static function graduates(array $document): array
    {
        $meta = self::meta($document['paragraphs']);
        $rows = [];

        foreach ($document['tables'] as $table) {
            $columns = null;
            $subHeaderIndex = null;

            foreach ($table as $index => $row) {
                $cells = self::expand($row);

                // A header starts the list, and starts it again over a later section of the
                // same table (credit-hour graduates, then "نظام فصلي" ones with their own
                // columns), so each section is read with its own header.
                if (! self::looksLikeStudentRow($cells) && self::rowHas($cells, 'الرقم الجامعي')) {
                    $headerRows = [$cells];
                    // A two-row header: the next row holds sub-headers under spanned cells.
                    $next = isset($table[$index + 1]) ? self::expand($table[$index + 1]) : [];
                    if ($next !== [] && ! self::looksLikeStudentRow($next)) {
                        $headerRows[] = $next;
                        $subHeaderIndex = $index + 1;
                    }
                    $columns = self::columns($headerRows, self::GRADUATE_COLUMNS);

                    continue;
                }

                if ($columns === null || $index === $subHeaderIndex || self::isBlank($cells) || self::isSectionTitle($cells)) {
                    continue;
                }

                $rows[] = self::graduateRow($cells, $columns, count($rows) + 1);
            }

            if ($columns !== null) {
                break;
            }
        }

        // The decision text names one semester; each row repeats the one the
        // student finished in, which wins when the text does not say.
        $rowSemesters = array_values(array_filter(array_column($rows, 'semester')));
        $rowYears = array_values(array_filter(array_column($rows, 'academicYear')));
        $meta['semester'] ??= self::mostCommon($rowSemesters);
        $meta['academicYear'] ??= self::mostCommon($rowYears);

        return ['meta' => $meta, 'rows' => $rows];
    }

    /**
     * @param  array{paragraphs: list<string>, tables: list<list<list<array{text: string, span: int, continuesAbove: bool}>>>}  $document
     * @return array{meta: array{decisionNumber: ?string, academicYear: ?string, semester: ?string}, rows: list<array<string, mixed>>}
     */
    public static function topStudents(array $document): array
    {
        $meta = self::meta($document['paragraphs']);
        $rows = [];

        foreach ($document['tables'] as $table) {
            $grid = array_map(self::expand(...), $table);

            foreach (self::columnGroups($grid) as [$start, $length]) {
                $columns = null;
                $block = ['studyYear' => null, 'specialization' => null, 'semester' => null, 'title' => null];

                foreach ($grid as $row) {
                    $cells = array_slice(array_pad($row, $start + $length, ''), $start, $length);
                    $filled = array_values(array_unique(array_filter(array_map('trim', $cells), static fn (string $text): bool => $text !== '')));

                    if ($filled === []) {
                        continue;
                    }

                    if (self::rowHas($cells, 'الرقم الجامعي')) {
                        $columns = self::columns([$cells], self::TOP_STUDENT_COLUMNS);

                        continue;
                    }

                    // A title row is one (spanned) text across the block, never a student.
                    if (count($filled) === 1 && ! self::looksLikeStudentRow($cells) && ! ctype_digit(self::latinDigits($filled[0]))) {
                        $block = self::block($filled[0]);
                        $columns = null;

                        continue;
                    }

                    if ($columns === null) {
                        continue;
                    }

                    $rows[] = self::topStudentRow($cells, $columns, $block, count($rows) + 1);
                }
            }
        }

        // Blocks that name their semester ("السنة الأولى (الفصل الأول)") are more
        // precise than the decision text, which may name both semesters.
        $rowSemesters = array_values(array_filter(array_column($rows, 'semester')));
        if ($rowSemesters !== []) {
            $meta['semester'] = self::mostCommon($rowSemesters);
        }

        return ['meta' => $meta, 'rows' => $rows];
    }

    /**
     * The column ranges of the student blocks in a top-students table. Most
     * decisions have one block across the table; some put two side by side
     * (first semester on the right, second on the left), each with its own
     * header. A block starts as many columns before its ID column as the
     * first block does (the rank column).
     *
     * @param  list<list<string>>  $grid
     * @return list<array{int, int}> [first column, width]
     */
    private static function columnGroups(array $grid): array
    {
        $keyword = SearchTextNormalizer::normalize('الرقم الجامعي');

        foreach ($grid as $cells) {
            $idColumns = [];
            foreach ($cells as $index => $cell) {
                if (str_contains(SearchTextNormalizer::normalize($cell), $keyword)) {
                    $idColumns[] = $index;
                }
            }

            if ($idColumns === []) {
                continue;
            }

            $offset = $idColumns[0];
            $width = max(array_map('count', $grid));
            $starts = array_map(static fn (int $column): int => max(0, $column - $offset), $idColumns);
            $groups = [];

            foreach ($starts as $index => $start) {
                $end = $starts[$index + 1] ?? $width;
                $groups[] = [$start, $end - $start];
            }

            return $groups;
        }

        return [];
    }

    /**
     * The decision number, academic year and semester from the decision text.
     *
     * @param  list<string>  $paragraphs
     * @return array{decisionNumber: ?string, academicYear: ?string, semester: ?string}
     */
    public static function meta(array $paragraphs): array
    {
        $decisionNumber = null;
        $academicYear = null;
        $semester = null;

        foreach ($paragraphs as $paragraph) {
            $text = self::latinDigits($paragraph);

            if ($decisionNumber === null && preg_match('/رقم\s*\(?\s*(\d+\s*\/\s*\d+|\d+)\s*\)?/u', $text, $match) === 1) {
                $decisionNumber = preg_replace('/\s+/u', '', $match[1]);
            }

            if ($academicYear === null) {
                $academicYear = self::academicYear($text);
            }

            if ($semester === null && str_contains(SearchTextNormalizer::normalize($text), 'فصل')) {
                $semester = AcademicSemester::fromArabic($text)?->value;
            }
        }

        return ['decisionNumber' => $decisionNumber, 'academicYear' => $academicYear, 'semester' => $semester];
    }

    /** "2025/2026" from text holding an academic year; consecutive years only. */
    public static function academicYear(string $text): ?string
    {
        if (preg_match_all('/(\d{4})\s*[\/\-–]\s*(\d{4})/u', self::latinDigits($text), $matches, PREG_SET_ORDER) === false) {
            return null;
        }

        foreach ($matches as $match) {
            if ((int) $match[2] === (int) $match[1] + 1) {
                return $match[1].'/'.$match[2];
            }
        }

        return null;
    }

    /** "2025/2026" from a file name, where the years may run together ("20252026"). */
    public static function academicYearInName(string $name): ?string
    {
        if (preg_match_all('/(?<!\d)(\d{4})\D{0,3}(\d{4})(?!\d)/u', self::latinDigits($name), $matches, PREG_SET_ORDER) === false) {
            return null;
        }

        foreach ($matches as $match) {
            if ((int) $match[2] === (int) $match[1] + 1 && (int) $match[1] >= 2000) {
                return $match[1].'/'.$match[2];
            }
        }

        return null;
    }

    public static function latinDigits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => '',
        ]);
    }

    /**
     * The part of a specialization after the program prefix:
     * "الهندسة المعلوماتية/هندسة البرمجيات" => "هندسة البرمجيات".
     */
    public static function specialization(string $text): string
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/[\/\\\\]/u', $text) ?: []), static fn (string $part): bool => $part !== ''));

        return $parts === [] ? trim($text) : $parts[array_key_last($parts)];
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    private static function graduateRow(array $cells, array $columns, int $line): array
    {
        $value = static fn (string $role): string => isset($columns[$role]) ? trim($cells[$columns[$role]] ?? '') : '';
        $issues = [];

        $id = self::studentId($value('id'));
        $name = self::name($value('name'));
        $specialization = $value('specialization');
        $gpaPoints = self::number($value('gpaPoints'), 0, 4);
        $gpaPercent = self::number($value('gpaPercent'), 0, 100);
        $grade = GraduationGrade::fromArabic($value('grade'));
        $semesterText = $value('semester');

        if ($id === null) {
            $issues[] = 'missing_id';
        }
        if ($name === '') {
            $issues[] = 'missing_name';
        }
        if ($value('gpaPoints') !== '' && $gpaPoints === null || $value('gpaPercent') !== '' && $gpaPercent === null) {
            $issues[] = 'bad_gpa';
        }
        if ($value('grade') !== '' && $grade === null) {
            $issues[] = 'bad_grade';
        }

        return [
            'line' => $line,
            'studentIdentifier' => $id,
            'name' => $name,
            'specialization' => $specialization !== '' ? self::specialization($specialization) : null,
            'gpaPoints' => $gpaPoints,
            'gpaPercent' => $gpaPercent,
            'grade' => $grade?->value,
            'semester' => $semesterText !== '' ? AcademicSemester::fromArabic($semesterText)?->value : null,
            'academicYear' => $semesterText !== '' ? self::academicYear($semesterText) : null,
            'issues' => $issues,
        ];
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     * @param  array{studyYear: ?int, specialization: ?string, semester: ?string, title: ?string}  $block
     * @return array<string, mixed>
     */
    private static function topStudentRow(array $cells, array $columns, array $block, int $line): array
    {
        $value = static fn (string $role): string => isset($columns[$role]) ? trim($cells[$columns[$role]] ?? '') : '';
        $issues = [];

        $id = self::studentId($value('id'));
        $name = self::name($value('name'));
        $gpa = self::number($value('gpa'), 0, 100);
        // The rank column is the first one, headed "ت" or "#".
        $rank = ctype_digit(self::latinDigits(trim($cells[0] ?? ''))) ? (int) self::latinDigits(trim($cells[0])) : null;

        if ($id === null) {
            $issues[] = 'missing_id';
        }
        if ($name === '') {
            $issues[] = 'missing_name';
        }
        // Some decisions list the top students by rank without their GPA.
        if ($gpa === null && $value('gpa') !== '') {
            $issues[] = 'bad_gpa';
        }
        if ($block['studyYear'] === null) {
            $issues[] = 'missing_study_year';
        }

        return [
            'line' => $line,
            'studentIdentifier' => $id,
            'name' => $name,
            'specialization' => $block['specialization'],
            'gpa' => $gpa,
            'rank' => $rank,
            'studyYear' => $block['studyYear'],
            'semester' => $block['semester'],
            'blockTitle' => $block['title'],
            'issues' => $issues,
        ];
    }

    /** @return array{studyYear: ?int, specialization: ?string, semester: ?string, title: string} */
    private static function block(string $title): array
    {
        $normalized = SearchTextNormalizer::normalize($title);
        $studyYear = null;

        foreach (self::STUDY_YEARS as $ordinal => $year) {
            if (str_contains($normalized, 'السنه '.$ordinal)) {
                $studyYear = $year;
                break;
            }
        }

        if ($studyYear === null && preg_match('/السنه\s*(\d)/u', self::latinDigits($normalized), $match) === 1) {
            $studyYear = (int) $match[1];
        }

        $parts = preg_split('/\s*[\/\\\\]\s*/u', $title, 2) ?: [];
        $specialization = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;

        // Only the words after "فصل": the year name ("السنة الثانية") would read as a semester.
        $semesterAt = mb_strpos($normalized, 'فصل');
        $semester = $semesterAt !== false ? AcademicSemester::fromArabic(mb_substr($normalized, $semesterAt))?->value : null;

        return ['studyYear' => $studyYear, 'specialization' => $specialization, 'semester' => $semester, 'title' => $title];
    }

    /**
     * Logical cells of a row, a spanned cell repeated once per grid column it
     * covers so column indexes line up across rows.
     *
     * @param  list<array{text: string, span: int, continuesAbove: bool}>  $row
     * @return list<string>
     */
    private static function expand(array $row): array
    {
        $cells = [];

        foreach ($row as $cell) {
            for ($i = 0; $i < max(1, $cell['span']); $i++) {
                $cells[] = $cell['continuesAbove'] ? '' : $cell['text'];
            }
        }

        return $cells;
    }

    /**
     * Column index per role. With a two-row header a column's label is the
     * top label plus the sub-label beneath it.
     *
     * @param  list<list<string>>  $headerRows
     * @param  array<string, string>  $keywords
     * @return array<string, int>
     */
    private static function columns(array $headerRows, array $keywords): array
    {
        $width = max(array_map('count', $headerRows));
        $columns = [];

        for ($index = 0; $index < $width; $index++) {
            $label = SearchTextNormalizer::normalize(implode(' ', array_map(static fn (array $row): string => $row[$index] ?? '', $headerRows)));

            foreach ($keywords as $keyword => $role) {
                // A spanned header ("المعدل التراكمي") repeats over its columns, so the
                // sub-header beneath it ("النقطي", "المئوي") is what tells them apart.
                if (! isset($columns[$role]) && str_contains($label, SearchTextNormalizer::normalize($keyword))) {
                    $columns[$role] = $index;
                    break;
                }
            }
        }

        return $columns;
    }

    /** @param list<string> $cells */
    private static function rowHas(array $cells, string $keyword): bool
    {
        $keyword = SearchTextNormalizer::normalize($keyword);

        foreach ($cells as $cell) {
            if (str_contains(SearchTextNormalizer::normalize($cell), $keyword)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $cells */
    private static function looksLikeStudentRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (self::studentId($cell) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * A full-width title over a section ("نظام فصلي"): one text across the row and no student.
     *
     * @param  list<string>  $cells
     */
    private static function isSectionTitle(array $cells): bool
    {
        $texts = array_unique(array_filter(array_map('trim', $cells), static fn (string $cell): bool => $cell !== ''));

        return count($texts) === 1 && ! self::looksLikeStudentRow($cells);
    }

    /** @param list<string> $cells */
    private static function isBlank(array $cells): bool
    {
        return array_filter($cells, static fn (string $cell): bool => trim($cell) !== '') === [];
    }

    private static function studentId(string $value): ?string
    {
        $value = preg_replace('/\s+/u', '', self::latinDigits($value)) ?? '';

        return preg_match('/^\d{5,12}$/', $value) === 1 ? $value : null;
    }

    private static function name(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        return mb_substr($value, 0, 180);
    }

    private static function number(string $value, float $min, float $max): ?float
    {
        // Word cells often carry invisible direction marks or non-breaking spaces
        // around a number ("69.27" + U+200F), and some write the decimal as "،".
        $value = (string) preg_replace('/[\p{Cf}\p{Z}\s%]+/u', '', self::latinDigits($value));
        $value = str_replace([',', '،'], '.', $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= $min && $number <= $max ? round($number, 2) : null;
    }

    /** @param list<mixed> $values */
    private static function mostCommon(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $counts = array_count_values(array_map('strval', $values));
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
