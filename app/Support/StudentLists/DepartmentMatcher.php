<?php

declare(strict_types=1);

namespace App\Support\StudentLists;

use App\Support\SearchTextNormalizer;

/**
 * Matches the specialization a decision prints ("هندسة البرمجيات ونظم
 * المعلومات") to one of the faculty's departments ("قسم هندسة البرمجيات ونظم
 * المعلومات الذكية") by the distinctive words they share.
 *
 * Words every department carries (قسم, هندسة, الذكية) are ignored, the article
 * and a leading "و" are dropped, and الصنعي counts as الاصطناعي. A match is
 * confident only when it shares enough words and clearly beats the runner-up;
 * otherwise the editor picks the department in the import preview.
 *
 * Words of the faculty's own name (كلية طب الأسنان) and of a degree (دكتور،
 * إجازة) say nothing about a department: "دكتور في طب الأسنان" is the whole
 * faculty's degree and must not match "قسم طب أسنان الأطفال". Such a text is
 * faculty-wide: no department, and nothing for the editor to pick.
 */
final class DepartmentMatcher
{
    private const STOP_WORDS = ['قسم', 'هندسه', 'ذكيه', 'و', 'في', 'من', 'علوم'];

    private const DEGREE_WORDS = ['كليه', 'دكتور', 'دكتوراه', 'اجازه', 'بكالوريوس', 'شهاده', 'جامعيه', 'درجه', 'ماجستير'];

    private const SYNONYMS = ['صنعي' => 'اصطناعي'];

    private const MIN_SCORE = 0.5;

    private const MIN_LEAD = 0.25;

    /**
     * @param  array<int, string>  $departments  department id => Arabic name
     * @return array{departmentId: ?int, confident: bool, score: float, facultyWide: bool}
     */
    public static function match(string $specialization, array $departments, string $facultyName = ''): array
    {
        $ignored = array_merge(self::words($facultyName), self::DEGREE_WORDS);
        $all = self::words($specialization);
        $wanted = array_values(array_diff($all, $ignored));

        if ($all !== [] && $wanted === []) {
            return ['departmentId' => null, 'confident' => true, 'score' => 0.0, 'facultyWide' => true];
        }

        if ($wanted === [] || $departments === []) {
            return ['departmentId' => null, 'confident' => false, 'score' => 0.0, 'facultyWide' => false];
        }

        $scores = [];

        foreach ($departments as $id => $name) {
            $words = array_values(array_diff(self::words($name), $ignored));
            $shared = count(array_intersect($wanted, $words));
            // Dice coefficient: shared words relative to both names' length.
            $scores[$id] = $words === [] ? 0.0 : (2 * $shared) / (count($wanted) + count($words));
        }

        arsort($scores);
        $bestId = (int) array_key_first($scores);
        $best = (float) reset($scores);
        $second = (float) (next($scores) ?: 0.0);

        if ($best <= 0.0) {
            return ['departmentId' => null, 'confident' => false, 'score' => 0.0, 'facultyWide' => false];
        }

        return [
            'departmentId' => $bestId,
            'confident' => $best >= self::MIN_SCORE && $best - $second >= self::MIN_LEAD,
            'score' => round($best, 2),
            'facultyWide' => false,
        ];
    }

    /** @return list<string> */
    public static function words(string $text): array
    {
        $normalized = SearchTextNormalizer::normalize($text);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';
        $words = [];

        foreach (preg_split('/\s+/u', $normalized) ?: [] as $word) {
            // "والنظم" => "النظم" => "نظم"
            if (mb_strlen($word) > 3 && str_starts_with($word, 'و')) {
                $word = mb_substr($word, 1);
            }
            if (mb_strlen($word) > 3 && str_starts_with($word, 'ال')) {
                $word = mb_substr($word, 2);
            }

            $word = self::SYNONYMS[$word] ?? $word;

            if ($word !== '' && ! in_array($word, self::STOP_WORDS, true)) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }
}
