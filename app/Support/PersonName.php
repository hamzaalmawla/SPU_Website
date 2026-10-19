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

    /**
     * Probably the same person written in another Latin spelling ("Nizar
     * Aldaher" and "Nizar Daher", "Yousra Haddeh" and "Yousra Haddah"): first
     * and last names agree once vowels, doubled letters and common
     * transliteration variants are set aside. Looser than same(), so it is a
     * suggestion for an editor to confirm, never applied on its own.
     */
    public static function likelySame(string $first, string $second): bool
    {
        if (self::same($first, $second)) {
            return true;
        }

        $firstSkeleton = self::skeleton($first);
        $secondSkeleton = self::skeleton($second);

        return count($firstSkeleton) >= 2
            && count($secondSkeleton) >= 2
            && $firstSkeleton[0] === $secondSkeleton[0]
            && end($firstSkeleton) === end($secondSkeleton);
    }

    /** @return list<string> Consonant outlines of a Latin name's words, without titles. */
    private static function skeleton(string $name): array
    {
        $words = preg_split('/[^A-Za-z]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $outline = [];

        foreach ($words as $word) {
            $word = strtolower($word);
            if (in_array($word, [...self::TITLES, 'mohd', 'md', 'phd', 'al', 'el', 'abu', 'bin'], true)) {
                continue;
            }

            $word = (string) preg_replace('/^(?:al|el)(?=[a-z]{3,})/', '', $word);
            $word = strtr($word, ['ou' => 'u', 'oo' => 'u', 'ee' => 'i', 'ph' => 'f', 'q' => 'k', 'c' => 'k', 'dh' => 'd', 'th' => 't', 'sh' => 's', 'kh' => 'k', 'gh' => 'g', 'z' => 's', 'j' => 'g']);
            $word = (string) preg_replace('/(.)\1+/', '$1', $word);

            if ($word !== '') {
                $outline[] = $word[0].preg_replace('/[aeiouyhw]/', '', substr($word, 1));
            }
        }

        return $outline;
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
