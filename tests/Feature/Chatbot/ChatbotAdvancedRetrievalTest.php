<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotEvaluationServiceInterface;
use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Models\Chatbot\ChatbotChunk;
use App\Services\Chatbot\ChatbotPassageContextWriter;
use App\Support\Chatbot\EvaluationScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Contextual retrieval, LLM reranking and the golden-question evaluation.
 */
final class ChatbotAdvancedRetrievalTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    private function decree(): string
    {
        return "# المادة 16\nد-1- لا يزيد الإعفاء على ثلاثين بالمئة من ساعات الكلية في حال كانت الإجازة من جامعة أخرى.\n\n"
            ."# المادة 17\nد-2- لا يزيد على خمسين بالمئة في حال كانت ضمن الكلية الواحدة.\n\n"
            ."# المادة 18\nيسمح بقبول المستنفدين وفق قواعد تغيير القيد.";
    }

    public function test_each_passage_gets_a_context_that_is_searched_with_it_and_never_paid_twice(): void
    {
        config(['chatbot.knowledge.chunk_characters' => 120, 'chatbot.knowledge.chunk_overlap' => 0]);
        // Only the passage itself decides (the request also carries the text around it).
        $this->openAi->contextWith = static fn (string $content): string => preg_match('/\nPassage[^\n]*:\n(.*?)\n\nText just after/su', $content, $match) === 1 && str_contains($match[1], 'خمسين')
            ? 'من قرار 448 عن إعفاء حملة الإجازة الجامعية من الكلية نفسها.'
            : 'من قرار 448.';
        $user = $this->adminUser();
        $this->saveApiKey($user);

        $document = $this->addKnowledge($user, 'قرار 448', $this->decree());

        $this->assertGreaterThan(1, $document->chunkCount);
        $this->assertCount($document->chunkCount, $this->openAi->contextRequests);
        $this->assertSame(0.0, (float) ($this->openAi->contextRequests[0]['temperature'] ?? -1));
        $stored = ChatbotChunk::query()->where('content', 'like', '%خمسين%')->value('context');
        $this->assertSame('من قرار 448 عن إعفاء حملة الإجازة الجامعية من الكلية نفسها.', $stored);

        // "حملة الإجازة" is only in the context: the passage is found through it.
        $passages = app(ChatbotKnowledgeServiceInterface::class)->retrieve('حملة الإجازة الجامعية من الكلية نفسها', 'sk-test', 'ar')->passages;
        $this->assertStringContainsString('خمسين', $passages[0]->content);
        $this->assertSame('من قرار 448 عن إعفاء حملة الإجازة الجامعية من الكلية نفسها.', $passages[0]->context);

        // Re-indexing the same text reuses every context.
        app(ChatbotKnowledgeServiceInterface::class)->reindex($document->id, (int) $user->id);
        $this->assertCount($document->chunkCount, $this->openAi->contextRequests);
    }

    public function test_website_pages_and_single_passages_get_no_context(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);

        $this->addKnowledge($user, 'Short note', 'The admission office is open from 9 to 3.');

        $this->assertSame([], $this->openAi->contextRequests);
    }

    public function test_the_reranker_reorders_the_candidates_and_a_failure_keeps_the_fused_order(): void
    {
        config(['chatbot.knowledge.chunk_characters' => 120, 'chatbot.knowledge.chunk_overlap' => 0, 'chatbot.retrieval.min_similarity' => 0.0, 'chatbot.retrieval.neighbors' => 0]);
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->addKnowledge($user, 'قرار 448', $this->decree());
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $fused = $service->retrieve('الإعفاء من ساعات الكلية', 'sk-test', 'ar')->passages;
        $this->assertSame([], $this->openAi->rerankRequests);

        config(['chatbot.retrieval.rerank' => true]);
        $last = count($fused);
        $this->openAi->rerankWith = static fn (string $content): array => [$last];
        $reranked = $service->retrieve('الإعفاء من ساعات الكلية', 'sk-test', 'ar')->passages;

        $this->assertCount(1, $this->openAi->rerankRequests);
        $this->assertSame($fused[$last - 1]->chunkId, $reranked[0]->chunkId);
        // Passages the reranker skipped still fill the remaining slots.
        $this->assertCount(count($fused), $reranked);

        $this->openAi->rerankStatus = 500;
        $this->assertSame(
            array_map(static fn ($p): int => $p->chunkId, $fused),
            array_map(static fn ($p): int => $p->chunkId, $service->retrieve('الإعفاء من ساعات الكلية', 'sk-test', 'ar')->passages),
        );
    }

    public function test_a_context_is_tidied_without_breaking_arabic_letters(): void
    {
        $this->assertSame('من دليل البحث', ChatbotPassageContextWriter::clean('«من دليل البحث»'));
        $this->assertSame('—', ChatbotPassageContextWriter::clean('—'));
        $this->assertSame('From decree 448.', ChatbotPassageContextWriter::clean("Context: \"From decree\n448.\""));
    }

    public function test_the_scorer_matches_whole_numbers_and_folded_spellings(): void
    {
        $answer = 'الإعفاء حتى **30%**، أو ٥٠٪ ضمن الكلية نفسها. التقدير: جيد جداً. الرقم 300 ليس مطلوباً.';

        $this->assertSame(['found' => 3, 'total' => 3, 'missing' => []], EvaluationScorer::facts($answer, [['30'], ['50'], ['جيد جدا']]));
        $this->assertSame(['found' => 0, 'total' => 1, 'missing' => ['3']], EvaluationScorer::facts($answer, [['3']]));
        $this->assertSame(['found' => 0, 'total' => 1, 'missing' => ['92.5']], EvaluationScorer::facts('المعدل 92.55%', [['92.5']]));
        $this->assertSame(['found' => 1, 'total' => 2], EvaluationScorer::sources(['قرار 448 لعام 2017 تعديل'], [['قرار 448'], ['دليل']]));
        $this->assertSame(['found' => 1, 'total' => 1], EvaluationScorer::sources(['إعلان مفاضلة النقل 2026'], [['اعلان مفاضلة النقل']]));
    }

    public function test_the_evaluation_asks_every_case_scores_it_and_saves_the_report(): void
    {
        $this->liveAssistant();
        $this->openAi->answerWith = static fn (array $payload): string => 'The office is open Sunday to Thursday from 9 to 3.';
        $evaluation = app(ChatbotEvaluationServiceInterface::class);

        $report = $evaluation->run([
            ['id' => 'hours', 'locale' => 'en', 'question' => 'When is the admission office open?', 'sources' => [['Admission guide']], 'facts' => [['9'], ['3']]],
            ['id' => 'fees', 'locale' => 'en', 'question' => 'How much is Pharmacy tuition?', 'sources' => [['Tuition fees']], 'facts' => [['4,500,000', '4500000']]],
        ], 'test', ['retrieval.rerank' => false]);

        $this->assertSame([true, false], array_column($report->results, 'passed'));
        $this->assertSame(1.0, $report->retrievalRecall);
        $this->assertSame(0.667, $report->factRecall);
        $this->assertSame(0.5, $report->passRate);
        $this->assertNotNull($report->savedAs);
        Storage::disk('local')->assertExists((string) $report->savedAs);

        // The golden questions shipped with the site are readable and complete.
        $cases = $evaluation->cases();
        $this->assertGreaterThanOrEqual(30, count($cases));
        foreach ($cases as $case) {
            $this->assertNotSame([], $case['facts'], $case['id']);
            $this->assertNotSame([], $case['sources'], $case['id']);
        }
    }
}
