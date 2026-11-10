<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use App\Support\SearchTextNormalizer;

/**
 * Keyword overlap between a question and a passage, used to re-rank the
 * vector search's candidates. Embeddings can miss exact names, codes and
 * numbers ("IT204", "2026", a faculty name); this lifts passages that
 * contain them.
 *
 * Both sides are folded with SearchTextNormalizer (Arabic hamza, ta marbuta,
 * diacritics, digits) and stripped of common Arabic and English words and of
 * the Arabic definite article.
 */
final class KeywordScorer
{
    private const STOPWORDS = [
        // English
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'are', 'was', 'be', 'what', 'which',
        'who', 'how', 'when', 'where', 'why', 'do', 'does', 'can', 'i', 'you', 'me', 'my', 'it', 'at', 'by', 'with',
        'about', 'tell', 'please', 'there', 'any', 'this', 'that', 'from', 'your', 'we', 'our', 'spu', 'university',
        // Arabic (folded forms)
        'في', 'من', 'الي', 'علي', 'عن', 'ما', 'ماذا', 'هل', 'هو', 'هي', 'كيف', 'متي', 'اين', 'لماذا', 'كم', 'او', 'و',
        'ان', 'لا', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'مع', 'كل', 'اي', 'انا', 'لي', 'عندي', 'يمكن', 'اريد', 'بدي',
        'شو', 'لو', 'سمحت', 'ممكن', 'جامعه', 'الجامعه', 'السوريه', 'الخاصه',
    ];

    /**
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $terms = [];
        foreach (SearchTextNormalizer::terms(self::words($text)) as $term) {
            $term = self::stem($term);
            if (mb_strlen($term) < 2 || in_array($term, self::STOPWORDS, true)) {
                continue;
            }
            $terms[$term] = true;
        }

        // Numeric keys ("2026") come back from array_keys() as integers.
        return array_map('strval', array_keys($terms));
    }

    /**
     * Share of the question's terms found in the passage (0-1).
     *
     * @param  list<string>  $queryTerms  from terms()
     */
    public static function score(array $queryTerms, string $normalizedPassage): float
    {
        if ($queryTerms === []) {
            return 0.0;
        }

        $found = 0;
        foreach ($queryTerms as $term) {
            if (str_contains($normalizedPassage, $term)) {
                $found++;
            }
        }

        return $found / count($queryTerms);
    }

    /**
     * The written forms each term counts under, as bits: a term from terms()
     * and the same word with an attached و ف ب ل ك (the article is already
     * stripped) or an English plural ending. A folded passage
     * (normalizePassage) is then matched word by word with one lookup each,
     * whatever the number of terms.
     *
     * @param  array<string, int>  $termBits  term => bit
     * @return array<string, int> form => bits of the terms it stands for
     */
    public static function formBits(array $termBits): array
    {
        $forms = [];
        foreach ($termBits as $term => $bit) {
            $term = (string) $term;
            foreach (['', 'و', 'ف', 'ب', 'ل', 'ك', 'وب', 'ول', 'فل'] as $prefix) {
                $forms[$prefix.$term] = ($forms[$prefix.$term] ?? 0) | (1 << $bit);
            }
            foreach (['s', 'es'] as $suffix) {
                $forms[$term.$suffix] = ($forms[$term.$suffix] ?? 0) | (1 << $bit);
            }
        }

        return $forms;
    }

    /**
     * Bits of the terms a folded passage contains (see formBits()).
     *
     * @param  array<string, int>  $formBits
     */
    public static function passageBits(string $normalizedPassage, array $formBits): int
    {
        $mask = 0;
        foreach (explode(' ', $normalizedPassage) as $word) {
            if (isset($formBits[$word])) {
                $mask |= $formBits[$word];
            }
        }

        return $mask;
    }

    /**
     * How telling a term is: BM25's inverse document frequency, high for a
     * term few passages contain (a name, a code), near zero for one most
     * passages contain ("الطالب").
     */
    public static function idf(int $passagesWithTerm, int $passages): float
    {
        return log(1 + ($passages - $passagesWithTerm + 0.5) / ($passagesWithTerm + 0.5));
    }

    /**
     * Share (0-1) of a search's term weight that a passage contains. Terms
     * are bits: `$searchMask` the search's, `$passageMask` those found in
     * the passage; `$weights[bit]` each term's idf().
     *
     * @param  array<int, float>  $weights
     */
    public static function weightedShare(int $searchMask, int $passageMask, array $weights): float
    {
        $all = 0.0;
        $matched = 0.0;
        foreach ($weights as $bit => $weight) {
            if (($searchMask >> $bit) & 1) {
                $all += $weight;
                if (($passageMask >> $bit) & 1) {
                    $matched += $weight;
                }
            }
        }

        return $all > 0.0 ? $matched / $all : 0.0;
    }

    /**
     * The passage text folded once at index time.
     */
    public static function normalizePassage(string $text): string
    {
        return implode(' ', array_map(self::stem(...), SearchTextNormalizer::terms(self::words($text))));
    }

    private static function words(string $text): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}\p{Mn}]+/u', ' ', $text);
    }

    private static function stem(string $term): string
    {
        // Arabic definite article and its common attached prepositions.
        foreach (['وال', 'بال', 'كال', 'فال', 'لل', 'ال'] as $prefix) {
            if (mb_strlen($term) > mb_strlen($prefix) + 2 && str_starts_with($term, $prefix)) {
                return mb_substr($term, mb_strlen($prefix));
            }
        }

        return $term;
    }
}
