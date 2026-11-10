<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Models\Chatbot\ChatbotChunk;
use App\Models\Chatbot\ChatbotDocument;
use App\Models\Shared\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

final class ChatbotKnowledgeTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    public function test_an_uploaded_file_is_split_embedded_and_audited(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        Storage::disk('local')->put('chatbot/uploads/tmp.md', "# القبول\nيبدأ التسجيل في شهر تموز.\n\n# الأقساط\nتدفع الأقساط على قسطين في كل فصل.");

        $document = app(ChatbotKnowledgeServiceInterface::class)->addUpload('chatbot/uploads/tmp.md', 'دليل_القبول-2026.md', null, (int) $user->id);

        $this->assertSame('ready', $document->status, (string) $document->error);
        $this->assertSame('دليل القبول 2026', $document->title);
        $this->assertNull($document->error);
        $this->assertGreaterThan(0, $document->chunkCount);
        $this->assertFalse($document->needsReindex);

        $row = ChatbotDocument::query()->findOrFail($document->id);
        $this->assertSame('md', $row->extension);
        $this->assertStringStartsWith('chatbot/documents/', (string) $row->storage_path);
        Storage::disk('local')->assertExists((string) $row->storage_path);
        Storage::disk('local')->assertMissing('chatbot/uploads/tmp.md');

        $chunk = ChatbotChunk::query()->where('chatbot_document_id', $document->id)->firstOrFail();
        $this->assertSame(512 * 4, strlen((string) base64_decode((string) $chunk->embedding, true)));
        $this->assertSame('text-embedding-3-small', $this->openAi->embeddingRequests[0]['model']);
        $this->assertSame(512, $this->openAi->embeddingRequests[0]['dimensions']);
        // The title travels with every passage that is embedded.
        $this->assertStringStartsWith('دليل القبول 2026', $this->openAi->embeddingRequests[0]['input'][0]);

        $this->assertTrue(AuditLog::query()->where('action', 'chatbot.document.uploaded')->where('entity_id', $document->id)->exists());
        $this->assertGreaterThan(0, app(ChatbotUsageServiceInterface::class)->summary()->tokens30Days);
    }

    public function test_the_same_file_twice_and_unsupported_types_are_refused(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $service = app(ChatbotKnowledgeServiceInterface::class);
        Storage::disk('local')->put('chatbot/uploads/a.txt', 'Registration opens on the first of July every year.');
        $service->addUpload('chatbot/uploads/a.txt', 'a.txt', null, (int) $user->id);

        Storage::disk('local')->put('chatbot/uploads/b.txt', 'Registration opens on the first of July every year.');
        try {
            $service->addUpload('chatbot/uploads/b.txt', 'copy.txt', null, (int) $user->id);
            $this->fail('A duplicate was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('admin_chatbot.errors.duplicate', $exception->getMessage());
        }
        Storage::disk('local')->assertMissing('chatbot/uploads/b.txt');

        Storage::disk('local')->put('chatbot/uploads/c.exe', 'MZ');
        $this->expectExceptionMessage('admin_chatbot.errors.unsupported_type');
        $service->addUpload('chatbot/uploads/c.exe', 'c.exe', null, (int) $user->id);
    }

    public function test_without_an_api_key_a_file_is_kept_as_failed_with_the_reason(): void
    {
        $user = $this->adminUser();

        $document = $this->addKnowledge($user, 'Notice', 'The library opens at eight in the morning on weekdays.');

        $this->assertSame('failed', $document->status);
        $this->assertSame(__('admin_chatbot.errors.no_api_key'), $document->error);
        $this->assertSame(0, ChatbotChunk::query()->count());

        // Once a key is saved, re-indexing makes it usable.
        $this->saveApiKey($user);
        $this->assertSame(1, app(ChatbotKnowledgeServiceInterface::class)->reindexStale((int) $user->id));
        $this->assertSame('ready', app(ChatbotKnowledgeServiceInterface::class)->documents()->first()?->status);
    }

    public function test_a_provider_error_marks_the_file_failed(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $this->openAi->embeddingsStatus = 401;

        $document = $this->addKnowledge($user, 'Notice', 'The library opens at eight in the morning on weekdays.');

        $this->assertSame('failed', $document->status);
        $this->assertSame(__('admin_chatbot.errors.provider_auth'), $document->error);
    }

    public function test_retrieval_ranks_the_relevant_passage_first_and_skips_inactive_files(): void
    {
        $user = $this->liveAssistant();
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $passages = $service->retrieve('What is the annual tuition of the Faculty of Pharmacy?', 'sk-test')->passages;

        $this->assertNotEmpty($passages);
        $this->assertSame('Tuition fees', $passages[0]->documentTitle);
        $this->assertStringContainsString('4,500,000', $passages[0]->content);

        $tuition = $service->documents()->firstWhere('title', 'Tuition fees');
        $service->setActive($tuition->id, false, (int) $user->id);

        $titles = array_map(fn ($passage) => $passage->documentTitle, $service->retrieve('What is the annual tuition of the Faculty of Pharmacy?', 'sk-test')->passages);
        $this->assertNotContains('Tuition fees', $titles);
    }

    public function test_the_query_embedding_is_cached(): void
    {
        $this->liveAssistant();
        $service = app(ChatbotKnowledgeServiceInterface::class);
        $before = count($this->openAi->embeddingRequests);

        $first = $service->retrieve('office hours', 'sk-test');
        $second = $service->retrieve('office hours', 'sk-test');

        $this->assertSame($before + 1, count($this->openAi->embeddingRequests));
        $this->assertGreaterThan(0, $first->embeddingTokens);
        $this->assertSame(0, $second->embeddingTokens);
    }

    public function test_delete_removes_passages_and_the_stored_file(): void
    {
        $user = $this->liveAssistant();
        $service = app(ChatbotKnowledgeServiceInterface::class);
        $document = $service->documents()->firstWhere('title', 'Tuition fees');
        $path = (string) ChatbotDocument::query()->findOrFail($document->id)->storage_path;

        $this->assertTrue($service->delete($document->id, (int) $user->id));

        $this->assertSame(0, ChatbotChunk::query()->where('chatbot_document_id', $document->id)->count());
        Storage::disk('local')->assertMissing($path);
        $this->assertTrue(AuditLog::query()->where('action', 'chatbot.document.deleted')->exists());
    }

    public function test_changing_the_embedding_size_marks_documents_for_reindexing(): void
    {
        $this->liveAssistant();
        config(['chatbot.embedding.dimensions' => 256]);
        $service = app(ChatbotKnowledgeServiceInterface::class);

        $this->assertSame(2, $service->stats()->staleDocuments);
        $this->assertSame(0, $service->stats()->searchableDocuments);
        $this->assertSame([], $service->retrieve('tuition', 'sk-test')->passages);
    }
}
