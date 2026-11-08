<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The business faculty's name is "كلية الأعمال" / "Business Faculty" (2026-11-08).
 * It was "كلية إدارة الأعمال" / "Faculty of Business Administration", and before
 * that "كلية العلوم الإدارية" / "Faculty of Administrative Sciences".
 *
 * Only text that names the faculty changes. "إدارة الأعمال" / "Business
 * Administration" as a subject (a program, a course, a research theme, a degree
 * such as an MBA, the department) is left as it is, so the bare words are
 * replaced only where a field is known to hold the faculty's name.
 */
final class BusinessFacultyName
{
    public const AR = 'كلية الأعمال';

    public const EN = 'Business Faculty';

    /** Phrases that always name the faculty, in the order they are replaced. */
    private const PHRASES = [
        'كلية إدارة الأعمال' => self::AR,
        'كلية العلوم الإدارية' => self::AR,
        'كلية  الأعمال' => self::AR,
        'FACULTY OF BUSINESS ADMINISTRATION' => 'BUSINESS FACULTY',
        'FACULTY OF ADMINISTRATIVE SCIENCES' => 'BUSINESS FACULTY',
        'Faculty of Business Administration' => self::EN,
        'Faculty Of Business Administration' => self::EN,
        'Faculty of Administrative Sciences' => self::EN,
        'Faculty Of Administrative Sciences' => self::EN,
        'Business Administration Study Plan' => 'Business Faculty Study Plan',
        'Dean of Business Administration' => 'Dean of the Business Faculty',
        'study plans for Business Administration departments' => 'study plans for the Business Faculty programs',
        'Student team & student name, Business Administration' => 'Student team & student name, Business Faculty',
        'Business  Faculty' => self::EN,
    ];

    /** The faculty's short name as page templates wrote it ("Browse alumni records for Business Administration."). */
    private const SENTENCE = '/^(.{1,110}) (in|for|from|of) (?:Business Administration|Administrative Sciences)\.$/u';

    /** Values that are the faculty's name only in a field that holds a faculty name. */
    private const SHORT_NAMES = [
        'إدارة الأعمال' => self::AR,
        'العلوم الإدارية' => self::AR,
        'Business Administration' => self::EN,
        'Administrative Sciences' => self::EN,
    ];

    /** Keys whose value is a faculty name wherever they appear. */
    private const FACULTY_KEYS = ['faculty', 'facultyAr', 'facultyEn'];

    /** Keys whose value is a faculty name when the same item links to the faculty. */
    private const LINKED_KEYS = ['title', 'label'];

    /** Subtrees that hold programs and courses, where the bare words are a subject. */
    private const SUBJECT_KEYS = ['courses', 'departments', 'themes', 'programs'];

    /**
     * Text that names the faculty, renamed. $sentences also renames a page
     * summary that ends in the short name; off inside programs and courses.
     */
    public static function text(string $value, bool $sentences = true): string
    {
        $renamed = strtr($value, self::PHRASES);
        $renamed = (string) preg_replace('/Faculty of Business\b(?! Administration)/u', self::EN, $renamed);

        if ($sentences && preg_match(self::SENTENCE, $renamed, $match) === 1) {
            $renamed = $match[1].' '.$match[2].' the '.self::EN.'.';
        }

        return $renamed;
    }

    /** A field that holds only the faculty's name ("Business Administration" included). */
    public static function name(string $value): string
    {
        $trimmed = trim((string) preg_replace('/\s+/u', ' ', $value));

        return self::SHORT_NAMES[$trimmed] ?? self::text($trimmed);
    }

    /**
     * A decoded JSON payload with every string that names the faculty renamed.
     */
    public static function payload(mixed $data): mixed
    {
        return self::walk($data, null, null, false);
    }

    /**
     * @param  array<array-key, mixed>|null  $siblings
     */
    private static function walk(mixed $data, int|string|null $key, ?array $siblings, bool $inSubject): mixed
    {
        if (is_string($data)) {
            if (! $inSubject && is_string($key) && self::holdsFacultyName($key, $siblings)) {
                return self::name($data);
            }

            return self::text($data, ! $inSubject);
        }

        if (! is_array($data)) {
            return $data;
        }

        $subject = $inSubject || (is_string($key) && in_array($key, self::SUBJECT_KEYS, true));
        $parentIsFaculty = $key === 'faculty';

        foreach ($data as $childKey => $child) {
            if ($parentIsFaculty && $childKey === 'name' && is_string($child) && ! $subject) {
                $data[$childKey] = self::name($child);

                continue;
            }

            $data[$childKey] = self::walk($child, $childKey, $data, $subject);
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>|null  $siblings
     */
    private static function holdsFacultyName(string $key, ?array $siblings): bool
    {
        if (in_array($key, self::FACULTY_KEYS, true)) {
            return true;
        }

        return in_array($key, self::LINKED_KEYS, true) && $siblings !== null && self::linksToFaculty($siblings);
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private static function linksToFaculty(array $item, int $depth = 0): bool
    {
        foreach ($item as $value) {
            if (is_string($value) && ($value === 'business-administration' || $value === 'business' || str_contains($value, '/faculties/business-administration'))) {
                return true;
            }

            if (is_array($value) && $depth < 1 && self::linksToFaculty($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
