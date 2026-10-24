<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CampusLifeClubsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_published_club_cards_link_to_detail_pages_with_optional_external_signup(): void
    {
        $payload = $this->clubPayload();
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['clubs']['items'][0]['signupLabel'] = $locale === 'ar' ? 'سجل الآن' : 'Join Now';
            $payload['translations'][$locale]['clubs']['items'][0]['signupUrl'] = 'https://forms.gle/abc123';
        }
        $this->publish($payload);

        $this->get('/en/campus-life/clubs-activities')
            ->assertOk()
            ->assertSee('/en/campus-life/clubs-activities/ai-technology', false)
            ->assertDontSee('#ai-technology', false);

        $this->get('/en/campus-life/clubs-activities/ai-technology')
            ->assertOk()
            ->assertSee('AI &amp; Technology Club', false)
            ->assertSee('The club gives students a collaborative space')
            ->assertSee('https://forms.gle/abc123', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer external"', false)
            ->assertSee('/ar/campus-life/clubs-activities/ai-technology', false);

        $sitemapUrls = app(SitemapServiceInterface::class)->generateEntries()->pluck('loc')->all();
        $this->assertContains(config('app.url').'/en/campus-life/clubs-activities/ai-technology', $sitemapUrls);
        $this->assertContains(config('app.url').'/ar/campus-life/clubs-activities/ai-technology', $sitemapUrls);

        $this->get('/en/campus-life/clubs-activities/not-a-club')->assertNotFound();
    }

    public function test_draft_club_detail_is_only_available_through_protected_preview(): void
    {
        $payload = $this->clubPayload();
        $payload['translations']['en']['clubs']['items'][0]['body'] = 'Draft-only club details.';
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow->saveDraft('campus_life.clubs-activities', $payload, (int) $author->id);
        $preview = $workflow->preview('campus_life.clubs-activities', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('club=ai-technology', false);
        $this->get($preview->previewUrl.'&club=ai-technology')
            ->assertOk()
            ->assertSee('Draft-only club details.')
            ->assertSee('Preview mode');
        $this->get('/en/campus-life/clubs-activities/ai-technology')->assertNotFound();
    }

    public function test_publish_rejects_unsafe_signup_urls_duplicate_slugs_and_locale_mismatch(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $this->clubPayload();
        $payload['translations']['en']['clubs']['items'][0]['signupLabel'] = 'Join';
        $payload['translations']['en']['clubs']['items'][0]['signupUrl'] = 'http://forms.gle/unsafe';
        $payload['translations']['en']['clubs']['items'][] = $payload['translations']['en']['clubs']['items'][0];
        $payload['translations']['ar']['clubs']['items'][0]['slug'] = 'different-slug';

        $readiness = $workflow->readiness('campus_life.clubs-activities', $payload);

        $this->assertFalse($readiness->isReady);
        $messages = collect($readiness->errors)->flatten()->implode(' ');
        $this->assertStringContainsString('valid HTTPS', $messages);
        $this->assertStringContainsString('unique', $messages);
        $this->assertStringContainsString('matching slugs', $messages);
    }

    public function test_existing_id_summary_and_fragment_payloads_upgrade_without_data_loss(): void
    {
        $payload = $this->clubPayload();
        foreach (['ar', 'en'] as $locale) {
            unset($payload['translations'][$locale]['clubs']['items'][0]['slug'], $payload['translations'][$locale]['clubs']['items'][0]['body']);
            $payload['translations'][$locale]['clubs']['items'][0]['href'] = '/'.$locale.'/campus-life/clubs-activities#ai-technology';
        }

        $this->publish($payload);

        $this->get('/en/campus-life/clubs-activities')
            ->assertOk()
            ->assertSee('/en/campus-life/clubs-activities/ai-technology', false)
            ->assertDontSee('#ai-technology', false);
        $this->get('/en/campus-life/clubs-activities/ai-technology')
            ->assertOk()
            ->assertSee('Exploring artificial intelligence and public speaking skills');
    }

    /** @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}} */
    private function clubPayload(): array
    {
        return app(CampusLifePageServiceInterface::class)->getEditablePayload('campus_life.clubs-activities');
    }

    /** @param array<string, mixed> $payload */
    private function publish(array $payload): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow->saveDraft('campus_life.clubs-activities', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('campus_life.clubs-activities', (int) $author->id));
    }
}
