<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Support\Chatbot\TextChunker;
use PHPUnit\Framework\TestCase;

final class TextChunkerTest extends TestCase
{
    public function test_short_text_is_one_passage_with_its_heading(): void
    {
        $chunks = TextChunker::chunk([['page' => null, 'text' => "# شروط القبول\nيقبل الطالب الحاصل على الشهادة الثانوية بمعدل لا يقل عن 60%."]]);

        $this->assertCount(1, $chunks);
        $this->assertSame('شروط القبول', $chunks[0]['heading']);
        $this->assertStringContainsString('60%', $chunks[0]['content']);
    }

    public function test_long_text_splits_under_the_size_with_overlap_and_never_inside_a_word(): void
    {
        $sentences = [];
        for ($index = 1; $index <= 60; $index++) {
            $sentences[] = "Sentence number {$index} describes the tuition of the faculty of pharmacy in detail.";
        }
        $chunks = TextChunker::chunk([['page' => 3, 'text' => implode("\n", $sentences)]], 400, 90);

        $this->assertGreaterThan(5, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(400, mb_strlen($chunk['content']));
            $this->assertSame(3, $chunk['page']);
            $this->assertMatchesRegularExpression('/^Sentence number \d+/', $chunk['content']);
        }

        // Each passage starts with the end of the one before it.
        $lastLineOfFirst = substr($chunks[0]['content'], (int) strrpos($chunks[0]['content'], "\n") + 1);
        $this->assertStringStartsWith($lastLineOfFirst, $chunks[1]['content']);
    }

    public function test_a_paragraph_longer_than_a_passage_is_cut_at_sentences_and_words(): void
    {
        $paragraph = str_repeat('الرسوم الدراسية للفصل الأول مئة ألف ليرة سورية. ', 80);
        $chunks = TextChunker::chunk([['page' => null, 'text' => $paragraph]], 300, 60);

        $this->assertGreaterThan(5, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(300, mb_strlen($chunk['content']));
            $words = preg_split('/\s+/u', trim($paragraph));
            $first = preg_split('/\s+/u', $chunk['content'])[0];
            $last = preg_split('/\s+/u', $chunk['content']);
            $this->assertContains($first, $words, 'A passage starts with a cut word.');
            $this->assertContains(end($last), $words, 'A passage ends with a cut word.');
        }
    }

    public function test_pages_stay_separate_and_headings_carry_across_pages(): void
    {
        $chunks = TextChunker::chunk([
            ['page' => 1, 'text' => "# Faculty of Medicine\nSix years of study."],
            ['page' => 2, 'text' => 'Clinical training starts in the fourth year.'],
        ]);

        $this->assertCount(2, $chunks);
        $this->assertSame([1, 2], array_column($chunks, 'page'));
        $this->assertSame('Faculty of Medicine', $chunks[1]['heading']);
    }

    public function test_empty_and_trivial_text_gives_nothing(): void
    {
        $this->assertSame([], TextChunker::chunk([['page' => null, 'text' => "  \n \n- "]]));
    }
}
