<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Contracts\PageServiceInterface;
use App\DTOs\PageDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageShellDataDTO;
use App\DTOs\PageTranslationDTO;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  Page creation with bilingual translations
    // ──────────────────────────────────────────────────────────────

    public function test_create_page_shell_with_arabic_and_english_translations(): void
    {
        $page = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'about-us',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );

        $this->assertInstanceOf(PageDTO::class, $page);
        $this->assertSame('about-us', $page->metadata->slug);
        $this->assertSame('landing', $page->metadata->template);
        $this->assertSame('draft', $page->metadata->status);

        // Add Arabic translation.
        $this->assertTrue(
            $this->pageService()->updateArabicTranslation($page->id, new PageTranslationDTO(
                title: 'من نحن',
                headline: 'تعرف على الجامعة',
                body: '<p>محتوى عربي تجريبي</p>',
            ), $this->author()->id),
        );

        // Add English translation.
        $this->assertTrue(
            $this->pageService()->updateEnglishTranslation($page->id, new PageTranslationDTO(
                title: 'About Us',
                headline: 'Learn About the University',
                body: '<p>Sample English content</p>',
            ), $this->author()->id),
        );

        // Verify both translations are stored correctly.
        $reloaded = $this->pageService()->getAdminEditorPayload($page->id);

        $this->assertSame('من نحن', $reloaded->arabicTranslation->title);
        $this->assertSame('تعرف على الجامعة', $reloaded->arabicTranslation->headline);
        $this->assertSame('About Us', $reloaded->englishTranslation->title);
        $this->assertSame('Learn About the University', $reloaded->englishTranslation->headline);

        $this->assertDatabaseHas('audit_logs', ['action' => 'page.created']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Translation update isolation
    // ──────────────────────────────────────────────────────────────

    public function test_updating_one_locale_translation_does_not_affect_the_other(): void
    {
        $page = $this->createPageWithTranslations();

        // Update only the English translation.
        $this->assertTrue(
            $this->pageService()->updateEnglishTranslation($page->id, new PageTranslationDTO(
                title: 'Updated English Title',
                headline: 'Updated English Headline',
                body: '<p>Updated English body</p>',
            ), $this->author()->id),
        );

        $reloaded = $this->pageService()->getAdminEditorPayload($page->id);

        // English should be updated.
        $this->assertSame('Updated English Title', $reloaded->englishTranslation->title);
        $this->assertSame('Updated English Headline', $reloaded->englishTranslation->headline);

        // Arabic should remain unchanged.
        $this->assertSame('من نحن', $reloaded->arabicTranslation->title);
        $this->assertSame('تعرف على الجامعة', $reloaded->arabicTranslation->headline);
    }

    public function test_updating_arabic_translation_does_not_affect_english(): void
    {
        $page = $this->createPageWithTranslations();

        // Update only the Arabic translation.
        $this->assertTrue(
            $this->pageService()->updateArabicTranslation($page->id, new PageTranslationDTO(
                title: 'عنوان عربي محدث',
                headline: 'عنوان رئيسي محدث',
                body: '<p>محتوى عربي محدث</p>',
            ), $this->author()->id),
        );

        $reloaded = $this->pageService()->getAdminEditorPayload($page->id);

        // Arabic should be updated.
        $this->assertSame('عنوان عربي محدث', $reloaded->arabicTranslation->title);
        $this->assertSame('عنوان رئيسي محدث', $reloaded->arabicTranslation->headline);

        // English should remain unchanged.
        $this->assertSame('About Us', $reloaded->englishTranslation->title);
        $this->assertSame('Learn About the University', $reloaded->englishTranslation->headline);
    }

    // ──────────────────────────────────────────────────────────────
    //  Draft → Publish workflow
    // ──────────────────────────────────────────────────────────────

    public function test_publish_transitions_status_and_sets_published_at_timestamp(): void
    {
        $page = $this->createPageWithTranslations();

        $this->assertSame('draft', $page->metadata->status);
        $this->assertNull($page->publishedAt);

        // Publish the page.
        $this->assertTrue(
            $this->pageService()->publish($page->id, $this->author()->id),
        );

        $published = $this->pageService()->getAdminEditorPayload($page->id);

        $this->assertSame('published', $published->metadata->status);
        $this->assertNotNull($published->publishedAt);

        $this->assertDatabaseHas('audit_logs', ['action' => 'page.publish']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Draft pages excluded from public queries
    // ──────────────────────────────────────────────────────────────

    public function test_draft_pages_are_not_returned_by_public_query_methods(): void
    {
        $page = $this->createPageWithTranslations();

        // Page is still in draft status — should not be publicly accessible.
        $this->assertNull(
            $this->pageService()->getPublicPageBySlug('test-page', 'en'),
        );

        $this->assertNull(
            $this->pageService()->getPublicPageBySlug('test-page', 'ar'),
        );
    }

    public function test_published_page_is_returned_by_public_query(): void
    {
        $page = $this->createPageWithTranslations();

        $this->assertTrue(
            $this->pageService()->publish($page->id, $this->author()->id),
        );

        $publicPage = $this->pageService()->getPublicPageBySlug('test-page', 'en');

        $this->assertInstanceOf(PageDTO::class, $publicPage);
        $this->assertSame('About Us', $publicPage->englishTranslation->title);
    }

    public function test_unpublished_page_is_no_longer_returned_by_public_query(): void
    {
        $page = $this->createPageWithTranslations();

        $this->assertTrue($this->pageService()->publish($page->id, $this->author()->id));
        $this->assertNotNull($this->pageService()->getPublicPageBySlug('test-page', 'en'));

        $this->assertTrue($this->pageService()->unpublish($page->id, $this->author()->id));
        $this->assertNull($this->pageService()->getPublicPageBySlug('test-page', 'en'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'page.unpublish']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Publish validation rejects incomplete pages
    // ──────────────────────────────────────────────────────────────

    public function test_publish_rejects_page_without_slug(): void
    {
        $page = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: '',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );

        $this->pageService()->updateEnglishTranslation($page->id, new PageTranslationDTO(
            title: 'No Slug Page',
        ), $this->author()->id);

        $this->assertFalse(
            $this->pageService()->publish($page->id, $this->author()->id),
        );
    }

    public function test_publish_rejects_page_without_template(): void
    {
        $page = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'no-template',
                template: '',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );

        $this->pageService()->updateEnglishTranslation($page->id, new PageTranslationDTO(
            title: 'No Template Page',
        ), $this->author()->id);

        $this->assertFalse(
            $this->pageService()->publish($page->id, $this->author()->id),
        );
    }

    public function test_publish_rejects_page_without_any_translations(): void
    {
        $page = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'no-translations',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );

        $this->assertFalse(
            $this->pageService()->publish($page->id, $this->author()->id),
        );
    }

    public function test_publish_rejects_page_missing_one_locale_title(): void
    {
        $page = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'english-only-page',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );

        $this->pageService()->updateEnglishTranslation($page->id, new PageTranslationDTO(
            title: 'English Only Page',
        ), $this->author()->id);

        $this->assertFalse($this->pageService()->publish($page->id, $this->author()->id));
    }

    public function test_update_metadata_rejects_self_parent(): void
    {
        $page = $this->createPageWithTranslations();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A page cannot be its own parent.');

        $this->pageService()->updateBaseMetadata($page->id, new PageMetadataDTO(
            slug: $page->metadata->slug,
            template: $page->metadata->template,
            isHomepageShell: $page->metadata->isHomepageShell,
            status: $page->metadata->status,
            parentPageId: $page->id,
        ), $this->author()->id);
    }

    public function test_update_metadata_rejects_descendant_parent_cycle(): void
    {
        $parent = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'parent-page',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );
        $child = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'child-page',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
                parentPageId: $parent->id,
            ),
            $this->author()->id,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A page cannot use one of its descendants as parent.');

        $this->pageService()->updateBaseMetadata($parent->id, new PageMetadataDTO(
            slug: $parent->metadata->slug,
            template: $parent->metadata->template,
            isHomepageShell: $parent->metadata->isHomepageShell,
            status: $parent->metadata->status,
            parentPageId: $child->id,
        ), $this->author()->id);
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function pageService(): PageServiceInterface
    {
        return app(PageServiceInterface::class);
    }

    private function author(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    /**
     * Create a draft page shell with both Arabic and English translations.
     */
    private function createPageWithTranslations(): PageDTO
    {
        $page = $this->pageService()->createPageShell(
            new PageShellDataDTO(
                slug: 'test-page',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author()->id,
        );

        $this->pageService()->updateArabicTranslation($page->id, new PageTranslationDTO(
            title: 'من نحن',
            headline: 'تعرف على الجامعة',
            body: '<p>محتوى عربي تجريبي</p>',
        ), $this->author()->id);

        $this->pageService()->updateEnglishTranslation($page->id, new PageTranslationDTO(
            title: 'About Us',
            headline: 'Learn About the University',
            body: '<p>Sample English content</p>',
        ), $this->author()->id);

        return $this->pageService()->getAdminEditorPayload($page->id);
    }
}
