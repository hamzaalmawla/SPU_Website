<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

/**
 * The prompt that turns a visitor's question into search wording.
 *
 * Questions and documents rarely use the same words: a visitor asks "متى
 * موعد التقدم؟" (when is the deadline?) while the ministry's announcement says
 * "اعتباراً من يوم الاثنين ... وحتى نهاية الدوام" (from Monday ... until the end
 * of the working day), and an English question has to find Arabic tables.
 * A small model rewrites the question into the wording official documents
 * use, in Arabic and in English; retrieval then scores each passage against
 * the question and the rewrite and keeps the better match.
 *
 * The rewrite only steers retrieval. It is never shown and never answers.
 */
final class SearchRewrite
{
    public const INSTRUCTIONS = <<<'TEXT'
You prepare search queries for the knowledge base of the Syrian Private University (SPU, الجامعة السورية الخاصة) in Damascus, Syria: its own pages and the official documents of the Syrian Ministry of Higher Education (admission competitions, transfer and change of enrolment, minimum averages, institutes, decrees).

Rewrite the visitor's message into the words such documents would use. Write exactly two lines and nothing else:
1. Arabic: formal Modern Standard Arabic as in ministry announcements, with the likely terms and synonyms (for example: موعد / تاريخ / فترة التقدم / اعتباراً من / وحتى; التحويل المماثل; تغيير القيد; الحد الأدنى; المفاضلة; الرسوم والأقساط; الكلية؛ الاختصاص).
2. English: the same search in English.

Keep names, numbers, years, faculties and universities from the message. Do not answer, explain or add facts. If the message is a greeting or not a question, repeat it unchanged on both lines.
TEXT;

    /**
     * The model's two lines as one search text, or null when it gave
     * nothing usable.
     */
    public static function parse(string $output, string $question): ?string
    {
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim((string) preg_replace('/^\s*(?:\d+[.)]\s*|-\s*)?(?:(?:arabic|english|العربية|الإنجليزية)\s*:\s*)?/iu', '', $line)),
            explode("\n", trim($output)),
        ), static fn (string $line): bool => $line !== ''));

        $text = trim(implode("\n", array_slice($lines, 0, 2)));
        if ($text === '' || mb_strlen($text) > 800 || $text === trim($question)) {
            return null;
        }

        return $text;
    }
}
