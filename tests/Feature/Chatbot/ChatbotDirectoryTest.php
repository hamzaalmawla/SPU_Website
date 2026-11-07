<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotDirectoryServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Questions about all faculties are answered from the complete list, not
 * only from the few faculty pages retrieval happens to find.
 */
final class ChatbotDirectoryTest extends TestCase
{
    use ChatbotTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->setUpChatbot();
    }

    public function test_the_directory_lists_every_published_faculty_and_center_in_both_languages(): void
    {
        $faculties = app(FacultyPageServiceInterface::class)->getHub('ar')->faculties;
        $english = app(FacultyPageServiceInterface::class)->getHub('en')->faculties->keyBy('slug');
        $this->assertGreaterThan(4, $faculties->count());

        $directory = app(ChatbotDirectoryServiceInterface::class)->forPrompt('ar');

        $this->assertStringContainsString('## Faculties ('.$faculties->count().', the complete list)', $directory);
        foreach ($faculties as $card) {
            $this->assertStringContainsString('- '.$card->title.' ('.$english[$card->slug]->title.'): /ar/faculties/', $directory);
        }
        $this->assertStringContainsString('## Centers (', $directory);
        $this->assertStringNotContainsString('<', $directory);
    }

    public function test_every_answer_knows_the_complete_list(): void
    {
        $this->liveAssistant();
        $faculties = app(FacultyPageServiceInterface::class)->getHub('ar')->faculties;

        $this->postJson('/ar/assistant/messages', ['message' => 'ما الكليات كافة في الجامعة'])->assertOk()->assertJsonPath('status', 'ok');

        $instructions = (string) end($this->openAi->responseRequests)['instructions'];
        $this->assertStringContainsString('# The university at a glance (from its website; official and complete)', $instructions);
        $this->assertStringContainsString('name every one of them, not only those that appear in <sources>', $instructions);
        foreach ($faculties as $card) {
            $this->assertStringContainsString($card->title, $instructions);
        }
    }
}
