<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\SearchTextNormalizer;

/**
 * The semester a faculty decision covers. Stored on `alumni.semester`,
 * `honor_students.semester` and `student_list_imports.semester`, and used by
 * the public Alumni and Honor List semester filter.
 */
enum AcademicSemester: string
{
    case FIRST = 'first';
    case SECOND = 'second';
    case SUMMER = 'summer';

    /** Reads "الفصل الأول", "الفصل الثاني" or "الفصل الصيفي" from Arabic text. */
    public static function fromArabic(string $text): ?self
    {
        $normalized = SearchTextNormalizer::normalize($text);

        return match (true) {
            str_contains($normalized, 'الصيفي') => self::SUMMER,
            str_contains($normalized, 'الثاني') => self::SECOND,
            str_contains($normalized, 'الاول') => self::FIRST,
            default => null,
        };
    }

    public function label(string $locale): string
    {
        return __('student_lists.semesters.'.$this->value, [], $locale);
    }

    /** Later semesters first when lists are sorted newest first. */
    public function order(): int
    {
        return match ($this) {
            self::FIRST => 1,
            self::SECOND => 2,
            self::SUMMER => 3,
        };
    }
}
