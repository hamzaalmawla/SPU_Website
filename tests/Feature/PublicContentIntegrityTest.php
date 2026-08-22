<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\Media\MediaAsset;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicContentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_fixture_only_content_and_generated_research_titles_are_not_public(): void
    {
        $publication = ResearchPublication::query()->create([
            'published_at' => now()->subDay(),
            'publication_year' => (int) now()->format('Y'),
            'is_enabled' => true,
        ]);
        ResearchPublicationTranslation::query()->create([
            'research_publication_id' => $publication->getKey(),
            'locale' => 'en',
            'title' => 'Research publication '.$publication->getKey(),
        ]);

        $this->get('/en/research')->assertOk()->assertDontSee('Research at SPU');
        $this->get('/en/research/publications')->assertOk()->assertDontSee('Research publication '.$publication->getKey());
        $this->get('/en/research/publications/ai-dental-diagnostics')->assertNotFound();
        $this->get('/en/news/events-list/evt-001')->assertNotFound();
        $this->get('/en/news/gallery')->assertOk()->assertDontSee('Syrian Private University Campus');
        $this->get('/en/facilities/artificial-intelligence/projects/artificial-intelligence-project-1')->assertNotFound();
        $this->get('/en/admissions/requirements')->assertNotFound();
        $this->get('/en/campus-life/career-development/jobs/lecturer-computer-science')->assertNotFound();
        $this->get('/en/e-services')->assertOk()->assertDontSee('Under Construction');
    }

    public function test_published_database_research_remains_public_without_fixture_fallback(): void
    {
        $publication = ResearchPublication::query()->create([
            'published_at' => now()->subDay(),
            'publication_year' => (int) now()->format('Y'),
            'is_enabled' => true,
        ]);
        ResearchPublicationTranslation::query()->create([
            'research_publication_id' => $publication->getKey(),
            'locale' => 'en',
            'title' => 'Verified Migrated Research Record',
            'abstract' => 'A real published database record.',
        ]);

        $this->get('/en/research/publications')
            ->assertOk()
            ->assertSee('Verified Migrated Research Record')
            ->assertDontSee('AI-Driven Predictive Models for Early Dental Caries Detection');
    }

    public function test_unpublish_removes_cms_only_content_from_public_routes(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $userId = (int) $author->getKey();

        $admissions = app(AdmissionsPageServiceInterface::class)->getEditablePayload('admissions.requirements');
        $admissions['translations']['en']['title'] = 'Integrity Admissions Target';
        $workflow->saveDraft('admissions.requirements', $admissions, $userId);
        $this->assertTrue($workflow->publish('admissions.requirements', $userId));
        $this->get('/en/admissions/requirements')->assertOk()->assertSee('Integrity Admissions Target');
        $this->assertTrue($workflow->unpublish('admissions.requirements', $userId));
        $this->get('/en/admissions/requirements')->assertNotFound();

        $campus = app(CampusLifePageServiceInterface::class)->getEditablePayload('campus_life.services');
        $campus['translations']['en']['hero']['title'] = 'Integrity Campus Target';
        $workflow->saveDraft('campus_life.services', $campus, $userId);
        $this->assertTrue($workflow->publish('campus_life.services', $userId));
        $this->get('/en/campus-life/services')->assertOk()->assertSee('Integrity Campus Target');
        $this->assertTrue($workflow->unpublish('campus_life.services', $userId));
        $this->get('/en/campus-life/services')->assertNotFound();

        $events = app(NewsServiceInterface::class)->getEditablePayload('news.events');
        $events['translations']['en']['upcoming'] = [[
            'id' => 'integrity-event',
            'title' => 'Integrity Published Event',
            'startsAt' => now()->addMonth()->toIso8601String(),
        ]];
        $workflow->saveDraft('news.events', $events, $userId);
        $this->assertTrue($workflow->publish('news.events', $userId));
        $this->get('/en/news/events-list/integrity-event')->assertOk()->assertSee('Integrity Published Event');
        $this->assertTrue($workflow->unpublish('news.events', $userId));
        $this->get('/en/news/events-list/integrity-event')->assertNotFound();

        $gallery = app(NewsServiceInterface::class)->getEditablePayload('news.gallery');
        $media = MediaAsset::query()->create([
            'disk' => 'public',
            'directory' => 'gallery',
            'filename' => 'integrity.jpg',
            'original_name' => 'integrity.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'checksum' => hash('sha256', 'integrity-gallery'),
            'media_type' => 'image',
            'library_scope' => 'main',
            'metadata_status' => 'reviewed',
            'width' => 1200,
            'height' => 800,
            'title_ar' => 'عنصر معرض موثق',
            'title_en' => 'Integrity Gallery Item',
            'alt_text_ar' => 'صورة موثقة',
            'alt_text_en' => 'Integrity gallery image',
            'path' => 'gallery/integrity.jpg',
        ]);
        $gallery['translations']['en']['items'] = [[
            'id' => 'integrity-gallery-item',
            'mediaId' => (int) $media->getKey(),
            'categoryId' => 'campus-life',
            'categoryLabel' => 'Campus Life',
        ]];
        $gallery['translations']['ar']['items'] = [[
            'id' => 'integrity-gallery-item',
            'mediaId' => (int) $media->getKey(),
            'categoryId' => 'campus-life',
            'categoryLabel' => 'الحياة الجامعية',
        ]];
        $workflow->saveDraft('news.gallery', $gallery, $userId);
        $this->assertTrue($workflow->publish('news.gallery', $userId));
        $this->get('/en/news/gallery')->assertOk()->assertSee('Integrity Gallery Item');
        $this->assertTrue($workflow->unpublish('news.gallery', $userId));
        $this->get('/en/news/gallery')->assertOk()->assertDontSee('Integrity Gallery Item');

        $research = app(ResearchPageServiceInterface::class)->getEditablePayload('research.projects');
        $research['translations']['en']['items'][0]['slug'] = 'integrity-research-project';
        $research['translations']['en']['items'][0]['title'] = 'Integrity Research Project';
        $research['translations']['ar']['items'][0]['slug'] = 'integrity-research-project';
        $research['translations']['ar']['items'][0]['title'] = 'مشروع بحثي موثق';
        $workflow->saveDraft('research.projects', $research, $userId);
        $this->assertTrue($workflow->publish('research.projects', $userId));
        $this->get('/en/research/projects/integrity-research-project')->assertOk()->assertSee('Integrity Research Project');
        $this->assertTrue($workflow->unpublish('research.projects', $userId));
        $this->get('/en/research/projects/integrity-research-project')->assertNotFound();

        $faculty = app(FacultyPageServiceInterface::class)->getEditablePayload('facilities.artificial-intelligence.projects');
        $faculty['translations']['en']['items'][] = [
            'slug' => 'integrity-faculty-project',
            'title' => 'Integrity Faculty Project',
            'summary' => 'Published CMS project.',
            'tag' => 'Verified',
            'team' => 'Verified team',
            'supervisor' => 'Verified supervisor',
        ];
        $faculty['translations']['ar']['items'][] = [
            'slug' => 'integrity-faculty-project',
            'title' => 'مشروع كلية موثق',
            'summary' => 'مشروع منشور.',
            'tag' => 'موثق',
            'team' => 'فريق موثق',
            'supervisor' => 'مشرف موثق',
        ];
        $workflow->saveDraft('facilities.artificial-intelligence.projects', $faculty, $userId);
        $this->assertTrue($workflow->publish('facilities.artificial-intelligence.projects', $userId));
        $this->get('/en/facilities/artificial-intelligence/projects/integrity-faculty-project')->assertOk()->assertSee('Integrity Faculty Project');
        $this->assertTrue($workflow->unpublish('facilities.artificial-intelligence.projects', $userId));
        $this->get('/en/facilities/artificial-intelligence/projects/integrity-faculty-project')->assertNotFound();

        $suggestions = app(EServicesPageServiceInterface::class)->getSuggestionsComplaintsEditablePayload();
        $suggestions['translations']['en']['hero']['title'] = 'Integrity E-Services Target';
        $workflow->saveDraft('e_services.suggestions-complaints', $suggestions, $userId);
        $this->assertTrue($workflow->publish('e_services.suggestions-complaints', $userId));
        $this->get('/en/e-services/suggestions-complaints')->assertOk()->assertSee('Integrity E-Services Target');
        $this->assertTrue($workflow->unpublish('e_services.suggestions-complaints', $userId));
        $this->get('/en/e-services/suggestions-complaints')->assertNotFound();
    }
}
