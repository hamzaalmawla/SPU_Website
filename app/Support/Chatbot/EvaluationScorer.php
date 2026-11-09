<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use App\Support\SearchTextNormalizer;

/**
 * Scores one answer of the golden-question evaluation (chatbot:evaluate):
 * which expected facts it contains and whether the passages it was given
 * came from the expected documents. Pure functions, no model calls: a fact
 * is a set of accepted spellings, matched after folding (Arabic letters,
 * Arabic-Indic digits, case), and a number only matches as a whole number.
 */
final class EvaluationScorer
{
    /**
     * @param  list<list<string>>  $groups  each group: accepted spellings of one fact
     * @return array{found: int, total: int, missing: list<string>}
     */
    public static function facts(string $answer, array $groups): array
    {
        $text = SearchTextNormalizer::normalize($answer);
        $found = 0;
        $missing = [];

        foreach ($groups as $spellings) {
            $hit = false;
            foreach ($spellings as $spelling) {
                if (self::contains($text, SearchTextNormalizer::normalize($spelling))) {
                    $hit = true;
                    break;
                }
            }
            $hit ? $found++ : $missing[] = (string) ($spellings[0] ?? '');
        }

        return ['found' => $found, 'total' => count($groups), 'missing' => $missing];
    }

    /**
     * How many source groups one of the given passage titles matches.
     *
     * @param  list<string>  $titles  of the passages the model was given
     * @param  list<list<string>>  $groups  each group: title fragments, any of which counts
     * @return array{found: int, total: int}
     */
    public static function sources(array $titles, array $groups): array
    {
        $titles = array_map(SearchTextNormalizer::normalize(...), $titles);
        $found = 0;

        foreach ($groups as $fragments) {
            foreach ($fragments as $fragment) {
                $fragment = SearchTextNormalizer::normalize($fragment);
                foreach ($titles as $title) {
                    if ($fragment !== '' && str_contains($title, $fragment)) {
                        $found++;

                        continue 3;
                    }
                }
            }
        }

        return ['found' => $found, 'total' => count($groups)];
    }

    private static function contains(string $text, string $fact): bool
    {
        if ($fact === '') {
            return false;
        }

        // A number is matched whole: 30 is not found in 300 or 3.30.
        if (preg_match('/^\d+(?:[.,]\d+)?$/', $fact) === 1) {
            return preg_match('/(?<![\d.,])'.preg_quote($fact, '/').'(?![\d]|[.,]\d)/u', $text) === 1;
        }

        return str_contains($text, $fact);
    }
}
