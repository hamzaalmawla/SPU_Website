<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Shared\PlainTextFormatter;
use PHPUnit\Framework\TestCase;

final class PlainTextFormatterTest extends TestCase
{
    public function test_numbered_items_on_their_own_lines_become_one_ordered_list(): void
    {
        $blocks = (new PlainTextFormatter)->blocks("1. قسم التشريح: التشريح 1-2-3-4.\n\n2. قسم النسج: الأنسجة 1-2.\n\n3. قسم الحياة.");

        $this->assertSame([[
            'type' => 'ordered',
            'items' => [
                ['marker' => '1.', 'lines' => ['قسم التشريح: التشريح 1-2-3-4.']],
                ['marker' => '2.', 'lines' => ['قسم النسج: الأنسجة 1-2.']],
                ['marker' => '3.', 'lines' => ['قسم الحياة.']],
            ],
        ]], $blocks);
    }

    public function test_a_numbered_list_pasted_as_one_line_is_split_into_items(): void
    {
        $blocks = (new PlainTextFormatter)->blocks('1. قسم التشريح: التشريح 1-2-3-4 وعلم الوراثة. 2. قسم النسج: الأنسجة 1-2. 3. قسم الأطفال: أمراض الأطفال 1-2.');

        $this->assertSame('ordered', $blocks[0]['type']);
        $this->assertSame(
            ['قسم التشريح: التشريح 1-2-3-4 وعلم الوراثة.', 'قسم النسج: الأنسجة 1-2.', 'قسم الأطفال: أمراض الأطفال 1-2.'],
            array_map(fn (array $item): string => $item['lines'][0], $blocks[0]['items']),
        );
    }

    public function test_numbers_inside_a_sentence_are_not_mistaken_for_a_list(): void
    {
        $text = 'Founded in 2003, the university has 7 faculties. Version 2. of the plan follows.';

        $this->assertSame([['type' => 'paragraph', 'lines' => [$text]]], (new PlainTextFormatter)->blocks($text));
    }

    public function test_paragraphs_line_breaks_bullets_and_wrapped_items(): void
    {
        $blocks = (new PlainTextFormatter)->blocks("Intro line\r\nsecond line\r\n\r\n- First\r\n• Second\r\nwraps here\r\n\r\nClosing paragraph");

        $this->assertSame([
            ['type' => 'paragraph', 'lines' => ['Intro line', 'second line']],
            ['type' => 'unordered', 'items' => [
                ['marker' => '•', 'lines' => ['First']],
                ['marker' => '•', 'lines' => ['Second', 'wraps here']],
            ]],
            ['type' => 'paragraph', 'lines' => ['Closing paragraph']],
        ], $blocks);
    }

    public function test_a_single_numbered_line_stays_a_paragraph_and_blank_text_is_empty(): void
    {
        $formatter = new PlainTextFormatter;

        $this->assertSame([['type' => 'paragraph', 'lines' => ['1. Only one step']]], $formatter->blocks('1. Only one step'));
        $this->assertSame([], $formatter->blocks("  \n "));
    }
}
