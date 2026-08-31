<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Folds text into the single comparable form used by site search.
 *
 * Arabic is written with optional diacritics and with several interchangeable
 * orthographic variants, so a visitor searching for "احمد" must still find a
 * record stored as "أَحْمَد". Both the indexed text and the query are folded
 * through this class, so the comparison never happens in the database's
 * collation: MariaDB's utf8mb4_unicode_ci and sqlite's ASCII-only LIKE fold
 * differently, and relying on either would make tests and production disagree.
 * Normalization happens once, in PHP, at index time.
 *
 * The folding is deliberately a **single-character** replacement table, which
 * is what makes {@see self::normalizeWithOffsets()} possible: search can locate
 * a match in the folded text and still highlight the corresponding run of the
 * original text, which may be longer (diacritics) or spelled differently
 * (hamza variants).
 *
 * Folding rules:
 *  - strip tashkeel / harakat: U+064B-U+0652 and the superscript alef U+0670
 *  - strip tatweel (kashida) U+0640
 *  - alef family    أ إ آ ٱ  -> ا
 *  - alef maqsura   ى        -> ي
 *  - ta marbuta     ة        -> ه
 *  - waw hamza      ؤ        -> و
 *  - ya hamza       ئ        -> ي
 *  - Arabic-Indic digits ٠-٩ -> 0-9
 *  - lowercase, so Latin content folds too
 *
 * This class is intentionally free of any database or framework dependency.
 */
final class SearchTextNormalizer
{
    /**
     * Single-character folding table, applied after lowercasing.
     *
     * Order is irrelevant because no replacement value is itself a key.
     *
     * @var array<string, string>
     */
    private const REPLACEMENTS = [
        // Tashkeel / harakat (U+064B - U+0652).
        "\u{064B}" => '', // fathatan
        "\u{064C}" => '', // dammatan
        "\u{064D}" => '', // kasratan
        "\u{064E}" => '', // fatha
        "\u{064F}" => '', // damma
        "\u{0650}" => '', // kasra
        "\u{0651}" => '', // shadda
        "\u{0652}" => '', // sukun
        "\u{0670}" => '', // superscript alef
        "\u{0640}" => '', // tatweel / kashida

        // Hamza and orthographic variants.
        "\u{0623}" => "\u{0627}", // أ -> ا
        "\u{0625}" => "\u{0627}", // إ -> ا
        "\u{0622}" => "\u{0627}", // آ -> ا
        "\u{0671}" => "\u{0627}", // ٱ -> ا
        "\u{0649}" => "\u{064A}", // ى -> ي
        "\u{0629}" => "\u{0647}", // ة -> ه
        "\u{0624}" => "\u{0648}", // ؤ -> و
        "\u{0626}" => "\u{064A}", // ئ -> ي

        // Arabic-Indic digits.
        "\u{0660}" => '0',
        "\u{0661}" => '1',
        "\u{0662}" => '2',
        "\u{0663}" => '3',
        "\u{0664}" => '4',
        "\u{0665}" => '5',
        "\u{0666}" => '6',
        "\u{0667}" => '7',
        "\u{0668}" => '8',
        "\u{0669}" => '9',
    ];

    /**
     * The folding table, exposed for tests and for any caller that needs to
     * reproduce the same folding elsewhere.
     *
     * @return array<string, string>
     */
    public static function replacements(): array
    {
        return self::REPLACEMENTS;
    }

    /**
     * Fold a string for comparison, collapsing whitespace runs to one space.
     */
    public static function normalize(string $value): string
    {
        $normalized = self::normalizeWithOffsets($value)['normalized'];
        $collapsed = preg_replace('/\s+/u', ' ', $normalized);

        return trim(is_string($collapsed) ? $collapsed : $normalized);
    }

    /**
     * Split a folded query into its distinct terms, longest first so that the
     * most specific term wins when highlighting overlapping matches.
     *
     * @return list<string>
     */
    public static function terms(string $value): array
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        $terms = array_values(array_unique(array_filter(
            explode(' ', $normalized),
            static fn (string $term): bool => $term !== '',
        )));

        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $terms;
    }

    /**
     * Reduce HTML or rich text to the plain, single-spaced text that gets
     * indexed. Tags, entities, scripts and styles never reach the index, so a
     * search for "div" or "https" cannot match markup.
     */
    public static function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $value) ?? $value;
        $value = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|tr|td|th)>#i', ' ', $value) ?? $value;
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Fold a string while recording, for every character of the folded result,
     * the index of the original character it came from.
     *
     * Whitespace is preserved here (unlike {@see self::normalize()}) so the
     * offsets stay aligned with the original string.
     *
     * @return array{normalized: string, characters: list<string>, offsets: list<int>}
     */
    public static function normalizeWithOffsets(string $value): array
    {
        $characters = self::characters($value);
        $normalizedCharacters = [];
        $offsets = [];

        foreach ($characters as $index => $character) {
            $folded = self::foldCharacter($character);

            if ($folded === '') {
                continue;
            }

            foreach (self::characters($folded) as $foldedCharacter) {
                $normalizedCharacters[] = $foldedCharacter;
                $offsets[] = $index;
            }
        }

        return [
            'normalized' => implode('', $normalizedCharacters),
            'characters' => $characters,
            'offsets' => $offsets,
        ];
    }

    /**
     * Split a UTF-8 string into its characters.
     *
     * @return list<string>
     */
    public static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : [];
    }

    /**
     * Escape the LIKE metacharacters in a folded term.
     *
     * '!' is used as the escape character rather than the more usual backslash:
     * MariaDB processes backslashes inside string literals and sqlite does not,
     * so "ESCAPE '\'" cannot be written once and mean the same thing on both.
     */
    public static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /**
     * Fold one character: lowercase it, then apply the replacement table.
     */
    private static function foldCharacter(string $character): string
    {
        $lowered = mb_strtolower($character, 'UTF-8');

        return self::REPLACEMENTS[$lowered] ?? $lowered;
    }
}
