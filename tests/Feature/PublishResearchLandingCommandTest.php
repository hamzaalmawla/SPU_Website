<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PublishResearchLandingCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_it_does_not_publish_without_the_publish_flag(): void
    {
        // The safety test. The default has to be the harmless one: someone
        // trying the command should not find out by seeing the page live.
        $this->artisan('research:publish-landing', ['--user' => $this->publisher()->email])
            ->expectsOutputToContain('Nothing was published')
            ->assertSuccessful();

        $this->assertNull(app(CmsWorkflowServiceInterface::class)->getPublishedPayload('research.index'));
        $this->assertFalse(app(ResearchPageServiceInterface::class)->isPubliclyAvailablePath('en', '/en/research'));
    }

    public function test_publishing_opens_the_landing_page_in_both_locales(): void
    {
        $this->artisan('research:publish-landing', [
            '--user' => $this->publisher()->email,
            '--publish' => true,
        ])->assertSuccessful();

        $published = app(CmsWorkflowServiceInterface::class)->getPublishedPayload('research.index');

        $this->assertIsArray($published);
        $this->assertNotEmpty($published['translations']['ar'] ?? null);
        $this->assertNotEmpty($published['translations']['en'] ?? null);

        $research = app(ResearchPageServiceInterface::class);

        foreach (['en', 'ar'] as $locale) {
            $this->assertTrue(
                $research->isPubliclyAvailablePath($locale, '/'.$locale.'/research'),
                "The {$locale} landing page is still gated after publishing.",
            );

            // It stops redirecting, which is the visitor-facing point of it.
            $this->get('/'.$locale.'/research')->assertOk();
        }
    }

    public function test_it_refuses_an_unknown_account(): void
    {
        $this->artisan('research:publish-landing', ['--user' => 'nobody@example.test', '--publish' => true])
            ->expectsOutputToContain('No account with the email')
            ->assertFailed();

        $this->assertNull(app(CmsWorkflowServiceInterface::class)->getPublishedPayload('research.index'));
    }

    public function test_it_refuses_a_payload_missing_a_language(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'payload').'.json';
        file_put_contents($path, json_encode([
            'translations' => ['en' => ['hero' => ['title' => 'English only']], 'ar' => []],
        ]));

        $this->artisan('research:publish-landing', [
            '--user' => $this->publisher()->email,
            '--file' => $path,
            '--publish' => true,
        ])->expectsOutputToContain('Both languages are required')->assertFailed();

        @unlink($path);
        $this->assertNull(app(CmsWorkflowServiceInterface::class)->getPublishedPayload('research.index'));
    }

    public function test_it_will_not_replace_published_content_without_force(): void
    {
        $email = $this->publisher()->email;

        $this->artisan('research:publish-landing', ['--user' => $email, '--publish' => true])->assertSuccessful();

        $this->artisan('research:publish-landing', ['--user' => $email, '--publish' => true])
            ->expectsOutputToContain('already published')
            ->assertFailed();
    }

    private function publisher(): User
    {
        return User::query()->firstOrFail();
    }
}
