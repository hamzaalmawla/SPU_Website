<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Models\Chatbot\ChatbotDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ChatbotWebsiteSyncTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
        config(['chatbot.website.base_url' => 'https://v2.spu.edu.sy', 'chatbot.website.seeds' => ['admissions', 'faculties']]);

        $this->openAi->pages = [
            '/en/admissions' => $this->page('Admissions', '<p>Registration opens in July for every faculty of the university.</p><a href="/en/admissions/fees">Fees</a><a href="/en/news/open-day">News</a><a href="/en/faculties/pharmacy/valedictorians">Top students</a>'),
            '/en/admissions/fees' => $this->page('Tuition and fees', '<p>The annual tuition of the Faculty of Pharmacy is 4,500,000 Syrian pounds.</p>'),
            '/en/faculties' => $this->page('Faculties', '<p>The university has seven faculties, including medicine, dentistry and pharmacy.</p><a href="/en/faculties/pharmacy">Pharmacy</a>'),
            '/en/faculties/pharmacy' => $this->page('Faculty of Pharmacy', '<p>The Faculty of Pharmacy offers a five-year program with hospital training.</p><a href="/en/faculties/pharmacy/members">Staff</a>'),
            '/ar/admissions' => $this->page('القبول', '<p>يبدأ التسجيل في شهر تموز لجميع كليات الجامعة السورية الخاصة.</p>'),
            '/ar/faculties' => $this->page('الكليات', '<p>تضم الجامعة سبع كليات منها الطب البشري وطب الأسنان والصيدلة.</p>'),
            '/en/news/open-day' => $this->page('Open day', '<p>Open day news that must not be indexed at all.</p>'),
            '/en/faculties/pharmacy/valedictorians' => $this->page('Top students', '<p>Student One, Student Two, Student Three are top students.</p>'),
            '/en/faculties/pharmacy/members' => $this->page('Members', '<p>Dr. A, Dr. B and Dr. C are faculty members here.</p>'),
        ];
    }

    public function test_published_pages_become_knowledge_with_links_and_private_lists_stay_out(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);

        $result = app(ChatbotKnowledgeServiceInterface::class)->syncWebsite((int) $user->id);

        $this->assertTrue($result->reachable);
        $this->assertSame(6, $result->pages);
        $this->assertSame(6, $result->added);
        $this->assertSame(0, $result->failed);

        $paths = ChatbotDocument::query()->where('source_type', 'website')->orderBy('source_url')->pluck('source_url')->all();
        $this->assertSame(['/ar/admissions', '/ar/faculties', '/en/admissions', '/en/admissions/fees', '/en/faculties', '/en/faculties/pharmacy'], $paths);
        $this->assertNotContains('/en/news/open-day', $this->openAi->pageRequests);
        $this->assertNotContains('/en/faculties/pharmacy/valedictorians', $this->openAi->pageRequests);
        $this->assertNotContains('/en/faculties/pharmacy/members', $this->openAi->pageRequests);

        $fees = ChatbotDocument::query()->where('source_url', '/en/admissions/fees')->firstOrFail();
        $this->assertSame('Tuition and fees', $fees->title);
        $this->assertSame('en', $fees->locale);
        $this->assertSame('ready', $fees->status);

        // An answer drawn from a page links to it.
        $this->postJson('/en/assistant/messages', ['message' => 'What is the annual tuition of the Faculty of Pharmacy?'])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('sources.0.title', 'Tuition and fees')
            ->assertJsonPath('sources.0.url', '/en/admissions/fees');
    }

    public function test_a_second_sync_embeds_only_what_changed_and_keeps_switched_off_pages_off(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $service = app(ChatbotKnowledgeServiceInterface::class);
        $service->syncWebsite((int) $user->id);

        $faculties = ChatbotDocument::query()->where('source_url', '/en/faculties')->firstOrFail();
        $service->setActive((int) $faculties->id, false, (int) $user->id);

        $this->openAi->pages['/en/admissions/fees'] = $this->page('Tuition and fees', '<p>The annual tuition of the Faculty of Pharmacy is 5,000,000 Syrian pounds.</p>');
        unset($this->openAi->pages['/ar/faculties']);
        $embeddingsBefore = count($this->openAi->embeddingRequests);

        $result = $service->syncWebsite((int) $user->id);

        $this->assertSame(1, $result->updated);
        $this->assertSame(4, $result->unchanged);
        $this->assertSame(1, $result->removed);
        $this->assertSame($embeddingsBefore + 1, count($this->openAi->embeddingRequests), 'Only the changed page is embedded again.');
        $this->assertFalse((bool) $faculties->fresh()->is_active, 'A page an editor switched off stays off.');
        $this->assertNull(ChatbotDocument::query()->where('source_url', '/ar/faculties')->first());
        $this->assertStringContainsString('5,000,000', app(ChatbotKnowledgeServiceInterface::class)->preview((int) ChatbotDocument::query()->where('source_url', '/en/admissions/fees')->value('id')));
    }

    public function test_an_unreachable_or_partly_reachable_site_removes_nothing(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $service = app(ChatbotKnowledgeServiceInterface::class);
        $service->syncWebsite((int) $user->id);

        $all = $this->openAi->pages;
        $this->openAi->pages = [];
        $this->assertFalse($service->syncWebsite((int) $user->id)->reachable);
        $this->assertSame(6, ChatbotDocument::query()->where('source_type', 'website')->count());

        // Only two of six pages answer: a partial outage, not a smaller site.
        $this->openAi->pages = array_intersect_key($all, array_flip(['/en/admissions', '/ar/admissions']));
        $this->assertSame(0, $service->syncWebsite((int) $user->id)->removed);
        $this->assertSame(6, ChatbotDocument::query()->where('source_type', 'website')->count());
    }

    public function test_the_sync_command_skips_without_a_key_and_reports_a_table_with_one(): void
    {
        $this->artisan('chatbot:sync-website')->expectsOutputToContain('No OpenAI key is saved yet')->assertSuccessful();

        $this->saveApiKey($this->adminUser());
        $this->artisan('chatbot:sync-website')->expectsTable(['Pages read', 'Added', 'Updated', 'Unchanged', 'Removed', 'Failed'], [[6, 6, 0, 0, 0, 0]])->assertSuccessful();
    }

    public function test_the_import_key_command_saves_the_key_encrypted_and_deletes_the_file(): void
    {
        $this->adminUser();
        $file = storage_path('framework/testing/openai-key.pending');
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, "  sk-proj-abcdefghijklmnopqrstuvwxyzABCD  \n");

        $this->artisan('chatbot:import-key', ['file' => $file])
            ->expectsOutputToContain('ending in …ABCD')
            ->expectsOutputToContain('OpenAI reachable from this server')
            ->doesntExpectOutputToContain('abcdefghijklmnop')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($file);
        $this->assertSame('sk-proj-abcdefghijklmnopqrstuvwxyzABCD', app(ChatbotSettingsServiceInterface::class)->apiKey());

        file_put_contents($file, 'not a key!');
        $this->artisan('chatbot:import-key', ['file' => $file])->assertFailed();
        $this->assertFileDoesNotExist($file);
    }

    private function page(string $title, string $body): string
    {
        return '<!DOCTYPE html><html><head><title>'.$title.' | SPU</title></head><body><nav><a href="/en/news">News</a></nav><main id="main-content"><h1>'.$title.'</h1>'.$body.'</main></body></html>';
    }
}
