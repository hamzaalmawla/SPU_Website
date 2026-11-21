<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Models\Chatbot\ChatbotDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ChatbotImportDocumentsCommandTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChatbot();
    }

    public function test_waiting_files_are_added_like_an_admin_upload_and_moved_to_done(): void
    {
        $user = $this->adminUser();
        $this->saveApiKey($user);
        $disk = Storage::disk('local');
        $disk->put('chatbot/import/قرار 448 - تعديل المادتين 16 و17.md', "# القرار 448\nيعدل المادتين 16 و17 من القرار 167 لعام 2016.");
        $disk->put('chatbot/import/مرسوم تسمية الجامعة.txt', 'يعدل المرسوم تسمية الجامعة لتصبح الجامعة السورية الخاصة.');
        $disk->put('chatbot/import/.gitkeep', '');

        $this->artisan('chatbot:import-documents', ['--user' => (string) $user->id])
            ->expectsOutputToContain('Would add: قرار 448 - تعديل المادتين 16 و17.md')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertSame(0, ChatbotDocument::query()->count());

        $this->artisan('chatbot:import-documents', ['--user' => (string) $user->id, '--write' => true])
            ->expectsOutputToContain('— ready, ')
            ->assertSuccessful();

        $this->assertSame(2, ChatbotDocument::query()->where('source_type', 'upload')->where('status', 'ready')->count());
        $this->assertSame((int) $user->id, (int) ChatbotDocument::query()->value('uploaded_by'));
        $disk->assertExists('chatbot/import/done/قرار 448 - تعديل المادتين 16 و17.md');
        $disk->assertMissing('chatbot/import/مرسوم تسمية الجامعة.txt');
        $this->assertSame([], $disk->files('chatbot/uploads'));

        // The same file sent again is recognised and not added twice.
        $disk->put('chatbot/import/copy.txt', 'يعدل المرسوم تسمية الجامعة لتصبح الجامعة السورية الخاصة.');
        $this->artisan('chatbot:import-documents', ['--user' => (string) $user->id, '--write' => true])
            ->expectsOutputToContain('SKIPPED: copy.txt is already in the knowledge.')
            ->assertSuccessful();
        $this->assertSame(2, ChatbotDocument::query()->count());
        $disk->assertExists('chatbot/import/done/copy.txt');
    }

    public function test_it_needs_a_user_and_says_when_nothing_waits(): void
    {
        $this->artisan('chatbot:import-documents')->assertExitCode(2);

        $this->artisan('chatbot:import-documents', ['--user' => '1'])
            ->expectsOutputToContain('No files are waiting')
            ->assertSuccessful();
    }
}
