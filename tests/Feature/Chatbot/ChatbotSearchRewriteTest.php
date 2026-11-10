<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\Support\Chatbot\SearchRewrite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Questions are rewritten into the documents' wording before retrieval.
 */
final class ChatbotSearchRewriteTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    public function test_a_question_worded_unlike_the_document_finds_it_through_the_rewrite(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->addKnowledge($user, 'إعلان مفاضلة النقل', "# التعليمات العامة\nعلى الراغبين بالتقدم إلى هذا الإعلان التقدم إلكترونياً اعتباراً من يوم الاثنين الواقع في 2026/10/19 وحتى نهاية الدوام الرسمي من يوم الاثنين الواقع في 2026/10/26.");
        $this->addKnowledge($user, 'Tuition fees', "# Pharmacy tuition\nThe annual tuition of the Faculty of Pharmacy is 4,500,000 Syrian pounds.");
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $question = 'When can I apply for the transfer?';
        $this->assertNotSame('إعلان مفاضلة النقل', $service->retrieve($question, 'sk-test')->passages[0]->documentTitle ?? null);

        $hint = "التقدم إلكترونياً اعتباراً من وحتى نهاية الدوام الرسمي مفاضلة النقل\nApplication period for the transfer competition";
        $this->assertSame('إعلان مفاضلة النقل', $service->retrieve($question, 'sk-test', 'en', $hint)->passages[0]->documentTitle);
    }

    public function test_every_question_is_rewritten_once_and_the_rewrite_reaches_retrieval(): void
    {
        $this->liveAssistant();
        $this->openAi->rewriteWith = static fn (string $question): string => "ساعات دوام مكتب القبول\nAdmission office hours";

        $reply = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'When are you open?', adminTest: true));

        $this->assertSame('ok', $reply->status);
        $this->assertCount(1, $this->openAi->rewriteRequests);
        $this->assertSame('When are you open?', $this->openAi->rewriteRequests[0]['input'][0]['content']);
        $this->assertFalse($this->openAi->rewriteRequests[0]['store']);
        $this->assertSame('ساعات دوام مكتب القبول || Admission office hours', $reply->searchHint);
        // The admin test box sees the passages the answer was given.
        $this->assertSame('Admission guide 2026', $reply->passages[0]->documentTitle);

        // Cached: the same question does not pay for a second rewrite.
        app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'When are you open?', adminTest: true));
        $this->assertCount(1, $this->openAi->rewriteRequests);
    }

    public function test_a_failing_rewrite_never_stops_the_answer(): void
    {
        $this->liveAssistant();
        $this->openAi->rewriteStatus = 500;

        $reply = app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'What are the admission office hours?'));

        $this->assertSame('ok', $reply->status);
        $this->assertNull($reply->searchHint);
        $this->assertCount(1, $this->openAi->responseRequests);
    }

    public function test_switching_it_off_skips_the_call(): void
    {
        config(['chatbot.retrieval.rewrite' => false]);
        $this->liveAssistant();

        app(ChatbotServiceInterface::class)->ask(new ChatbotQuestionDTO(locale: 'en', message: 'What are the admission office hours?'));

        $this->assertSame([], $this->openAi->rewriteRequests);
    }

    public function test_the_rewrite_output_is_tidied(): void
    {
        $this->assertSame('موعد التقدم || Application dates', SearchRewrite::parse("1. Arabic: موعد التقدم\n2. English: Application dates", 'q'));
        $this->assertSame("موعد التقدم || Application dates\nالرسوم || Fees", SearchRewrite::parse("موعد التقدم || Application dates\nالرسوم || Fees", 'q'));
        $this->assertNull(SearchRewrite::parse('   ', 'q'));
        $this->assertNull(SearchRewrite::parse('hello', 'hello'));
    }
}
