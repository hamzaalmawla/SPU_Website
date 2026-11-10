<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Contracts\Chatbot\ChatbotDirectoryServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\Person\Person;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Questions about all faculties are answered from the complete list, not
 * only from the few faculty pages retrieval happens to find; questions about
 * who holds a post, from the leadership page, not from an old signature.
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

    public function test_the_directory_names_the_current_president_and_deans_from_the_leadership_page(): void
    {
        // An editor replaces the president on the leadership page.
        $rector = Person::query()->where('category', 'rector')->firstOrFail();
        $rector->translations()->where('locale', 'ar')->update(['name' => 'أ. د. مروان الحجي', 'role' => 'رئيس الجامعة']);
        $rector->translations()->where('locale', 'en')->update(['name' => 'Prof. Dr. Marwan Al-Haji', 'role' => 'University President']);

        $arabic = app(ChatbotDirectoryServiceInterface::class)->forPrompt('ar');
        $english = app(ChatbotDirectoryServiceInterface::class)->forPrompt('en');

        $this->assertStringStartsWith('## Current leadership (who holds each post now, from the leadership page /ar/about/leadership)', $arabic);
        $this->assertStringContainsString('- رئيس الجامعة: أ. د. مروان الحجي (Prof. Dr. Marwan Al-Haji)', $arabic);
        $this->assertStringContainsString('- University President: Prof. Dr. Marwan Al-Haji (أ. د. مروان الحجي)', $english);
        $this->assertStringNotContainsString('عبد الرزاق', $arabic);
        $this->assertStringNotContainsString('Razzaq', $english);

        // Every dean the leadership page shows.
        $deans = app(AboutPageServiceInterface::class)->getLeadershipProfiles('ar')->where('category', 'dean');
        $this->assertNotEmpty($deans);
        foreach ($deans as $dean) {
            $this->assertStringContainsString(': '.$dean->name, $arabic);
        }
        $this->assertStringNotContainsString('<', $arabic);
    }

    public function test_an_old_signature_in_the_sources_does_not_outrank_the_leadership_page(): void
    {
        $this->liveAssistant();
        $rector = Person::query()->where('category', 'rector')->firstOrFail();
        $rector->translations()->where('locale', 'ar')->update(['name' => 'أ. د. مروان الحجي']);

        $this->postJson('/ar/assistant/messages', ['message' => 'من هو رئيس الجامعة السورية الخاصة'])->assertOk()->assertJsonPath('status', 'ok');

        $instructions = (string) end($this->openAi->responseRequests)['instructions'];
        $this->assertStringContainsString('- رئيس الجامعة: أ. د. مروان الحجي', $instructions);
        $this->assertStringContainsString('For a question about who holds a post now, answer from that list.', $instructions);
        $this->assertStringContainsString('Never present such a name as the current holder', $instructions);
    }
}
