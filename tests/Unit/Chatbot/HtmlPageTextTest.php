<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Support\Chatbot\ExecutionTime;
use App\Support\Chatbot\HtmlPageText;
use PHPUnit\Framework\TestCase;

final class HtmlPageTextTest extends TestCase
{
    public function test_only_the_main_content_is_kept_as_text_with_headings(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html><html lang="ar"><head><title>القبول | الجامعة السورية الخاصة</title><script>var x = 1;</script></head>
            <body>
              <header><nav><a href="/ar/news">الأخبار</a></nav></header>
              <main id="main-content">
                <nav class="breadcrumb"><a href="/ar">الرئيسية</a></nav>
                <h1>القبول والتسجيل</h1>
                <p>يبدأ التسجيل في شهر <strong>تموز</strong>.</p>
                <h2>الوثائق المطلوبة</h2>
                <ul><li>الشهادة الثانوية</li><li>صورة الهوية</li></ul>
                <div class="card">يبدأ التسجيل في شهر تموز.</div>
                <form><input name="q"><button>بحث</button></form>
                <div hidden>نص مخفي</div>
                <span aria-hidden="true">زخرفة</span>
                <a href="/ar/admissions/fees">الأقساط</a>
                <a href="https://other.example/x">خارجي</a>
                <div data-chatbot>widget</div>
              </main>
              <footer>جميع الحقوق محفوظة</footer>
            </body></html>
            HTML;

        $page = HtmlPageText::parse($html);

        $this->assertSame('القبول والتسجيل', $page['title']);
        $this->assertStringContainsString('# القبول والتسجيل', $page['text']);
        $this->assertStringContainsString('يبدأ التسجيل في شهر تموز.', $page['text']);
        $this->assertSame(1, substr_count($page['text'], 'يبدأ التسجيل في شهر تموز.'), 'Repeated card text is kept once.');
        $this->assertStringContainsString('# الوثائق المطلوبة', $page['text']);
        $this->assertStringContainsString('- الشهادة الثانوية', $page['text']);
        foreach (['الأخبار', 'الرئيسية', 'بحث', 'نص مخفي', 'زخرفة', 'widget', 'جميع الحقوق', 'var x'] as $absent) {
            $this->assertStringNotContainsString($absent, $page['text']);
        }
        $this->assertContains('/ar/admissions/fees', $page['links']);
        $this->assertContains('https://other.example/x', $page['links']);
    }

    public function test_the_time_limit_is_never_lowered_or_added_where_there_is_none(): void
    {
        $original = ini_get('max_execution_time');

        try {
            ini_set('max_execution_time', '0');
            ExecutionTime::atLeast(300);
            $this->assertSame('0', ini_get('max_execution_time'), 'An unlimited process (CLI, deploy, tests) must stay unlimited.');

            ini_set('max_execution_time', '900');
            ExecutionTime::atLeast(300);
            $this->assertSame('900', ini_get('max_execution_time'));
        } finally {
            ini_set('max_execution_time', (string) $original);
        }
    }
}
