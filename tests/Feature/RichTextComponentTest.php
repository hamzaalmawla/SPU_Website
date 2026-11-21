<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class RichTextComponentTest extends TestCase
{
    public function test_plain_text_renders_the_same_paragraph_as_before(): void
    {
        $html = Blade::render('<x-public.rich-text class="mt-4 text-sm" :text="$text" />', ['text' => 'One line & more']);

        $this->assertSame('<p class="mt-4 text-sm">One line &amp; more</p>', trim($html));
    }

    public function test_a_pasted_numbered_list_renders_as_an_escaped_ordered_list(): void
    {
        $html = Blade::render('<x-public.rich-text class="mt-4" :text="$text" />', [
            'text' => "Departments:\n\n1. <b>Anatomy</b>\n2. Pathology",
        ]);

        $this->assertStringContainsString('<div class="rich-text mt-4"><p>Departments:</p>', $html);
        $this->assertStringContainsString('<ol class="rich-text__list" role="list">', $html);
        $this->assertStringContainsString('<li><span class="rich-text__marker">1.</span><span class="rich-text__item">&lt;b&gt;Anatomy&lt;/b&gt;</span></li>', $html);
        $this->assertStringContainsString('<li><span class="rich-text__marker">2.</span><span class="rich-text__item">Pathology</span></li>', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_line_breaks_inside_a_paragraph_are_kept(): void
    {
        $html = Blade::render('<x-public.rich-text :text="$text" />', ['text' => "Line one\nLine two"]);

        $this->assertStringContainsString('<p>Line one<br>Line two</p>', $html);
    }
}
