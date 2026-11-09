<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\Support\Chatbot\ChatbotPrompt;
use App\Support\Chatbot\SearchRewrite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The same question gets the same answer, every part of a question is
 * answered, and a name or number is found wherever it is written.
 */
final class ChatbotConsistencyTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    public function test_the_rewrite_splits_a_question_into_one_search_per_part(): void
    {
        $hint = SearchRewrite::parse("التحويل المماثل الساعات المنجزة الصيدلة || equivalent transfer pharmacy completed hours\nتحويل المعدل النقطي 3.1 إلى معدل مئوي || convert GPA 3.1 to percentage", 'q');

        $this->assertSame([
            "التحويل المماثل الساعات المنجزة الصيدلة\nequivalent transfer pharmacy completed hours",
            "تحويل المعدل النقطي 3.1 إلى معدل مئوي\nconvert GPA 3.1 to percentage",
        ], SearchRewrite::parts($hint));

        // The older two-line form (Arabic, then English) is one search.
        $this->assertSame(["موعد التقدم\nApplication dates"], SearchRewrite::parts(SearchRewrite::parse("Arabic: موعد التقدم\nEnglish: Application dates", 'q')));
        $this->assertCount(SearchRewrite::MAX_PARTS, SearchRewrite::parts("a || A\nb || B\nc || C\nd || D"));
        $this->assertSame([], SearchRewrite::parts(null));

        // A model that leaves "||" out still gives one search per part.
        $this->assertSame([
            "الساعات المنجزة للتحويل\nhours for transfer",
            "المعدل المئوي المكافئ\nequivalent percentage",
        ], SearchRewrite::parts(SearchRewrite::parse("الساعات المنجزة للتحويل\nhours for transfer\nالمعدل المئوي المكافئ\nequivalent percentage", 'q')));
    }

    public function test_the_best_passages_bring_the_passage_after_them(): void
    {
        config(['chatbot.knowledge.chunk_characters' => 200, 'chatbot.knowledge.chunk_overlap' => 0]);
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->addKnowledge($user, 'قرار 448', "# المادة 16\nد-1- الإعفاء من الساعات المعتمدة لحامل الإجازة لا يزيد على 30% إذا كانت الإجازة من كلية أخرى أو من جامعة أخرى منسجمة مع الاختصاص الذي يرغب القيد فيه في الجامعة الخاصة.\n\nد-2- وفي حال القبول ضمن القسم ذاته في اختصاص ثان يصل الحد إلى خمسين بالمئة من مجموع الساعات.");

        $contents = array_map(
            static fn ($passage): string => $passage->content,
            app(ChatbotKnowledgeServiceInterface::class)->retrieve('الإعفاء لحامل الإجازة الجامعية من كلية أخرى', 'sk-test', 'ar')->passages,
        );

        $this->assertNotEmpty(array_filter($contents, static fn (string $content): bool => str_contains($content, 'خمسين')));
    }

    public function test_every_part_of_a_question_gets_its_own_passages(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        // Many passages on the first topic, one on the second: one search
        // for both would fill every slot with the first.
        $transfer = '';
        for ($index = 1; $index <= 12; $index++) {
            $transfer .= "# الفقرة {$index}\nالتحويل المماثل إلى كلية الصيدلة يشترط إنجاز 26 ساعة معتمدة في الجامعة المحول منها، البند {$index}.\n\n";
        }
        $this->addKnowledge($user, 'إعلان التحويل المماثل', $transfer);
        $this->addKnowledge($user, 'قرار 230 نظام التقويم', "# المعدل المئوي المكافئ\nالمعدل المئوي = (المعدل النقطي × 20) + 20، وتقدير جيد جداً من 2.75 إلى 3.24.");
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $question = 'كم ساعة يجب أن أنجز للتحويل المماثل إلى الصيدلة؟ وكم يعادل معدل نقطي 3.1 مئوياً؟';
        $hint = "التحويل المماثل كلية الصيدلة الساعات المعتمدة المنجزة || equivalent transfer pharmacy completed credit hours\nتحويل المعدل النقطي إلى المعدل المئوي المكافئ تقدير || convert point GPA to equivalent percentage grade";

        $titles = array_map(static fn ($passage): string => $passage->documentTitle, $service->retrieve($question, 'sk-test', 'ar', $hint)->passages);

        $this->assertContains('قرار 230 نظام التقويم', $titles);
        $this->assertContains('إعلان التحويل المماثل', $titles);
        $this->assertLessThanOrEqual((int) config('chatbot.retrieval.top_k_multi') + (int) config('chatbot.retrieval.neighbors'), count($titles));
    }

    public function test_the_rules_ask_for_every_part_and_the_text_in_force(): void
    {
        $instructions = ChatbotPrompt::instructions('SPU', 'ar', ['phone' => null, 'email' => null, 'website' => 'https://example.test'], '', 'today');

        $this->assertStringContainsString('Answer EVERY part', $instructions);
        $this->assertStringContainsString('text in force', $instructions);
        $this->assertStringContainsString('give each rule with its condition', $instructions);
        $this->assertContains('coverage', ChatbotPrompt::schema()['required']);
        $this->assertSame('full', ChatbotPrompt::parseReply('{"category":"university","answer":"x","memory":""}')['coverage'] ?? null);
    }

    public function test_a_name_is_found_even_when_its_meaning_ranks_it_out_of_the_candidates(): void
    {
        config(['chatbot.retrieval.candidates' => 2]);
        $user = $this->adminUser();
        $this->saveApiKey($user);
        for ($index = 1; $index <= 5; $index++) {
            $this->addKnowledge($user, "Engineers {$index}", "# Network\nمهندس الشبكات في المديرية رقم {$index}");
        }
        $names = [];
        for ($index = 1; $index <= 40; $index++) {
            $names[] = "الموظف{$index} قسم{$index} تحويلة 10{$index}";
        }
        $names[20] = 'عامر خورشيد مهندس ذكاء اصطناعي تحويلة 1801';
        $this->addKnowledge($user, 'دليل الهواتف', "# الأسماء\n".implode("\n", $names));

        $titles = array_map(
            static fn ($passage): string => $passage->documentTitle,
            app(ChatbotKnowledgeServiceInterface::class)->retrieve('من هو عامر خورشيد المهندس', 'sk-test', 'ar')->passages,
        );

        $this->assertContains('دليل الهواتف', $titles);
    }

    public function test_answers_ask_for_temperature_zero_and_a_model_that_refuses_it_is_asked_again_without(): void
    {
        $this->liveAssistant();

        app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'What are the admission office hours?', adminTest: true));
        $this->assertEquals(0.0, $this->openAi->responseRequests[0]['temperature'] ?? 'missing');
        $this->assertEquals(0.0, $this->openAi->rewriteRequests[0]['temperature'] ?? 'missing');

        $this->openAi->responseRequests = [];
        $this->openAi->rejectTemperature = true;
        $reply = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'How much is Pharmacy tuition?', adminTest: true));

        $this->assertSame('ok', $reply->status);
        $this->assertCount(2, $this->openAi->responseRequests);
        $this->assertArrayNotHasKey('temperature', $this->openAi->responseRequests[1]);

        // Remembered: the next answer goes without at once.
        app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'When is the office open?', adminTest: true));
        $this->assertCount(3, $this->openAi->responseRequests);
    }

    public function test_an_unanswered_question_is_searched_again_wider_and_never_cached(): void
    {
        config(['chatbot.retrieval.top_k' => 1, 'chatbot.retrieval.min_similarity' => 0.0]);
        $this->liveAssistant();
        $calls = 0;
        $this->openAi->coverage = static function () use (&$calls): string {
            return ++$calls === 1 ? 'none' : 'full';
        };
        $this->openAi->answerWith = static fn (array $payload): string => str_contains((string) end($payload['input'])['content'], '[2]') ? 'Found it.' : 'I do not have that yet.';

        $reply = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'What are the admission office hours?', visitorKey: 'v1'));

        $this->assertSame('Found it.', $reply->answer);
        $this->assertCount(2, $this->openAi->responseRequests);

        // A first answer that did not find everything is never shared from cache.
        $calls = 0;
        $this->openAi->coverage = 'none';
        $this->openAi->responseRequests = [];
        $first = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'Is there a swimming pool?', visitorKey: 'v2'));
        $again = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'Is there a swimming pool?', visitorKey: 'v3'));

        $this->assertFalse($first->cached);
        $this->assertFalse($again->cached);
    }

    public function test_a_complete_first_answer_is_still_cached(): void
    {
        $this->liveAssistant();

        app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'What are the admission office hours?', visitorKey: 'v1'));
        $again = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'What are the admission office hours?', visitorKey: 'v2'));

        $this->assertTrue($again->cached);
    }
}
