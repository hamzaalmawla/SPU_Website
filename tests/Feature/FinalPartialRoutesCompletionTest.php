<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\Filament\Pages\ManageCampusLife;
use App\Filament\Pages\ManageEServicesPage;
use App\Filament\Pages\ManageNews;
use App\Filament\Pages\ManagePharmacyFaculty;
use App\Models\Cms\CmsDraft;
use App\Models\Form\DynamicFormSubmission;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class FinalPartialRoutesCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_campus_landing_only_renders_verified_portals_and_removes_unverified_figures(): void
    {
        $service = app(CampusLifePageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = $this->superAdmin();
        $payload = $service->getEditablePayload('campus_life.landing');
        $workflow->saveDraft('campus_life.landing', $payload, (int) $author->id);
        $this->assertTrue($workflow->publish('campus_life.landing', (int) $author->id));

        $page = $service->getLanding('en');
        $this->assertNotNull($page);

        $this->assertSame([], $page->landing['stats']);
        $this->assertNotEmpty($page->landing['portalGuidance']);
        $this->assertNotEmpty($page->landing['portals']);
        foreach ($page->landing['portals'] as $portal) {
            $this->assertNotSame('#', $portal['url']);
            $this->assertStringStartsWith('/en/', $portal['url']);
        }

        $this->get('/en/campus-life')
            ->assertOk()
            ->assertSee('Verified student portal destinations')
            ->assertDontSee('8,500')
            ->assertDontSee('96%');

        $this->get('/sitemaps/sitemap-static.xml')->assertOk()->assertSee('/en/campus-life');
    }

    public function test_all_five_published_routes_render_in_both_directions_with_locale_alternates(): void
    {
        $author = $this->superAdmin();
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payloads = [
            'campus_life.landing' => app(CampusLifePageServiceInterface::class)->getEditablePayload('campus_life.landing'),
            'campus_life.virtual_tour' => app(VirtualTourPageServiceInterface::class)->getEditablePayload(),
            'e_services.suggestions-complaints' => app(EServicesPageServiceInterface::class)->getSuggestionsComplaintsEditablePayload(),
            'news.articles' => app(NewsServiceInterface::class)->getEditablePayload('news.articles'),
            'facilities.pharmacy.training' => app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.pharmacy.training'),
        ];

        foreach (['ar', 'en'] as $locale) {
            $payloads['facilities.pharmacy.training']['translations'][$locale]['seoTitle'] = $locale === 'ar' ? 'تدريب الصيدلة' : 'Pharmacy Training';
            $payloads['facilities.pharmacy.training']['translations'][$locale]['seoDescription'] = $locale === 'ar' ? 'التدريب العملي في كلية الصيدلة.' : 'Practical training at the Faculty of Pharmacy.';
            $payloads['facilities.pharmacy.training']['translations'][$locale]['seoImage'] = '/images/pharmacy-place.jpg';
            foreach ($payloads['facilities.pharmacy.training']['translations'][$locale]['payload']['facts'] ?? [] as $index => $fact) {
                $payloads['facilities.pharmacy.training']['translations'][$locale]['payload']['facts'][$index]['verified'] = true;
            }
        }

        foreach ($payloads as $targetKey => $payload) {
            $workflow->saveDraft($targetKey, $payload, (int) $author->id);
            $this->assertTrue($workflow->publish($targetKey, (int) $author->id));
        }

        foreach (['campus-life', 'virtual-tour', 'e-services/suggestions-complaints', 'news/articles', 'facilities/pharmacy/training'] as $path) {
            $this->get('/ar/'.$path)
                ->assertOk()
                ->assertSee('<html lang="ar" dir="rtl">', false)
                ->assertSee('href="/en/'.$path.'"', false);
            $this->get('/en/'.$path)
                ->assertOk()
                ->assertSee('<html lang="en" dir="ltr">', false)
                ->assertSee('href="/ar/'.$path.'"', false);
        }
    }

    public function test_virtual_tour_has_curated_editor_protected_workflow_and_accessible_controls(): void
    {
        $author = $this->superAdmin();
        $service = app(VirtualTourPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $service->getEditablePayload();
        $payload['translations']['en']['hero']['title'] = 'Editable Campus Photo Tour';
        $payload['translations']['ar']['hero']['title'] = 'جولة صور قابلة للتحرير';

        $workflow->saveDraft('campus_life.virtual_tour', $payload, (int) $author->id);
        $this->assertTrue($workflow->readiness('campus_life.virtual_tour')->isReady);
        $this->get('/en/virtual-tour')->assertOk()->assertDontSee('Editable Campus Photo Tour');

        $preview = $workflow->preview('campus_life.virtual_tour', 'en', (int) $author->id);
        $this->get($preview->previewUrl)->assertOk()->assertSee('Editable Campus Photo Tour')->assertSee('Preview mode');
        $this->assertTrue($workflow->publish('campus_life.virtual_tour', (int) $author->id));
        $this->get('/sitemaps/sitemap-static.xml')->assertOk()->assertSee('/en/virtual-tour');

        $this->get('/en/virtual-tour')
            ->assertOk()
            ->assertSee('Editable Campus Photo Tour')
            ->assertSee('x-data="virtualTour"', false)
            ->assertSee('x-on:pointerdown="startPan"', false)
            ->assertSee('x-on:keydown="handleKey"', false)
            ->assertSee('x-on:click="toggleAutoplay"', false)
            ->assertSee('x-on:click="toggleFullscreen"', false)
            ->assertDontSee('360 degree', false);

        $this->actingAs($author, 'web');
        Livewire::test(ManageCampusLife::class)
            ->set('data.target_key', 'campus_life.virtual_tour')
            ->call('loadTarget', 'campus_life.virtual_tour')
            ->assertSee('Interactive Photo Viewer')
            ->assertSee('Photo Scenes');
    }

    public function test_suggestions_page_cms_and_secure_private_submission_pipeline(): void
    {
        Storage::fake('local');
        $author = $this->superAdmin();
        $service = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $service->getSuggestionsComplaintsEditablePayload();
        $payload['translations']['en']['hero']['title'] = 'Published Feedback Desk';
        $payload['translations']['ar']['hero']['title'] = 'مكتب الملاحظات المنشور';

        $workflow->saveDraft('e_services.suggestions-complaints', $payload, (int) $author->id);
        $preview = $workflow->preview('e_services.suggestions-complaints', 'en', (int) $author->id);
        $this->get($preview->previewUrl)->assertOk()->assertSee('Published Feedback Desk');
        $this->assertTrue($workflow->publish('e_services.suggestions-complaints', (int) $author->id));
        $this->get('/sitemaps/sitemap-static.xml')->assertOk()->assertSee('/en/e-services/suggestions-complaints');

        $this->post('/en/e-services/suggestions-complaints', [
            'fullName' => 'Secure Reviewer',
            'email' => 'reviewer@example.com',
            'phone' => '+963110000000',
            'requestType' => 'suggestion',
            'subject' => 'Improve service',
            'message' => 'Please review this suggestion in the secure review queue.',
            'attachment' => UploadedFile::fake()->create('evidence.pdf', 256, 'application/pdf'),
            'consent' => '1',
        ])->assertRedirect('/en/e-services/suggestions-complaints');

        $submission = DynamicFormSubmission::query()->where('form_id', 'suggestions-complaints')->firstOrFail();
        $this->assertSame('e-services-suggestions-complaints', $submission->payload_json['_context']['source']);
        $this->assertSame('Secure Reviewer', $submission->applicant_name);
        $this->assertSame('local', $submission->files_json['attachment']['disk']);
        Storage::disk('local')->assertExists($submission->files_json['attachment']['path']);

        $this->actingAs($author, 'web');
        Livewire::test(ManageEServicesPage::class)
            ->set('data.target_key', 'e_services.suggestions-complaints')
            ->call('loadTarget', 'e_services.suggestions-complaints')
            ->assertSee('Suggestions &amp; Complaints Page', false)
            ->assertSee('Consent Label');
    }

    public function test_suggestions_submission_rejects_unsafe_attachments_and_missing_consent(): void
    {
        Storage::fake('local');

        $this->from('/en/e-services/suggestions-complaints')->post('/en/e-services/suggestions-complaints', [
            'fullName' => 'Invalid Attachment',
            'email' => 'invalid@example.com',
            'phone' => '+963110000000',
            'requestType' => 'complaint',
            'subject' => 'Unsafe upload',
            'message' => 'This submission must not be stored.',
            'attachment' => UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream'),
        ])->assertRedirect('/en/e-services/suggestions-complaints')
            ->assertSessionHasErrors(['attachment', 'consent']);

        $this->assertDatabaseCount('dynamic_form_submissions', 0);
        Storage::disk('local')->assertMissing('dynamic-form-submissions');
    }

    public function test_news_articles_shell_is_cms_managed_and_article_shares_use_canonical_url(): void
    {
        $author = $this->superAdmin();
        $news = app(NewsServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $news->getEditablePayload('news.articles');
        $payload['translations']['en']['title'] = 'Published Article Library';
        $payload['translations']['ar']['title'] = 'مكتبة المقالات المنشورة';

        $workflow->saveDraft('news.articles', $payload, (int) $author->id);
        $this->get('/en/news/articles')->assertOk()->assertDontSee('Published Article Library');
        $preview = $workflow->preview('news.articles', 'en', (int) $author->id);
        $this->get($preview->previewUrl)->assertOk()->assertSee('Published Article Library');
        $this->assertTrue($workflow->publish('news.articles', (int) $author->id));
        $this->get('/sitemaps/sitemap-static.xml')->assertOk()->assertSee('/en/news/articles');

        $this->get('/en/news/articles?search=University')
            ->assertOk()
            ->assertSee('Published Article Library')
            ->assertSee('role="search"', false)
            ->assertSee('name="search"', false);

        $createdArticle = $this->createPublishedArticle();
        $article = $news->getPublicArticle((string) $createdArticle->id, 'en');
        $this->assertNotNull($article);
        $canonical = url($article->url);
        $this->get($article->url)
            ->assertOk()
            ->assertSee('data-share-url="'.$canonical.'"', false)
            ->assertSee('facebook.com/sharer/sharer.php?u='.urlencode($canonical), false)
            ->assertDontSee('facebook.com/SPUpage.sy', false)
            ->assertDontSee('telegram.me/SPUchannel', false);

        $this->actingAs($author, 'web')->withSession(['admin_locale' => 'en']);
        Livewire::test(ManageNews::class)->set('data.target_key', 'news.articles')->call('loadTarget', 'news.articles')->assertSee('News articles page');
    }

    public function test_only_pharmacy_registers_training_and_its_full_workflow_is_available(): void
    {
        $registry = app(CmsTargetRegistryInterface::class);
        $trainingTargets = $registry->all()->filter(fn ($target): bool => str_ends_with($target->key, '.training'))->values();
        $this->assertCount(1, $trainingTargets);
        $this->assertSame('facilities.pharmacy.training', $trainingTargets->first()->key);

        $author = $this->superAdmin();
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $payload = $facilities->getEditablePayload('facilities.pharmacy.training');
        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale]['seoTitle'] = $locale === 'ar' ? 'تدريب الصيدلة' : 'Pharmacy Training';
            $payload['translations'][$locale]['seoDescription'] = $locale === 'ar' ? 'التدريب العملي في كلية الصيدلة.' : 'Practical training at the Faculty of Pharmacy.';
            $payload['translations'][$locale]['seoImage'] = '/images/pharmacy-place.jpg';
            foreach ($payload['translations'][$locale]['payload']['facts'] ?? [] as $index => $fact) {
                $payload['translations'][$locale]['payload']['facts'][$index]['verified'] = true;
            }
        }
        $payload['translations']['en']['title'] = 'Published Pharmacy Training';
        $payload['translations']['en']['payload']['hero']['title'] = 'Published Pharmacy Training';

        $workflow->saveDraft('facilities.pharmacy.training', $payload, (int) $author->id);
        $readiness = $workflow->readiness('facilities.pharmacy.training');
        $this->assertTrue($readiness->isReady, json_encode($readiness->errors));
        $preview = $workflow->preview('facilities.pharmacy.training', 'en', (int) $author->id);
        $this->get($preview->previewUrl)->assertOk()->assertSee('Published Pharmacy Training');
        $this->assertTrue($workflow->publish('facilities.pharmacy.training', (int) $author->id));
        $this->get('/sitemaps/sitemap-static.xml')->assertOk()->assertSee('/en/facilities/pharmacy/training');
        $this->get('/en/facilities/pharmacy/training')->assertOk()->assertSee('Published Pharmacy Training');
        $this->assertTrue($workflow->unpublish('facilities.pharmacy.training', (int) $author->id));
        $workflow->saveDraft('facilities.pharmacy.training', $payload, (int) $author->id);
        $this->assertTrue($workflow->schedule('facilities.pharmacy.training', now()->addHour(), (int) $author->id));
        $this->assertDatabaseHas('cms_drafts', [
            'target_key' => 'facilities.pharmacy.training',
            'status' => 'scheduled',
        ]);
        $this->assertTrue($workflow->unpublish('facilities.pharmacy.training', (int) $author->id));
        $this->assertSame('draft', CmsDraft::query()->where('target_key', 'facilities.pharmacy.training')->latest('id')->value('status'));

        $this->actingAs($author, 'web');
        Livewire::test(ManagePharmacyFaculty::class)
            ->set('data.target_key', 'facilities.pharmacy.training')
            ->call('loadTarget', 'facilities.pharmacy.training')
            ->assertSee('Training program steps')
            ->assertSee('Approved training destinations');
    }

    private function superAdmin(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    private function createPublishedArticle(): NewsArticle
    {
        $category = NewsCategory::query()->create([
            'slug' => 'final-route-news',
            'type' => 'news',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        $category->translations()->createMany([
            ['locale' => 'ar', 'name' => 'أخبار'],
            ['locale' => 'en', 'name' => 'News'],
        ]);
        $article = NewsArticle::query()->create([
            'news_category_id' => $category->id,
            'slug' => 'canonical-share-test',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => 1,
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => 'اختبار المشاركة', 'excerpt' => 'مقتطف', 'body' => '<p>المحتوى</p>'],
            ['locale' => 'en', 'title' => 'Canonical Share Test', 'excerpt' => 'Excerpt', 'body' => '<p>Body</p>'],
        ]);

        return $article;
    }
}
