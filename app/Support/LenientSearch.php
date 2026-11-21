<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Matches a visitor's search the way a reader would, not character for
 * character: every typed word has to appear, in any order, but
 *
 *  - Arabic diacritics, tatweel and letter variants (أ إ آ ى ة ؤ ئ) and case
 *    are folded ({@see SearchTextNormalizer});
 *  - punctuation and the Arabic article are ignored ("الصيدلة" finds "صيدلة");
 *  - against names and titles, a word may carry a small typo ("pharmcy",
 *    "صيدلاني") or another Latin spelling ("Ahmed" for "Ahmad", "Mohamed"
 *    for "Muhammad").
 *
 * Callers pass an item's text in both languages, which is what lets a search
 * typed in English find an item on the Arabic page and the other way round.
 */
final class LenientSearch
{
    /** Typed words beyond this are ignored. */
    private const MAX_TERMS = 8;

    /** Words shorter than this are matched exactly; a typo in them is indistinguishable from another word. */
    private const MIN_FUZZY_LENGTH = 4;

    /**
     * The typed words, folded, without punctuation or the Arabic article.
     * One-letter words ("د" of "د.", "a") are dropped unless nothing else is left.
     *
     * @return list<string>
     */
    public static function terms(string $query): array
    {
        $words = self::words(self::normalize(mb_substr($query, 0, 200)));
        $terms = [];
        foreach ($words as $word) {
            $word = self::withoutArticle($word);
            if (mb_strlen($word) >= 2 && ! in_array($word, $terms, true)) {
                $terms[] = $word;
            }
        }

        if ($terms === []) {
            $terms = array_values(array_unique($words));
        }

        return array_slice($terms, 0, self::MAX_TERMS);
    }

    /**
     * Whether every typed word is found. `$texts` is searched for each word as
     * typed; `$names` (titles, people's names) also accepts a near spelling.
     * An empty query matches everything.
     *
     * @param  array<int, mixed>  $texts
     * @param  array<int, mixed>  $names
     */
    public static function matches(string $query, array $texts, array $names = []): bool
    {
        $terms = self::terms($query);
        if ($terms === []) {
            return true;
        }

        $haystack = ' '.self::fold([...$texts, ...$names]).' ';
        $nameWords = null;

        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                continue;
            }

            $nameWords ??= self::words(self::fold($names));
            if (! self::anyWordAlike($term, $nameWords)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one typed word is a near spelling of a stored word: one edit
     * apart (two for long words), the start of a longer word with one edit,
     * or, for Latin words, the same consonant outline.
     */
    public static function wordAlike(string $term, string $word): bool
    {
        if ($term === '' || $word === '') {
            return false;
        }

        if (str_contains($word, $term)) {
            return true;
        }

        $word = self::withoutArticle($word);
        $length = mb_strlen($term);
        // A slip in the first letter is rare, and checking it first keeps a
        // search over thousands of titles from comparing every word.
        if ($length < self::MIN_FUZZY_LENGTH || mb_substr($term, 0, 1) !== mb_substr($word, 0, 1)) {
            return false;
        }

        $allowed = $length >= 8 ? 2 : 1;
        $wordLength = mb_strlen($word);
        // A short stored word is one edit from too many others ("بهاق" and "بها").
        if ($wordLength < self::MIN_FUZZY_LENGTH) {
            return false;
        }

        if (abs($wordLength - $length) <= $allowed && self::distance($term, $word) <= $allowed) {
            return true;
        }

        // Still typing, or a word with a suffix: "pharmac" for "pharmaceutical".
        if ($wordLength > $length && self::distance($term, mb_substr($word, 0, $length)) <= ($length >= 6 ? 1 : 0)) {
            return true;
        }

        if (preg_match('/^[a-z]+$/', $term) === 1 && preg_match('/^[a-z]+$/', $word) === 1) {
            $outline = PersonName::latinOutline($term);

            return mb_strlen($outline) >= 3 && $outline === PersonName::latinOutline($word);
        }

        return false;
    }

    /** @param list<string> $words */
    private static function anyWordAlike(string $term, array $words): bool
    {
        foreach ($words as $word) {
            if (self::wordAlike($term, $word)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $values */
    private static function fold(array $values): string
    {
        $parts = [];
        array_walk_recursive($values, static function (mixed $value) use (&$parts): void {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $parts[] = (string) $value;
            }
        });

        return implode(' ', self::words(self::normalize(implode(' ', $parts))));
    }

    /**
     * The same folding as SearchTextNormalizer::normalize(), done on the whole
     * string at once: that one walks character by character to keep offsets
     * for highlighting, which a search over thousands of items cannot afford.
     */
    private static function normalize(string $value): string
    {
        return strtr(mb_strtolower($value, 'UTF-8'), SearchTextNormalizer::replacements());
    }

    /** @return list<string> */
    private static function words(string $folded): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** "الصيدلة" and "والصيدلة" become "صيدله"; a short word keeps its letters. */
    private static function withoutArticle(string $word): string
    {
        return (string) preg_replace('/^(?:و|ب|ل|ف)?ال(?=\p{L}{3,})/u', '', $word);
    }

    /** Edit distance counted in characters, not bytes, so an Arabic letter is one edit. */
    private static function distance(string $first, string $second): int
    {
        $map = [];
        $encode = static function (string $value) use (&$map): string {
            $out = '';
            foreach (SearchTextNormalizer::characters($value) as $character) {
                $map[$character] ??= chr(count($map) % 256);
                $out .= $map[$character];
            }

            return $out;
        };

        return levenshtein($encode($first), $encode($second));
    }
}
