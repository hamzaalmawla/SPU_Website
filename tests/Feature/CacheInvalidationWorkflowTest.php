<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Content\FacultyMemberDataDTO;
use App\DTOs\Content\FacultyMemberTranslationDataDTO;
use App\DTOs\Page\PageShellDataDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\DTOs\Seo\PageSeoInputDTO;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SettingValueDTO;
use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Page\AboutNavigationCard;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class CacheInvalidationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_about_card_mutation_invalidates_cached_public_html(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $card = $service->createCard('about.history', 'Before cache refresh', 'Before cache refresh');
        $this->assertTrue($service->publish($card->id));

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('Before cache refresh');

        $this->assertTrue($service->updateCard($card->id, [
            'title_override_ar' => 'After cache refresh',
            'title_override_en' => 'After cache refresh',
        ]));

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('After cache refresh')
            ->assertDontSee('Before cache refresh');
    }

    public function test_due_about_cards_are_published_and_visible(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $card = $service->createCard('about.history', 'Scheduled card', 'Scheduled card');
        $this->assertTrue($service->schedule($card->id, now()->addHour()->toDateTimeString()));

        AboutNavigationCard::query()->whereKey($card->id)->update([
            'publish_at' => now()->subMinute(),
        ]);

        $this->assertSame(1, $service->publishDueScheduled());
        $this->assertDatabaseHas('about_navigation_cards', [
            'id' => $card->id,
            'status' => 'published',
            'publish_at' => null,
        ]);
    }

    public function test_page_seo_mutation_invalidates_cached_public_html(): void
    {
        $author = $this->author();
        $pages = app(PageServiceInterface::class);
        $page = $pages->createPageShell(
            new PageShellDataDTO(
                slug: 'cache-seo-page',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            (int) $author->getKey(),
        );

        foreach (['ar' => 'Arabic cache page', 'en' => 'Cache SEO Page'] as $locale => $title) {
            $translation = new PageTranslationDTO(
                title: $title,
                headline: $title,
                body: '<p>Cache invalidation content.</p>',
            );

            if ($locale === 'ar') {
                $this->assertTrue($pages->updateArabicTranslation($page->id, $translation, (int) $author->getKey()));
            } else {
                $this->assertTrue($pages->updateEnglishTranslation($page->id, $translation, (int) $author->getKey()));
            }
        }

        $this->assertTrue($pages->updateEnglishSeo(
            $page->id,
            new PageSeoInputDTO(locale: 'en', title: 'Initial cached SEO title'),
            (int) $author->getKey(),
        ));
        $this->assertTrue($pages->publish($page->id, (int) $author->getKey()));

        $this->get('/en/cache-seo-page')
            ->assertOk()
            ->assertSee('<title>Initial cached SEO title</title>', false);

        $this->assertTrue($pages->updateEnglishSeo(
            $page->id,
            new PageSeoInputDTO(locale: 'en', title: 'Updated cached SEO title'),
            (int) $author->getKey(),
        ));

        $this->get('/en/cache-seo-page')
            ->assertOk()
            ->assertSee('<title>Updated cached SEO title</title>', false)
            ->assertDontSee('<title>Initial cached SEO title</title>', false);
    }

    public function test_page_mutation_forgets_all_menu_tree_locales_and_groups(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['header', 'footer', 'utility'] as $treeType) {
                $key = 'menu.tree.'.$treeType.'.'.$locale;
                app(CacheServiceInterface::class)->remember($key, static fn (): string => 'stale');
            }
        }

        $cacheService = app(CacheServiceInterface::class);

        $author = $this->author();
        $page = app(PageServiceInterface::class)->createPageShell(
            new PageShellDataDTO(
                slug: 'menu-cache-page',
                template: 'landing',
                isHomepageShell: false,
                status: 'draft',
            ),
            (int) $author->getKey(),
        );

        foreach (['ar', 'en'] as $locale) {
            $this->assertSame('fresh', $cacheService->remember('menu.tree.header.'.$locale, static fn (): string => 'fresh'));
            $this->assertSame('fresh', $cacheService->remember('menu.tree.footer.'.$locale, static fn (): string => 'fresh'));
            $this->assertSame('fresh', $cacheService->remember('menu.tree.utility.'.$locale, static fn (): string => 'fresh'));
        }

        $this->assertNotNull($page->id);
    }

    public function test_seo_settings_refresh_only_the_changed_locale_cache_key(): void
    {
        $cacheService = app(CacheServiceInterface::class);
        $cacheService->remember('settings.default_seo.ar', static fn (): string => 'stale-ar');
        $cacheService->remember('settings.default_seo.en', static fn (): string => 'stale-en');

        $this->assertTrue(app(SettingsServiceInterface::class)->updateGroup(
            new SettingsDTO('seo', 'ar', [
                new SettingValueDTO(
                    key: 'default_seo',
                    type: 'json',
                    jsonValue: ['title' => 'Updated Arabic SEO'],
                    isPublic: true,
                ),
            ]),
            (int) $this->author()->getKey(),
        ));

        $this->assertSame('fresh-ar', $cacheService->remember('settings.default_seo.ar', static fn (): string => 'fresh-ar'));
        $this->assertSame('stale-en', $cacheService->remember('settings.default_seo.en', static fn (): string => 'fresh-en'));
    }

    public function test_profile_mutation_invalidates_sitemap_html(): void
    {
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();
        $admin = $this->author();
        $data = $this->facultyMemberData('cached-profile', (int) $faculty->getKey());
        $member = app(ProfileAdminServiceInterface::class)->createFacultyMember($data, (int) $admin->getKey());

        FacultyMember::query()->whereKey($member->id)->update([
            'publication_status' => PublicationStatus::Published->value,
            'published_at' => now(),
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/en/about/profile/cached-profile', false);

        $this->assertTrue(app(ProfileAdminServiceInterface::class)->updateFacultyMember(
            (int) $member->id,
            $this->facultyMemberData('updated-cached-profile', (int) $faculty->getKey(), (int) $member->id),
            (int) $admin->getKey(),
        ));

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/en/about/profile/updated-cached-profile', false)
            ->assertDontSee('/en/about/profile/cached-profile', false);
    }

    public function test_facilities_caches_are_refreshed_after_cms_publish(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = $this->author();

        $initial = $facilities->getHub('en');
        $payload = $facilities->getEditablePayload('facilities.landing');
        $payload['translations']['en']['hero']['summary'] = 'Fresh facilities cache content.';
        $workflow->saveDraft('facilities.landing', $payload, (int) $author->getKey());

        $this->assertTrue($workflow->publish('facilities.landing', (int) $author->getKey()));

        $refreshed = $facilities->getHub('en');

        $this->assertNotSame(
            $initial->content['hero']['summary'] ?? null,
            $refreshed->content['hero']['summary'] ?? null,
        );
        $this->assertSame('Fresh facilities cache content.', $refreshed->content['hero']['summary'] ?? null);
    }

    private function author(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }

    private function facultyMemberData(string $slug, int $facultyId, ?int $id = null): FacultyMemberDataDTO
    {
        return new FacultyMemberDataDTO(
            id: $id,
            slug: $slug,
            facultyId: $facultyId,
            departmentId: null,
            email: 'cache-profile@example.test',
            phone: null,
            officeLocation: null,
            photoMediaId: null,
            cvMediaId: null,
            socialLinks: null,
            sortOrder: 1,
            isEnabled: true,
            translations: [
                new FacultyMemberTranslationDataDTO('ar', 'Arabic Cached Faculty Member', null, 'Lecturer', null, []),
                new FacultyMemberTranslationDataDTO('en', 'Cached Faculty Member', null, 'Lecturer', null, []),
            ],
            educations: [],
        );
    }
}
