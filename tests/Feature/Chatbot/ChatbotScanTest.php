<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Filament\Pages\ManageChatbot;
use App\Models\Chatbot\ChatbotChunk;
use App\Models\Chatbot\ChatbotDocument;
use App\Models\Chatbot\ChatbotUsageDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\KnowledgeFixtures;
use Tests\TestCase;

/**
 * Scanned PDFs, photos and Excel files uploaded as AI assistant knowledge.
 */
final class ChatbotScanTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();

        // The fake "vision model" writes each page out from the request.
        $this->openAi->answerWith = static function (array $payload): string {
            $content = $payload['input'][0]['content'] ?? null;
            if (is_array($content) && preg_match('/^Page (\d+) of/', (string) ($content[0]['text'] ?? ''), $matches) === 1) {
                return "## الصفحة {$matches[1]}\n| الجامعة | الحد الأدنى |\nالجامعة: الجامعة السورية الخاصة ; الحد الأدنى: ".(70 + (int) $matches[1]);
            }

            return 'answer';
        };
    }

    public function test_a_scanned_pdf_is_read_page_by_page_and_indexed(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        Storage::disk('local')->put('chatbot/uploads/scan.pdf', KnowledgeFixtures::scannedPdf(3));

        $document = app(ChatbotKnowledgeServiceInterface::class)->addUpload('chatbot/uploads/scan.pdf', 'إعلان المفاضلة.pdf', null, (int) $user->id);

        $this->assertSame('ready', $document->status, (string) $document->error);
        $this->assertSame(3, $document->scanPagesTotal);
        $this->assertSame(3, $document->scanPagesRead);

        // One vision call per page, with the page image and no storage.
        $ocr = array_values(array_filter($this->openAi->responseRequests, static fn (array $request): bool => is_array($request['input'][0]['content'] ?? null)));
        $this->assertCount(3, $ocr);
        $this->assertSame('input_image', $ocr[0]['input'][0]['content'][1]['type']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $ocr[0]['input'][0]['content'][1]['image_url']);
        $this->assertFalse($ocr[0]['store']);
        $this->assertSame('gpt-5.4-mini', $ocr[0]['model']);

        $chunks = ChatbotChunk::query()->where('chatbot_document_id', $document->id)->orderBy('position')->get();
        $this->assertSame([1, 2, 3], $chunks->pluck('page')->unique()->values()->all());
        $this->assertStringContainsString('الجامعة: الجامعة السورية الخاصة ; الحد الأدنى: 72', (string) $chunks->firstWhere('page', 2)?->content);

        // The page text is kept next to the file and reused on re-index.
        $row = ChatbotDocument::query()->findOrFail($document->id);
        Storage::disk('local')->assertExists($row->storage_path.'.scan.json');
        $calls = count($this->openAi->responseRequests);
        $this->assertSame('ready', app(ChatbotKnowledgeServiceInterface::class)->reindex($document->id, (int) $user->id)->status);
        $this->assertCount($calls, $this->openAi->responseRequests);

        // Reading the pages is counted in the day's usage and cost.
        $day = ChatbotUsageDay::query()->firstOrFail();
        $this->assertSame(3 * 1200, (int) $day->input_tokens);
        $this->assertGreaterThan(0, (int) $day->cost_micro_usd);
        $this->assertSame(0, app(ChatbotUsageServiceInterface::class)->questionsToday());

        // Deleting the file removes the saved page text too.
        app(ChatbotKnowledgeServiceInterface::class)->delete($document->id, (int) $user->id);
        Storage::disk('local')->assertMissing($row->storage_path.'.scan.json');
    }

    public function test_a_long_scan_carries_on_in_later_runs_until_every_page_is_read(): void
    {
        config(['chatbot.ocr.concurrency' => 2]);
        $user = $this->adminUser();
        $this->saveApiKey($user);
        Storage::disk('local')->put('chatbot/uploads/long.pdf', KnowledgeFixtures::scannedPdf(5));
        $service = app(ChatbotKnowledgeServiceInterface::class);

        // The upload request reads one batch (two pages), then stops.
        $document = $service->addUpload('chatbot/uploads/long.pdf', 'long-scan.pdf', null, (int) $user->id);
        $this->assertSame('pending', $document->status);
        $this->assertSame([5, 2], [$document->scanPagesTotal, $document->scanPagesRead]);
        $this->assertTrue($service->hasPendingScans());
        $this->assertSame(0, ChatbotChunk::query()->count());

        // The scheduler's run picks it up where it stopped.
        $this->artisan('chatbot:continue-indexing', ['--seconds' => 5])->assertSuccessful();
        $this->assertSame(4, ChatbotDocument::query()->findOrFail($document->id)->scan_pages_read);

        $this->assertSame(1, $service->continuePending(5));
        $done = $service->document($document->id);
        $this->assertSame('ready', $done?->status);
        $this->assertSame(5, $done?->scanPagesRead);
        $this->assertFalse($service->hasPendingScans());
        $this->assertSame([1, 2, 3, 4, 5], ChatbotChunk::query()->pluck('page')->unique()->sort()->values()->all());

        // Each page was read exactly once.
        $this->assertCount(5, array_filter($this->openAi->responseRequests, static fn (array $request): bool => is_array($request['input'][0]['content'] ?? null)));
    }

    public function test_a_page_that_keeps_failing_is_given_up_and_the_rest_is_used(): void
    {
        config(['chatbot.ocr.max_attempts' => 2]);
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->openAi->answerWith = static function (array $payload): string {
            $text = (string) ($payload['input'][0]['content'][0]['text'] ?? '');
            if (str_starts_with($text, 'Page 2 of')) {
                // An empty reply is a failed read.
                return '';
            }

            return 'نص الصفحة: '.$text;
        };
        Storage::disk('local')->put('chatbot/uploads/scan.pdf', KnowledgeFixtures::scannedPdf(2));
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $document = $service->addUpload('chatbot/uploads/scan.pdf', 'scan.pdf', null, (int) $user->id);
        $this->assertSame('pending', $document->status);
        $this->assertSame(1, $document->scanPagesRead);

        $service->continuePending(5);
        $done = $service->document($document->id);
        $this->assertSame('ready', $done?->status);
        $this->assertSame(__('admin_chatbot.errors.scan_pages_unread'), $done?->error);
        $this->assertSame([1], ChatbotChunk::query()->pluck('page')->unique()->values()->all());
    }

    public function test_a_rejected_key_while_reading_fails_the_file_with_the_reason(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->openAi->responsesStatus = 401;
        Storage::disk('local')->put('chatbot/uploads/photo.png', KnowledgeFixtures::jpeg());

        $document = app(ChatbotKnowledgeServiceInterface::class)->addUpload('chatbot/uploads/photo.png', 'photo.png', null, (int) $user->id);

        $this->assertSame('failed', $document->status);
        $this->assertSame(__('admin_chatbot.errors.provider_auth'), $document->error);
    }

    public function test_a_photo_and_an_excel_workbook_become_knowledge(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $service = app(ChatbotKnowledgeServiceInterface::class);

        Storage::disk('local')->put('chatbot/uploads/notice.jpg', KnowledgeFixtures::jpeg());
        $photo = $service->addUpload('chatbot/uploads/notice.jpg', 'notice.jpg', null, (int) $user->id);
        $this->assertSame('ready', $photo->status, (string) $photo->error);
        $this->assertSame(1, $photo->scanPagesTotal);

        $path = Storage::disk('local')->path('chatbot/uploads/tables.xlsx');
        @mkdir(dirname($path), 0777, true);
        KnowledgeFixtures::xlsx($path, ['جدول 4' => ['rows' => [
            ['مصدر الشهادة', 'الحد الأدنى', 'الجامعة', 'الرغبة'],
            ['سوريا', 92.55, 'الجامعة السورية الخاصة', 'الطب البشري'],
        ]]]);
        $sheet = $service->addUpload('chatbot/uploads/tables.xlsx', 'tables.xlsx', null, (int) $user->id);

        $this->assertSame('ready', $sheet->status, (string) $sheet->error);
        $this->assertSame(0, $sheet->scanPagesTotal);
        $this->assertStringContainsString(
            'مصدر الشهادة: سوريا ; الحد الأدنى: 92.55 ; الجامعة: الجامعة السورية الخاصة ; الرغبة: الطب البشري',
            (string) ChatbotChunk::query()->where('chatbot_document_id', $sheet->id)->value('content'),
        );

        $retrieved = $service->retrieve('الحد الأدنى الطب البشري الجامعة السورية الخاصة', 'sk-test');
        $this->assertNotEmpty($retrieved->passages);
    }

    public function test_the_admin_list_shows_progress_and_keeps_reading_while_open(): void
    {
        config(['chatbot.ocr.concurrency' => 1]);
        $user = $this->adminUser();
        $this->saveApiKey($user);
        Storage::disk('local')->put('chatbot/uploads/scan.pdf', KnowledgeFixtures::scannedPdf(2));
        $document = app(ChatbotKnowledgeServiceInterface::class)->addUpload('chatbot/uploads/scan.pdf', 'scan.pdf', null, (int) $user->id);
        $this->assertSame('pending', $document->status);

        $this->actingAs($user);
        Livewire::test(ManageChatbot::class)
            ->assertSee(__('admin_chatbot.scan_progress', ['read' => 1, 'total' => 2]))
            ->assertSeeHtml('wire:poll.5s="continueScans"')
            ->call('continueScans')
            ->assertDontSeeHtml('wire:poll.5s="continueScans"');

        $this->assertSame('ready', ChatbotDocument::query()->findOrFail($document->id)->status);
    }
}
