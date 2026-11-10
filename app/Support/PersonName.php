<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Compares people's names the way a reader would: without academic titles,
 * punctuation, spacing, diacritics or Arabic letter variants, and allowing a
 * middle name present on one side only.
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

            $skeleton = self::wordSkeleton($word);
            if ($skeleton !== '') {
                $outline[] = $skeleton;
            }
        }

        return $outline;
    }

    /** The consonant outline of one lowercase Latin word, for search: see wordSkeleton(). */
    public static function latinOutline(string $word): string
    {
        return self::wordSkeleton(strtolower($word));
    }

    /** Consonant outline of one lowercase Latin word: "ahmed" and "ahmad" both give "ahmd". */
    private static function wordSkeleton(string $word): string
    {
        $word = (string) preg_replace('/^(?:al|el)(?=[a-z]{3,})/', '', $word);
        $word = strtr($word, ['ou' => 'u', 'oo' => 'u', 'ee' => 'i', 'ph' => 'f', 'q' => 'k', 'c' => 'k', 'dh' => 'd', 'th' => 't', 'sh' => 's', 'kh' => 'k', 'gh' => 'g', 'z' => 's', 'j' => 'g']);
        $word = (string) preg_replace('/(.)\1+/', '$1', $word);

        return $word === '' ? '' : $word[0].preg_replace('/[aeiouyhw]/', '', substr($word, 1));
    }

    /**
     * How well a name typed so far matches a stored name, for suggestions
     * while someone types: 4 the same words, 3 same(), 2 likelySame(), 1 the
     * typed words start the name's words ("Ahm" for "Ahmad Ali", "Mohamed
     * Kh" for "Muhammad Khaled"), 0 no match.
     */
    public static function matchRank(string $typed, string $name): int
    {
        $typedTokens = self::tokens($typed);
        $nameTokens = self::tokens($name);

        if ($typedTokens === [] || $nameTokens === [] || mb_strlen(implode('', $typedTokens)) < 3) {
            return 0;
        }

        if (implode('', $typedTokens) === implode('', $nameTokens)) {
            return 4;
        }

        if (count($typedTokens) >= 2 && self::same($typed, $name)) {
            return 3;
        }

        if (count($typedTokens) >= 2 && self::likelySame($typed, $name)) {
            return 2;
        }

        return self::startsLike($typedTokens, $nameTokens) ? 1 : 0;
    }

    /**
     * Every typed word is one of the name's words, in any spelling variant,
     * and the last typed word may still be incomplete.
     *
     * @param  array<int, string>  $typedTokens
     * @param  array<int, string>  $nameTokens
     */
    private static function startsLike(array $typedTokens, array $nameTokens): bool
    {
        $available = $nameTokens;
        $last = count($typedTokens) - 1;

        foreach (array_values($typedTokens) as $index => $typedToken) {
            $found = null;
            foreach ($available as $key => $nameToken) {
                if ($index === $last ? self::wordStartsLike($typedToken, $nameToken) : self::wordsAlike($typedToken, $nameToken)) {
                    $found = $key;
                    break;
                }
            }

            if ($found === null) {
                return false;
            }

            unset($available[$found]);
        }

        return true;
    }

    private static function wordsAlike(string $first, string $second): bool
    {
        if ($first === $second || self::withoutArticle($first) === self::withoutArticle($second)) {
            return true;
        }

        return self::isLatin($first) && self::isLatin($second) && self::wordSkeleton($first) === self::wordSkeleton($second);
    }

    private static function wordStartsLike(string $typed, string $word): bool
    {
        if (str_starts_with($word, $typed) || str_starts_with(self::withoutArticle($word), self::withoutArticle($typed))) {
            return true;
        }

        return self::isLatin($typed) && self::isLatin($word) && mb_strlen($typed) >= 2
            && str_starts_with(self::wordSkeleton($word), self::wordSkeleton($typed));
    }

    /** "الحسن" and "حسن", "alhasan" and "hasan" are one name. */
    private static function withoutArticle(string $word): string
    {
        return (string) preg_replace('/^(?:ال|al|el)(?=\p{L}{3,})/u', '', $word);
    }

    private static function isLatin(string $word): bool
    {
        return preg_match('/^[a-z]+$/', $word) === 1;
    }

    /** @return array<int, string> */
    public static function tokens(string $name): array
    {
        // Folds diacritics, tatweel, hamza forms (أ إ آ ؤ ئ), ة, ى and case.
        $name = SearchTextNormalizer::normalize($name);
        $name = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);

        return array_values(array_filter(
            explode(' ', $name),
            static fn (string $token): bool => $token !== '' && ! in_array($token, self::TITLES, true),
        ));
    }
}
