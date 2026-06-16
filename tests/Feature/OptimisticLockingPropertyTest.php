<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\DTOs\Homepage\HomepageDraftDataDTO;
use App\DTOs\Page\PageDraftDataDTO;
use App\DTOs\Page\PageMetadataDTO;
use App\DTOs\Seo\PageSeoInputDTO;
use App\DTOs\Page\PageShellDataDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\Exceptions\ConflictException;
use App\Models\Homepage\HomepageDraft;
use App\Models\Page\PageDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for optimistic locking on draft save operations.
 *
 * Feature: codebase-audit-remediation, Property 5: Stale Version Rejection (Optimistic Locking)
 *
 * For any draft resource (homepage draft or page draft) at version V,
 * attempting to save with expectedVersion ≠ V SHALL throw a ConflictException
 * and leave the resource unchanged, preventing lost updates from concurrent editors.
 *
 * **Validates: Requirements 17.1, 17.2**
 */
#[Group('property')]
class OptimisticLockingPropertyTest extends TestCase
{
    use PropertyTestHelpers;
    use RefreshDatabase;

    private HomepagePublishingServiceInterface $publishingService;

    private HomepageSectionServiceInterface $homepageSectionService;

    private PageServiceInterface $pageService;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->publishingService = app(HomepagePublishingServiceInterface::class);
        $this->homepageSectionService = app(HomepageSectionServiceInterface::class);
        $this->pageService = app(PageServiceInterface::class);
        $this->author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Data Providers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate random wrong version offsets for homepage draft tests.
     *
     * @return array<string, array{0: int}>
     */
    public static function homepageDraftStaleVersionProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 20; $i++) {
            // Generate a wrong version: either a past version (negative offset) or a future version (positive offset)
            $offset = random_int(0, 1) === 0
                ? random_int(-100, -1)   // stale (past) version
                : random_int(1, 100);    // future version

            $cases["homepage_iteration_{$i}"] = [$offset];
        }

        return $cases;
    }

    /**
     * Generate random wrong version offsets for page draft tests.
     *
     * @return array<string, array{0: int}>
     */
    public static function pageDraftStaleVersionProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 20; $i++) {
            $offset = random_int(0, 1) === 0
                ? random_int(-100, -1)
                : random_int(1, 100);

            $cases["page_iteration_{$i}"] = [$offset];
        }

        return $cases;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 5 — Homepage Draft: Stale Version Rejection
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Property 5: For any homepage draft at version V, saving with expectedVersion ≠ V
     * throws ConflictException and leaves the resource unchanged.
     *
     * **Validates: Requirements 17.1**
     */
    #[Test]
    #[DataProvider('homepageDraftStaleVersionProvider')]
    public function homepage_draft_save_with_wrong_version_throws_conflict_exception(int $versionOffset): void
    {
        // Create an initial draft to establish a version
        $sections = $this->homepageSectionService->getSections()->all();
        $draft = $this->publishingService->saveDraft(
            new HomepageDraftDataDTO(sections: $sections),
            $this->author->id,
        );

        $currentVersion = $draft->version;
        $wrongVersion = $currentVersion + $versionOffset;

        // Ensure the wrong version is actually different
        if ($wrongVersion === $currentVersion) {
            $wrongVersion = $currentVersion + 1;
        }

        // Record the draft count before the stale save attempt
        $draftCountBefore = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->count();

        // Attempt to save with a wrong expectedVersion — must throw ConflictException
        $conflictThrown = false;
        $conflictCurrentVersion = null;

        try {
            $this->publishingService->saveDraft(
                new HomepageDraftDataDTO(sections: $sections),
                $this->author->id,
                $wrongVersion,
            );
        } catch (ConflictException $e) {
            $conflictThrown = true;
            $conflictCurrentVersion = $e->currentVersion;
        }

        $this->assertTrue(
            $conflictThrown,
            "ConflictException must be thrown when expectedVersion ({$wrongVersion}) ≠ current version ({$currentVersion})"
        );

        $this->assertSame(
            $currentVersion,
            $conflictCurrentVersion,
            "ConflictException must report the correct current version ({$currentVersion})"
        );

        // Verify the resource is unchanged — no new draft was created
        $draftCountAfter = HomepageDraft::query()
            ->where('target_type', 'homepage')
            ->count();

        $this->assertSame(
            $draftCountBefore,
            $draftCountAfter,
            'No new draft should be created when a version conflict is detected'
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 5 — Page Draft: Stale Version Rejection
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Property 5: For any page draft at version V, saving with expectedVersion ≠ V
     * throws ConflictException and leaves the resource unchanged.
     *
     * **Validates: Requirements 17.2**
     */
    #[Test]
    #[DataProvider('pageDraftStaleVersionProvider')]
    public function page_draft_save_with_wrong_version_throws_conflict_exception(int $versionOffset): void
    {
        // Create a page shell to attach drafts to
        $page = $this->pageService->createPageShell(
            new PageShellDataDTO(
                slug: 'test-page-' . self::randomSlugSegment(),
                template: 'default',
                isHomepageShell: false,
                status: 'draft',
            ),
            $this->author->id,
        );

        // Build a valid page draft payload
        $draftPayload = $this->buildPageDraftPayload();

        // Create an initial draft to establish a version
        $draft = $this->pageService->saveDraft(
            $page->id,
            $draftPayload,
            $this->author->id,
        );

        $currentVersion = $draft->version;
        $wrongVersion = $currentVersion + $versionOffset;

        // Ensure the wrong version is actually different
        if ($wrongVersion === $currentVersion) {
            $wrongVersion = $currentVersion + 1;
        }

        // Record the draft count before the stale save attempt
        $draftCountBefore = PageDraft::query()
            ->where('page_id', $page->id)
            ->count();

        // Attempt to save with a wrong expectedVersion — must throw ConflictException
        $conflictThrown = false;
        $conflictCurrentVersion = null;

        try {
            $this->pageService->saveDraft(
                $page->id,
                $draftPayload,
                $this->author->id,
                $wrongVersion,
            );
        } catch (ConflictException $e) {
            $conflictThrown = true;
            $conflictCurrentVersion = $e->currentVersion;
        }

        $this->assertTrue(
            $conflictThrown,
            "ConflictException must be thrown when expectedVersion ({$wrongVersion}) ≠ current version ({$currentVersion})"
        );

        $this->assertSame(
            $currentVersion,
            $conflictCurrentVersion,
            "ConflictException must report the correct current version ({$currentVersion})"
        );

        // Verify the resource is unchanged — no new draft was created
        $draftCountAfter = PageDraft::query()
            ->where('page_id', $page->id)
            ->count();

        $this->assertSame(
            $draftCountBefore,
            $draftCountAfter,
            'No new draft should be created when a version conflict is detected'
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function buildPageDraftPayload(): PageDraftDataDTO
    {
        return new PageDraftDataDTO(
            metadata: new PageMetadataDTO(
                slug: 'test-page-' . self::randomSlugSegment(),
                template: 'default',
                isHomepageShell: false,
                status: 'draft',
            ),
            arabicTranslation: new PageTranslationDTO(
                title: 'صفحة اختبار',
                body: '<p>محتوى اختبار</p>',
            ),
            englishTranslation: new PageTranslationDTO(
                title: 'Test Page',
                body: '<p>Test content</p>',
            ),
            arabicSeo: new PageSeoInputDTO(
                locale: 'ar',
                title: 'صفحة اختبار SEO',
            ),
            englishSeo: new PageSeoInputDTO(
                locale: 'en',
                title: 'Test Page SEO',
            ),
        );
    }
}
