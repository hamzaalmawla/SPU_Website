<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Support\Chatbot\SpeechText;
use PHPUnit\Framework\TestCase;

final class SpeechTextTest extends TestCase
{
    public function test_markdown_becomes_plain_sentences(): void
    {
        $markdown = "### Admission requirements\nYou need:\n- the **secondary certificate**\n- a copy of your *ID*\n\nSee [the admission page](/en/admissions) or https://www.spu.edu.sy/en/admissions.";

        $this->assertSame(
            'Admission requirements. You need: the secondary certificate. a copy of your ID. See the admission page or spu.edu.sy.',
            SpeechText::fromMarkdown($markdown),
        );
    }

    public function test_table_rows_are_read_with_their_column_names(): void
    {
        $markdown = "| الكلية | الحد الأدنى |\n|---|---|\n| الطب البشري | 230 |\n| الصيدلة | 220 |";

        $this->assertSame('الكلية: الطب البشري، الحد الأدنى: 230. الكلية: الصيدلة، الحد الأدنى: 220.', SpeechText::fromMarkdown($markdown));
    }

    public function test_code_and_pictographs_are_not_read(): void
    {
        $this->assertSame('Welcome! Ask me anything.', SpeechText::fromMarkdown("Welcome! 🎓 Ask me anything.\n```\nrm -rf /\n```"));
    }

    public function test_segments_start_short_and_respect_the_limits(): void
    {
        $sentence = 'The admission office is open from Sunday to Thursday, from nine in the morning until three in the afternoon.';
        $segments = SpeechText::segments(str_repeat($sentence.' ', 12), 160, 420, 3000);

        $this->assertGreaterThan(2, count($segments));
        $this->assertLessThanOrEqual(160, mb_strlen($segments[0]));
        foreach ($segments as $segment) {
            $this->assertLessThanOrEqual(420, mb_strlen($segment));
            $this->assertNotSame('', $segment);
        }
        $this->assertLessThanOrEqual(3000, mb_strlen(implode(' ', $segments)));
    }

    public function test_a_long_first_sentence_is_cut_at_a_pause(): void
    {
        $text = 'يجب على الطالب المتقدم إلى المفاضلة أن يحمل الشهادة الثانوية العامة بفرعها العلمي، وأن يكون معدله أعلى من الحد الأدنى المعلن للكلية التي يرغب في التسجيل فيها، وأن يقدم أوراقه كاملة خلال الفترة المحددة. شكراً.';
        $segments = SpeechText::segments($text, 80, 420, 3000);

        $this->assertLessThanOrEqual(80, mb_strlen($segments[0]));
        $this->assertSame(preg_replace('/\s+/u', ' ', $text), implode(' ', $segments));
    }

    public function test_the_same_text_is_always_cut_the_same_way(): void
    {
        $text = SpeechText::fromMarkdown('**Fees.** The annual tuition is 4,500,000 SYP. It is paid per semester. Scholarships cover up to 50%.');

        $this->assertSame(SpeechText::segments($text, 40, 60), SpeechText::segments($text, 40, 60));
        $this->assertSame([], SpeechText::segments('   '));
    }

    public function test_it_tells_arabic_from_english(): void
    {
        $this->assertTrue(SpeechText::isArabic('ما هي شروط القبول في SPU؟'));
        $this->assertFalse(SpeechText::isArabic('What are the admission requirements?'));
    }
}
