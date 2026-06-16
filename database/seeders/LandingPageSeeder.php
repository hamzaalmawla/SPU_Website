<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page\Page;
use App\Models\Page\PageSeoMeta;
use App\Models\Page\PageTranslation;
use App\Models\User\User;
use Illuminate\Database\Seeder;

/**
 * Seeds placeholder landing pages for local development and demo content only.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        foreach ($this->pages() as $index => $definition) {
            $page = Page::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'parent_id' => null,
                    'type' => $definition['type'],
                    'template' => $definition['layout_key'],
                    'status' => 'published',
                    'sort_order' => $index + 1,
                    'is_enabled' => true,
                    'show_in_breadcrumbs' => true,
                    'show_in_nav' => $definition['show_in_nav'],
                    'is_homepage_shell' => $definition['type'] === 'homepage',
                    'publish_at' => null,
                    'published_at' => now(),
                    'created_by' => (int) $admin->getKey(),
                    'updated_by' => (int) $admin->getKey(),
                    'approved_by' => (int) $admin->getKey(),
                    'last_reviewed_at' => now()->toDateString(),
                    'layout_key' => $definition['layout_key'],
                    'builder_schema_version' => 1,
                    // content_json stays shell-level; localized runtime copy is seeded below.
                    'content_json' => ['seeded' => true, 'slug' => $definition['slug']],
                ]
            );

            foreach ($definition['translations'] as $locale => $translation) {
                PageTranslation::query()->updateOrCreate(
                    [
                        'page_id' => (int) $page->getKey(),
                        'locale' => $locale,
                    ],
                    [
                        'title' => $translation['title'],
                        'navigation_label' => $translation['navigation_label'],
                        'headline' => $translation['headline'],
                        'subheadline' => $translation['subheadline'],
                        'hero_payload' => $translation['hero_payload'],
                        'overview_cards_payload' => null,
                        'stats_payload' => null,
                        'body_payload' => $translation['body_payload'],
                        'cta_payload' => $translation['cta_payload'],
                        'sidebar_payload' => null,
                        'excerpt' => $translation['excerpt'],
                        'body' => $translation['body'],
                        'raw_excerpt' => $translation['excerpt'],
                        'meta_title_fallback' => $translation['title'],
                    ]
                );

                PageSeoMeta::query()->updateOrCreate(
                    [
                        'page_id' => (int) $page->getKey(),
                        'locale' => $locale,
                    ],
                    [
                        'meta_title' => $translation['title'],
                        'meta_description' => $translation['excerpt'],
                        'og_title' => $translation['title'],
                        'og_description' => $translation['excerpt'],
                        'og_image_media_id' => null,
                        'og_image_url' => null,
                        'canonical_url' => $baseUrl.'/'.$locale.'/'.$definition['slug'],
                        'robots' => 'index,follow',
                        'hreflang_payload' => [
                            ['locale' => 'ar', 'url' => $baseUrl.'/ar/'.$definition['slug']],
                            ['locale' => 'en', 'url' => $baseUrl.'/en/'.$definition['slug']],
                        ],
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array{slug: string, type: string, layout_key: string, show_in_nav: bool, translations: array<string, array<string, mixed>>}>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'home',
                'type' => 'homepage',
                'layout_key' => 'homepage',
                'show_in_nav' => false,
                'translations' => $this->translations('الرئيسية', 'Home', 'واجهة رئيسية للجامعة', 'University homepage shell'),
            ],
            [
                'slug' => 'about',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('عن الجامعة', 'About', 'مقدمة تعريفية قابلة للإدارة', 'Managed institutional overview'),
            ],
            [
                'slug' => 'faculties',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('الكليات', 'Faculties', 'صفحة تمهيدية للكليات', 'Faculty landing shell'),
            ],
            [
                'slug' => 'research',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('البحث العلمي', 'Research', 'مدخل أولي لمحتوى البحث', 'Research landing shell'),
            ],
            [
                'slug' => 'news',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('الأخبار', 'News', 'منطقة أخبار أولية', 'News landing shell'),
            ],
            [
                'slug' => 'events',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('الفعاليات', 'Events', 'منطقة فعاليات تمهيدية', 'Events landing shell'),
            ],
            [
                'slug' => 'admissions',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('القبول والتسجيل', 'Admissions', 'قالب أولي لمسار القبول', 'Admissions landing shell'),
            ],
            [
                'slug' => 'contact',
                'type' => 'landing_page',
                'layout_key' => 'landing-page',
                'show_in_nav' => true,
                'translations' => $this->translations('اتصل بنا', 'Contact', 'صفحة تواصل أولية', 'Contact landing shell'),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function translations(string $arabicTitle, string $englishTitle, string $arabicExcerpt, string $englishExcerpt): array
    {
        return [
            'ar' => [
                'title' => $arabicTitle,
                'navigation_label' => $arabicTitle,
                'headline' => $arabicTitle,
                'subheadline' => 'محتوى تمهيدي قابل للتوسعة لاحقاً.',
                'excerpt' => $arabicExcerpt,
                'body' => $arabicExcerpt,
                'hero_payload' => ['title' => $arabicTitle, 'summary' => $arabicExcerpt],
                'body_payload' => ['blocks' => [['type' => 'paragraph', 'content' => $arabicExcerpt]]],
                'cta_payload' => ['label' => 'اكتشف المزيد', 'url' => '/ar/home'],
            ],
            'en' => [
                'title' => $englishTitle,
                'navigation_label' => $englishTitle,
                'headline' => $englishTitle,
                'subheadline' => 'Starter content ready for later expansion.',
                'excerpt' => $englishExcerpt,
                'body' => $englishExcerpt,
                'hero_payload' => ['title' => $englishTitle, 'summary' => $englishExcerpt],
                'body_payload' => ['blocks' => [['type' => 'paragraph', 'content' => $englishExcerpt]]],
                'cta_payload' => ['label' => 'Discover more', 'url' => '/en/home'],
            ],
        ];
    }
}
