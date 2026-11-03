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
