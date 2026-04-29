<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\PreviewServiceInterface;
use App\Models\HomepageDraft;
use App\Models\Page;
use App\Models\PageDraft;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_locale_homepages_render_from_real_runtime_data(): void
    {
        $this->get('/ar')
            ->assertOk()
            ->assertSee('الجامعة السورية الخاصة')
            ->assertSee('كلياتنا الجامعية')
            ->assertDontSee('academic faculties')
            ->assertDontSee('Public AR homepage');

        $this->get('/en')
            ->assertOk()
            ->assertSee('Syrian Private University')
            ->assertSee('Our Faculties')
            ->assertDontSee('Public EN homepage');
    }

    public function test_top_level_landing_page_renders_and_includes_breadcrumbs(): void
    {
        $this->get('/en/about')
            ->assertOk()
            ->assertSee('About')
            ->assertSee('Home');
    }

    public function test_public_shell_renders_navigation_and_footer_payloads(): void
    {
        $this->get('/en')
            ->assertOk()
            ->assertSee('About')
            ->assertSee('Student Portal')
            ->assertSee('EXPLORE SPU')
            ->assertSee('Apply Now')
            ->assertSee('Contact SPU')
            ->assertDontSee('Connect');

        $this->get('/ar')
            ->assertOk()
            ->assertSee('بوابة الطالب')
            ->assertSee('استكشف SPU')
            ->assertSee('تواصل معنا')
            ->assertDontSee('Student Portal');

        $this->get('/en/about')
            ->assertOk()
            ->assertSee('Apply now')
            ->assertSee('Navigation')
            ->assertSee('Syrian Private University');
    }

    public function test_missing_page_slug_returns_not_found(): void
    {
        $this->get('/en/does-not-exist')->assertNotFound();
    }

    public function test_draft_page_is_not_publicly_accessible(): void
    {
        $page = $this->createPage('draft-only', [
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->get('/en/'.$page->slug)->assertNotFound();
    }

    public function test_scheduled_page_before_publish_time_is_not_publicly_accessible(): void
    {
        $page = $this->createPage('scheduled-page', [
            'publish_at' => now()->addDay(),
            'published_at' => now(),
        ]);

        $this->get('/en/'.$page->slug)->assertNotFound();
    }

    public function test_disabled_and_unpublished_pages_are_not_publicly_accessible(): void
    {
        $disabledPage = $this->createPage('disabled-page', [
            'is_enabled' => false,
        ]);

        $unpublishedPage = $this->createPage('unpublished-page', [
            'status' => 'review',
            'published_at' => null,
        ]);

        $this->get('/en/'.$disabledPage->slug)->assertNotFound();
        $this->get('/en/'.$unpublishedPage->slug)->assertNotFound();
    }

    public function test_language_switch_preserves_page_context_when_translation_exists(): void
    {
        $this->get('/en/about')
            ->assertOk()
            ->assertSee('/ar/about', false)
            ->assertSee('/en/about', false);
    }

    public function test_preview_renders_draft_content_without_public_leakage(): void
    {
        $page = $this->createPage('preview-shell');

        PageDraft::query()->create([
            'page_id' => (int) $page->getKey(),
            'payload_json' => [
                'page' => [
                    'metadata' => [
                        'slug' => 'preview-shell',
                        'template' => 'landing-page',
                        'isHomepageShell' => false,
                        'status' => 'draft',
                    ],
                    'arabicTranslation' => [
                        'title' => 'معاينة الصفحة',
                        'headline' => 'معاينة الصفحة',
                        'body' => 'محتوى المعاينة',
                        'bodyPayload' => [
                            'blocks' => [
                                ['type' => 'paragraph', 'content' => 'محتوى المعاينة'],
                            ],
                        ],
                    ],
                    'englishTranslation' => [
                        'title' => 'Preview Shell',
                        'headline' => 'Preview Shell',
                        'body' => 'Draft preview body',
                        'bodyPayload' => [
                            'blocks' => [
                                ['type' => 'paragraph', 'content' => 'Draft preview body'],
                            ],
                        ],
                    ],
                    'arabicSeo' => [
                        'locale' => 'ar',
                        'title' => 'معاينة الصفحة',
                    ],
                    'englishSeo' => [
                        'locale' => 'en',
                        'title' => 'Preview Shell',
                    ],
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Preview-only update',
            'created_by' => $this->author()->id,
            'updated_by' => $this->author()->id,
            'approved_by' => null,
            'scheduled_at' => null,
            'published_at' => null,
        ]);

        $preview = app(PreviewServiceInterface::class)->createToken('page', (int) $page->getKey(), 'en', $this->author()->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Draft preview body')
            ->assertSee('Preview mode');

        $this->get('/en/'.$page->slug)
            ->assertOk()
            ->assertSee('Published page body')
            ->assertDontSee('Draft preview body');
    }

    public function test_preview_language_switch_preserves_tokenized_preview_context(): void
    {
        $page = $this->createPage('preview-language-shell');

        PageDraft::query()->create([
            'page_id' => (int) $page->getKey(),
            'payload_json' => [
                'page' => [
                    'metadata' => [
                        'slug' => 'preview-language-shell',
                        'template' => 'landing-page',
                        'isHomepageShell' => false,
                        'status' => 'draft',
                    ],
                    'arabicTranslation' => [
                        'title' => 'معاينة عربية',
                        'headline' => 'معاينة عربية',
                        'bodyPayload' => [
                            'blocks' => [
                                ['type' => 'paragraph', 'content' => 'محتوى عربي للمعاينة'],
                            ],
                        ],
                    ],
                    'englishTranslation' => [
                        'title' => 'English Preview',
                        'headline' => 'English Preview',
                        'bodyPayload' => [
                            'blocks' => [
                                ['type' => 'paragraph', 'content' => 'English preview content'],
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Locale switch preview test',
            'created_by' => $this->author()->id,
            'updated_by' => $this->author()->id,
        ]);

        $preview = app(PreviewServiceInterface::class)->createToken('page', (int) $page->getKey(), 'en', $this->author()->id);

        $this->get('/ar/preview?token='.$preview->token)
            ->assertOk()
            ->assertSee('محتوى عربي للمعاينة')
            ->assertSee('/en/preview?token='.$preview->token, false);

        $this->get('/en/preview?token='.$preview->token)
            ->assertOk()
            ->assertSee('English preview content')
            ->assertSee('/ar/preview?token='.$preview->token, false)
            ->assertDontSee('Published page body');
    }

    public function test_page_preview_token_stays_bound_to_original_draft_snapshot(): void
    {
        $page = $this->createPage('stable-preview-shell');

        PageDraft::query()->create([
            'page_id' => (int) $page->getKey(),
            'payload_json' => [
                'page' => [
                    'metadata' => [
                        'slug' => 'stable-preview-shell',
                        'template' => 'landing-page',
                        'isHomepageShell' => false,
                        'status' => 'draft',
                    ],
                    'englishTranslation' => [
                        'title' => 'Stable Preview',
                        'headline' => 'Stable Preview',
                        'body' => 'Original preview body',
                        'bodyPayload' => [
                            'blocks' => [
                                ['type' => 'paragraph', 'content' => 'Original preview body'],
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Original draft snapshot',
            'created_by' => $this->author()->id,
            'updated_by' => $this->author()->id,
        ]);

        $preview = app(PreviewServiceInterface::class)->createToken('page', (int) $page->getKey(), 'en', $this->author()->id);

        PageDraft::query()->create([
            'page_id' => (int) $page->getKey(),
            'payload_json' => [
                'page' => [
                    'metadata' => [
                        'slug' => 'stable-preview-shell',
                        'template' => 'landing-page',
                        'isHomepageShell' => false,
                        'status' => 'draft',
                    ],
                    'englishTranslation' => [
                        'title' => 'Stable Preview',
                        'headline' => 'Stable Preview',
                        'body' => 'Newer preview body',
                        'bodyPayload' => [
                            'blocks' => [
                                ['type' => 'paragraph', 'content' => 'Newer preview body'],
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Newer draft snapshot',
            'created_by' => $this->author()->id,
            'updated_by' => $this->author()->id,
        ]);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('Original preview body')
            ->assertDontSee('Newer preview body');
    }

    public function test_homepage_preview_hydrates_draft_section_payloads(): void
    {
        HomepageDraft::query()->create([
            'target_type' => 'homepage',
            'target_id' => null,
            'payload_json' => [
                'homepage' => [
                    'sections' => [
                        [
                            'id' => 99,
                            'key' => 'legacy_unknown',
                            'sortOrder' => 1,
                            'isEnabled' => true,
                            'payload' => [
                                'title' => 'Legacy Unknown Section',
                            ],
                        ],
                        [
                            'id' => 2,
                            'key' => 'academic_faculties',
                            'sortOrder' => 2,
                            'isEnabled' => true,
                            'payload' => [
                                'title' => 'Draft Section',
                                'summary' => 'Secondary draft summary',
                                'items' => [
                                    [
                                        'title' => 'Draft Faculty',
                                        'imageUrl' => '/images/faculty-test.svg',
                                        'metric' => 'Draft Stat',
                                        'action' => [
                                            'label' => 'Draft Feature',
                                            'url' => '/en/faculties',
                                        ],
                                    ],
                                ],
                            ],
                            'arabicTranslation' => [
                                'headline' => 'قسم تجريبي',
                                'body' => 'ملخص القسم',
                            ],
                            'englishTranslation' => [
                                'headline' => 'Draft Section',
                                'body' => 'Secondary draft summary',
                            ],
                        ],
                        [
                            'id' => 1,
                            'key' => 'hero',
                            'sortOrder' => 3,
                            'isEnabled' => true,
                            'payload' => [
                                'eyebrow' => 'Preview Runtime',
                                'title' => 'Preview Homepage Hero',
                                'summary' => 'Draft homepage summary',
                                'primaryAction' => [
                                    'label' => 'Explore Draft',
                                    'url' => '/en/explore-draft',
                                ],
                                'stats' => [
                                    ['value' => '12', 'label' => 'Draft Stat'],
                                ],
                                'featuredItems' => [
                                    ['title' => 'Draft Feature', 'summary' => 'Feature summary'],
                                ],
                            ],
                            'arabicTranslation' => [
                                'headline' => 'واجهة رئيسية تجريبية',
                                'body' => 'ملخص تجريبي',
                            ],
                            'englishTranslation' => [
                                'headline' => 'Preview Homepage Hero',
                                'body' => 'Draft homepage summary',
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'draft',
            'draft_notes' => 'Homepage preview test',
            'created_by' => $this->author()->id,
            'updated_by' => $this->author()->id,
        ]);

        $preview = app(PreviewServiceInterface::class)->createToken('homepage', null, 'en', $this->author()->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertDontSee('Legacy Unknown Section')
            ->assertSee('Preview Homepage Hero')
            ->assertSeeInOrder(['Preview Homepage Hero', 'Draft Section'])
            ->assertSee('Draft homepage summary')
            ->assertSee('Explore Draft')
            ->assertSee('Draft Faculty')
            ->assertSee('Draft Stat')
            ->assertSee('Draft Feature');

        $this->get('/en')
            ->assertOk()
            ->assertDontSee('Preview Homepage Hero')
            ->assertDontSee('Draft homepage summary')
            ->assertDontSee('Draft Stat')
            ->assertDontSee('Draft Feature');
    }

    private function createPage(string $slug, array $overrides = []): Page
    {
        $author = $this->author();

        $page = Page::query()->create(array_merge([
            'parent_id' => null,
            'type' => 'landing_page',
            'template' => 'landing-page',
            'slug' => $slug,
            'status' => 'published',
            'sort_order' => 50,
            'is_enabled' => true,
            'show_in_breadcrumbs' => true,
            'show_in_nav' => false,
            'is_homepage_shell' => false,
            'publish_at' => null,
            'published_at' => now(),
            'created_by' => $author->id,
            'updated_by' => $author->id,
            'approved_by' => $author->id,
            'last_reviewed_at' => now()->toDateString(),
            'layout_key' => 'landing-page',
            'builder_schema_version' => 1,
            'content_json' => ['seeded' => true],
        ], $overrides));

        foreach (['ar' => 'صفحة منشورة', 'en' => 'Published page'] as $locale => $title) {
            PageTranslation::query()->create([
                'page_id' => (int) $page->getKey(),
                'locale' => $locale,
                'title' => $locale === 'ar' ? $title.' '.$slug : $title.' '.$slug,
                'navigation_label' => $locale === 'ar' ? 'منشور' : 'Published',
                'headline' => $locale === 'ar' ? 'صفحة منشورة' : 'Published page',
                'subheadline' => $locale === 'ar' ? 'وصف قصير' : 'Short description',
                'hero_payload' => ['title' => $locale === 'ar' ? 'صفحة منشورة' : 'Published page', 'summary' => $locale === 'ar' ? 'ملخص الصفحة' : 'Page summary'],
                'overview_cards_payload' => null,
                'stats_payload' => null,
                'body_payload' => ['blocks' => [['type' => 'paragraph', 'content' => $locale === 'ar' ? 'نص الصفحة المنشورة' : 'Published page body']]],
                'cta_payload' => ['label' => $locale === 'ar' ? 'اعرف المزيد' : 'Learn more', 'url' => '/'.$locale],
                'sidebar_payload' => null,
                'excerpt' => $locale === 'ar' ? 'ملخص الصفحة' : 'Page summary',
                'body' => $locale === 'ar' ? 'نص الصفحة المنشورة' : 'Published page body',
                'raw_excerpt' => $locale === 'ar' ? 'ملخص الصفحة' : 'Page summary',
                'meta_title_fallback' => $locale === 'ar' ? 'صفحة منشورة' : 'Published page',
            ]);

            PageSeoMeta::query()->create([
                'page_id' => (int) $page->getKey(),
                'locale' => $locale,
                'meta_title' => $locale === 'ar' ? 'صفحة منشورة' : 'Published page',
                'meta_description' => $locale === 'ar' ? 'وصف الصفحة' : 'Page description',
                'og_title' => $locale === 'ar' ? 'صفحة منشورة' : 'Published page',
                'og_description' => $locale === 'ar' ? 'وصف الصفحة' : 'Page description',
                'og_image_media_id' => null,
                'og_image_url' => null,
                'canonical_url' => config('app.url').'/'.$locale.'/'.$slug,
                'robots' => 'index,follow',
                'hreflang_payload' => [
                    ['locale' => 'ar', 'url' => config('app.url').'/ar/'.$slug],
                    ['locale' => 'en', 'url' => config('app.url').'/en/'.$slug],
                ],
            ]);
        }

        return $page;
    }

    private function author(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }
}
