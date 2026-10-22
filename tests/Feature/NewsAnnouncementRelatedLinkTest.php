<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsRelatedLinkServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsAnnouncementRelatedLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create(['role_slug' => 'super_admin', 'is_locked' => false]);
    }

    public function test_announcement_button_follows_the_linked_event(): void
    {
        $open = $this->publish('Open Workshop', 'events', ['event' => [
            'requires_registration' => true,
            'formId' => 'activity-registration',
            'sessions' => [['date' => '2030-05-01', 'starts_at' => '10:00']],
        ]]);
        $display = $this->publish('Science Fair', 'events', ['event' => [
            'sessions' => [['date' => '2030-05-02', 'starts_at' => '10:00']],
        ]]);

        $toOpen = $this->publish('Workshop announced', 'announcements', ['related_kind' => 'article', 'related_article_id' => $open]);
        $toDisplay = $this->publish('Fair announced', 'announcements', [
            'related_kind' => 'article',
            'related_article_id' => $display,
            'cta_label_en' => 'See the programme',
            'cta_label_ar' => 'شاهد البرنامج',
        ]);

        $this->get('/en/news/'.$toOpen)->assertOk()
            ->assertSee('href="/en/news/'.$open.'/register"', false)
            ->assertSee('Register');
        $this->get('/ar/news/'.$toOpen)->assertOk()
            ->assertSee('href="/ar/news/'.$open.'/register"', false)
            ->assertSee('سجّل الآن');
        $this->get('/en/news/'.$toDisplay)->assertOk()
            ->assertSee('href="/en/news/'.$display.'"', false)
            ->assertSee('See the programme');
        $this->get('/ar/news/'.$toDisplay)->assertOk()->assertSee('شاهد البرنامج');
    }

    public function test_announcement_links_to_a_research_conference_or_an_address(): void
    {
        $conferenceId = $this->publishConferences();
        $toConference = $this->publish('Conference announced', 'announcements', ['related_kind' => 'conference', 'related_conference_id' => $conferenceId]);
        $toAddress = $this->publish('Portal announced', 'announcements', ['related_kind' => 'url', 'related_url' => 'https://portal.example.org/apply']);
        $toPage = $this->publish('Page announced', 'announcements', ['related_kind' => 'url', 'related_url' => '/en/admissions']);

        $this->get('/en/news/'.$toConference)->assertOk()
            ->assertSee('href="/en/research/conferences/register?event='.$conferenceId.'"', false);
        $this->get('/en/news/'.$toAddress)->assertOk()
            ->assertSee('href="https://portal.example.org/apply"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('View details');
        $this->get('/en/news/'.$toPage)->assertOk()
            ->assertSee('href="/en/admissions"', false);

        $this->assertArrayHasKey($conferenceId, app(NewsRelatedLinkServiceInterface::class)->relatedLinkOptions('conference', 'en'));
    }

    public function test_link_is_checked_and_kept_only_on_announcements(): void
    {
        $news = $this->publish('Plain news', 'news', []);
        $hidden = $this->prepare('Unpublished event', 'events', ['event' => ['sessions' => [['date' => '2030-07-01', 'starts_at' => '10:00']]]]);
        app(CmsWorkflowServiceInterface::class)->saveDraft((string) $hidden->targetKey, $hidden->payload, (int) $this->editor->getKey());

        $badUrl = $this->prepare('Bad link', 'announcements', ['related_kind' => 'url', 'related_url' => 'javascript:alert(1)']);
        $this->assertArrayHasKey('related_url', app(NewsArticleCmsServiceInterface::class)->publishErrors((string) $badUrl->targetKey, $badUrl->payload));

        $notEvent = $this->prepare('Wrong target', 'announcements', ['related_kind' => 'article', 'related_article_id' => $news]);
        $this->assertArrayHasKey('related_article_id', app(NewsArticleCmsServiceInterface::class)->publishErrors((string) $notEvent->targetKey, $notEvent->payload));

        $onNews = $this->prepare('News with link', 'news', ['related_kind' => 'url', 'related_url' => 'https://example.org']);
        $this->assertNull($onNews->payload['related_kind']);
        $this->assertNull($onNews->payload['related_url']);

        // A target that is not public shows no button.
        $toHidden = $this->publish('Hidden target', 'announcements', ['related_kind' => 'article', 'related_article_id' => $hidden->articleId]);
        $this->assertSame((int) $hidden->articleId, NewsArticle::query()->findOrFail($toHidden)->related_article_id);
        $this->get('/en/news/'.$toHidden)->assertOk()->assertDontSee('href="/en/news/'.$hidden->articleId.'"', false);

        $options = app(NewsRelatedLinkServiceInterface::class)->relatedLinkOptions('article', 'en');
        $this->assertArrayNotHasKey($news, $options);
        $this->assertArrayNotHasKey((int) $hidden->articleId, $options);
        $this->assertArrayHasKey((int) $hidden->articleId, app(NewsRelatedLinkServiceInterface::class)->relatedLinkOptions('article', 'en', (int) $hidden->articleId));
    }

    /** @param array<string, mixed> $extra */
    private function publish(string $title, string $category, array $extra): int
    {
        $prepared = $this->prepare($title, $category, $extra);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $workflow->saveDraft((string) $prepared->targetKey, $prepared->payload, (int) $this->editor->getKey());
        $this->assertTrue($workflow->publish((string) $prepared->targetKey, (int) $this->editor->getKey()));

        return (int) $prepared->articleId;
    }

    /** @param array<string, mixed> $extra */
    private function prepare(string $title, string $category, array $extra): NewsArticleCmsDataDTO
    {
        return app(NewsArticleCmsServiceInterface::class)->prepareDraft(new NewsArticleCmsDataDTO(null, [
            'news_category_id' => $this->categoryId($category),
            'is_enabled' => true,
            'is_featured' => false,
            'translations' => [
                ['locale' => 'ar', 'title' => 'عنوان '.$title, 'excerpt' => 'ملخص', 'body' => '<p>محتوى</p>'],
                ['locale' => 'en', 'title' => $title, 'excerpt' => 'Summary', 'body' => '<p>Body</p>'],
            ],
            'attachments' => [],
            'seoMeta' => [],
            ...$extra,
        ]), (int) $this->editor->getKey());
    }

    private function categoryId(string $slug): int
    {
        if (! NewsCategory::query()->where('slug', $slug)->exists()) {
            NewsCategory::query()->create([
                'slug' => $slug,
                'type' => $slug === 'announcements' ? 'announcement' : 'news',
                'is_enabled' => true,
            ]);
        }

        return (int) NewsCategory::query()->where('slug', $slug)->value('id');
    }

    /** Publishes the conference page and returns the ID of its first upcoming, registerable conference. */
    private function publishConferences(): string
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $conferences = app(ResearchPageServiceInterface::class)->getEditablePayload('research.conferences');

        foreach (['ar', 'en'] as $locale) {
            foreach ($conferences['translations'][$locale]['upcoming'] as &$conference) {
                $conference['date'] = 'May 2030';
                $conference['formId'] = 'conference-registration';
            }
            unset($conference);

            foreach ($conferences['translations'][$locale]['past'] as &$conference) {
                $conference['hasProceedings'] = false;
                unset($conference['proceedingsUrl']);
            }
            unset($conference);
        }

        $workflow->saveDraft('research.conferences', $conferences, (int) $this->editor->getKey());
        $this->assertTrue($workflow->publish('research.conferences', (int) $this->editor->getKey()));

        return (string) $conferences['translations']['en']['upcoming'][0]['id'];
    }
}
