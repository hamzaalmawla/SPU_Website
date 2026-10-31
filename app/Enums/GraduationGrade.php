<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\SearchTextNormalizer;

/**
 * A graduate's grade (التقدير) as the graduates decision states it. Stored on
 * `alumni.grade`; shown on the public Alumni pages in Arabic and English.
 */
enum GraduationGrade: string
{
    case EXCELLENT = 'excellent';
    case VERY_GOOD = 'very_good';
    case GOOD = 'good';
    case PASS = 'pass';

    /** Reads the Arabic grade the decision prints (امتياز, جيد جداً, جيد, مقبول). */
    public static function fromArabic(string $text): ?self
    {
        $normalized = SearchTextNormalizer::normalize($text);

        return match (true) {
            str_contains($normalized, 'امتياز') => self::EXCELLENT,
            str_contains($normalized, 'جيد جدا') => self::VERY_GOOD,
            str_contains($normalized, 'جيد') => self::GOOD,
            str_contains($normalized, 'مقبول') => self::PASS,
            default => null,
        };
    }

    public function label(string $locale): string
    {
        return __('student_lists.grades.'.$this->value, [], $locale);
    }
}
