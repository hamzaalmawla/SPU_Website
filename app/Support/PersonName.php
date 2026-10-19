<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Compares people's names the way a reader would: without academic titles,
 * punctuation, spacing or Arabic letter variants, and allowing a middle name
 * present on one side only.
 */
final class PersonName
{
    private const TITLES = [
        'prof', 'professor', 'dr', 'doctor', 'assoc', 'associate', 'asst', 'assistant', 'eng', 'engineer', 'mr', 'mrs', 'ms', 'mhd',
        'ا', 'د', 'م', 'اد', 'امد', 'استاذ', 'الاستاذ', 'الاستاذه', 'دكتور', 'الدكتور', 'الدكتوره', 'المهندس', 'المهندسه', 'المساعد', 'المساعده', 'المشارك', 'المشاركه',
    ];

    public static function same(string $first, string $second): bool
    {
        $firstTokens = self::tokens($first);
        $secondTokens = self::tokens($second);

        if ($firstTokens === [] || $secondTokens === []) {
            return false;
        }

        if (implode('', $firstTokens) === implode('', $secondTokens)) {
            return true;
        }

        // "Ayman Ali" and "Ayman Mohammad Ali" are the same person, as long as
        // two or more names still agree.
        [$shorter, $longer] = count($firstTokens) <= count($secondTokens) ? [$firstTokens, $secondTokens] : [$secondTokens, $firstTokens];

        return count($shorter) >= 2
            && $shorter[0] === $longer[0]
            && end($shorter) === end($longer)
            && array_diff($shorter, $longer) === [];
    }

    /** @return array<int, string> */
    public static function tokens(string $name): array
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ـ' => '']);
        $name = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

        return array_values(array_filter(
            explode(' ', $name),
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::TITLES, true),
        ));
    }
}
