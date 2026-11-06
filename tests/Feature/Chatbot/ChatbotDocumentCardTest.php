<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Models\Chatbot\ChatbotChunk;
use App\Models\Chatbot\ChatbotDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Long uploaded files get one extra passage with what they are and their
 * key facts, so a deadline is found together with the document's name.
 */
final class ChatbotDocumentCardTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    private const CARD = "إعلان وزارة التعليم العالي لمفاضلة النقل (التحويل المماثل وتغيير القيد) للعام الدراسي 2026-2027.\nموعد التقدم: اعتباراً من 2026/10/19 وحتى نهاية الدوام الرسمي من يوم 2026/10/26.\nرسم التقدم: 500 ل.س جديدة عبر تطبيق شام كاش.";

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
        $this->openAi->answerWith = static fn (array $payload): string => str_contains((string) ($payload['instructions'] ?? ''), 'document card') ? self::CARD : 'answer';
    }

    public function test_a_long_upload_gets_its_card_first_and_pays_for_it_once(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        Storage::disk('local')->put('chatbot/uploads/notice.md', $this->longAnnouncement());
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $document = $service->addUpload('chatbot/uploads/notice.md', 'إعلان مفاضلة النقل.md', null, (int) $user->id);

        $this->assertSame('ready', $document->status, (string) $document->error);
        $cardRequests = $this->cardRequests();
        $this->assertCount(1, $cardRequests);
        $this->assertSame('gpt-6.1-terra', $cardRequests[0]['model']);
        $this->assertFalse($cardRequests[0]['store']);
        $this->assertStringContainsString('Document title: إعلان مفاضلة النقل', $cardRequests[0]['input'][0]['content']);

        $first = ChatbotChunk::query()->where('chatbot_document_id', $document->id)->orderBy('position')->firstOrFail();
        $this->assertSame(__('admin_chatbot.card_heading', [], 'ar'), $first->heading);
        $this->assertStringContainsString('اعتباراً من 2026/10/19', (string) $first->content);

        // A question naming the competition now finds the deadline first.
        $top = $service->retrieve('موعد التقدم لمفاضلة النقل التحويل المماثل 2026-2027', 'sk-test')->passages[0];
        $this->assertSame((int) $first->id, $top->chunkId);

        // Saved next to the file: re-indexing does not write it again.
        $row = ChatbotDocument::query()->findOrFail($document->id);
        Storage::disk('local')->assertExists($row->storage_path.'.card.json');
        $service->reindex($document->id, (int) $user->id);
        $this->assertCount(1, $this->cardRequests());

        $service->delete($document->id, (int) $user->id);
        Storage::disk('local')->assertMissing($row->storage_path.'.card.json');
    }

    public function test_short_files_typed_text_and_a_failed_card_do_without(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $service = app(ChatbotKnowledgeServiceInterface::class);

        Storage::disk('local')->put('chatbot/uploads/short.md', "# القبول\nيبدأ التسجيل في شهر تموز.");
        $service->addUpload('chatbot/uploads/short.md', 'short.md', null, (int) $user->id);
        $service->addText('FAQ', $this->longAnnouncement(), (int) $user->id);
        $this->assertSame([], $this->cardRequests());

        $this->openAi->failModels = ['gpt-6.1-terra' => 500, 'gpt-5.4-mini' => 500];
        Storage::disk('local')->put('chatbot/uploads/long.md', $this->longAnnouncement().' نسخة ثانية');
        $document = $service->addUpload('chatbot/uploads/long.md', 'long.md', null, (int) $user->id);

        $this->assertSame('ready', $document->status);
        $this->assertNotSame(__('admin_chatbot.card_heading', [], 'ar'), ChatbotChunk::query()->where('chatbot_document_id', $document->id)->orderBy('position')->value('heading'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardRequests(): array
    {
        return array_values(array_filter($this->openAi->responseRequests, static fn (array $request): bool => str_contains((string) ($request['instructions'] ?? ''), 'document card')));
    }

    private function longAnnouncement(): string
    {
        $rules = str_repeat("- يجب على الطالب قراءة الإعلان جيداً قبل التقدم إلى هذه المفاضلة لمعرفة متطلبات القبول وشروطه والتقيد بالمواعيد.\n", 30);

        return "# أولاً - الأحكام العامة\n".$rules."\n# تاسعاً - التعليمات العامة\nعلى الراغبين بالتقدم إلى هذا الإعلان التقدم إلكترونياً اعتباراً من يوم الاثنين الواقع في 2026/10/19 وحتى نهاية الدوام الرسمي من يوم الاثنين الواقع في 2026/10/26.";
    }
}
