<?php

declare(strict_types=1);

namespace Database\Seeders\LegacyImport;

use App\Models\Page;
use App\Models\PageSeoMeta;
use App\Models\PageTranslation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ImportLegacyStaticPagesSeeder extends BaseLegacyImportSeeder
{
    public function run(): void
    {
        $module = 'static_pages';
        $batch = $this->batchName($module);

        foreach ($this->legacyRows('jx_site_static_pages') as $row) {
            $sourceId = $this->normalizedInteger($this->rowValue($row, 'id'));

            if ($this->alreadyImported('jx_site_static_pages', $sourceId, 'pages')) {
                continue;
            }

            $translations = $this->buildTranslations($row);
            $classification = $this->classifyPage($translations);
            $slug = $this->normalizedSlug($classification, $translations, $sourceId);

            if ($translations === []) {
                $this->reject($module, 'jx_site_static_pages', $sourceId, 'unsupported_locale', 'No supported AR/EN translation payload could be derived from legacy page.', ['slug' => $slug]);
                $this->logSkip($module, $batch, 'jx_site_static_pages', $sourceId, 'pages', 'Skipped page row without usable AR/EN translation payload.', ['slug' => $slug]);

                continue;
            }

            $page = Page::withTrashed()->firstOrNew(['slug' => $slug]);

            $page->fill([
                'parent_id' => null,
                'type' => 'landing_page',
                'template' => 'landing-page',
                'status' => $this->normalizedBoolean($this->rowValue($row, ['is_published', 'published', 'is_active']), true) ? 'published' : 'draft',
                'sort_order' => $this->normalizedInteger($this->rowValue($row, ['sort_order', 'order'])) ?? 0,
                'is_enabled' => ! $this->normalizedBoolean($this->rowValue($row, ['is_disabled', 'disabled']), false),
                'show_in_breadcrumbs' => true,
                'show_in_nav' => $this->normalizedBoolean($this->rowValue($row, ['show_in_nav', 'show_in_menu']), false),
                'is_homepage_shell' => false,
                'publish_at' => null,
                'published_at' => $this->dateNormalizer()->normalize($this->rowValue($row, ['published_at', 'publish_date']))?->toDateTimeString(),
                'created_by' => null,
                'updated_by' => null,
                'approved_by' => null,
                'deleted_at' => null,
                'last_reviewed_at' => null,
                'layout_key' => 'landing-page',
                'builder_schema_version' => 1,
                'content_json' => [
                    'legacy_source' => 'jx_site_static_pages',
                    'legacy_id' => $sourceId,
                    'legacy_classification' => $classification,
                ],
            ]);
            $page->save();

            foreach ($translations as $locale => $translation) {
                PageTranslation::query()->updateOrCreate(
                    ['page_id' => (int) $page->getKey(), 'locale' => $locale],
                    [
                        'title' => $translation['title'],
                        'navigation_label' => $translation['navigation_label'],
                        'headline' => $translation['title'],
                        'subheadline' => null,
                        'hero_payload' => null,
                        'overview_cards_payload' => null,
                        'stats_payload' => null,
                        'body_payload' => ['blocks' => [['type' => 'legacy_html', 'content' => $translation['body']]]],
                        'cta_payload' => null,
                        'sidebar_payload' => null,
                        'excerpt' => $translation['excerpt'],
                        'body' => $translation['body'],
                        'raw_excerpt' => $translation['excerpt'],
                        'meta_title_fallback' => $translation['title'],
                    ],
                );

                PageSeoMeta::query()->updateOrCreate(
                    ['page_id' => (int) $page->getKey(), 'locale' => $locale],
                    [
                        'meta_title' => $translation['meta_title'],
                        'meta_description' => $translation['meta_description'],
                        'og_title' => $translation['meta_title'],
                        'og_description' => $translation['meta_description'],
                        'og_image_media_id' => null,
                        'og_image_url' => null,
                        'canonical_url' => null,
                        'robots' => 'index,follow',
                        'hreflang_payload' => [
                            ['locale' => 'ar', 'slug' => $slug],
                            ['locale' => 'en', 'slug' => $slug],
                        ],
                    ],
                );
            }

            $this->migrationLogger()->log($module, $batch, 'jx_site_static_pages', $sourceId, 'pages', (int) $page->getKey(), 'success', 'Imported legacy static page into page shell tables.', ['slug' => $slug]);
        }
    }

    /**
     * @param  array<string, array{title: string, navigation_label: string, excerpt: ?string, body: ?string, meta_title: ?string, meta_description: ?string}>  $translations
     */
    private function classifyPage(array $translations): string
    {
        $titles = implode(' ', array_map(static fn (array $translation): string => html_entity_decode($translation['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $translations));
        $bodies = implode(' ', array_map(static fn (array $translation): string => html_entity_decode(strip_tags((string) $translation['body']), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $translations));
        $haystack = Str::lower(trim($titles.' '.$bodies));

        if ($haystack === '' || preg_replace('/\s+/u', '', $haystack) === 'spu' || Str::length($haystack) < 40) {
            return 'placeholder_page';
        }

        return match (true) {
            str_contains($haystack, 'خدمة المجتمع') || str_contains($haystack, 'community service') => 'community_service',
            str_contains($haystack, 'الابحاث العلمية') || str_contains($haystack, 'scientific research') => 'scientific_research',
            str_contains($haystack, 'مستشفى الجامعة') || str_contains($haystack, 'spu hospital') => 'hospital_overview',
            str_contains($haystack, 'العيادات السنية') || str_contains($haystack, 'dental clinics') => 'dental_clinics',
            str_contains($haystack, 'بوابة الخريجين') || str_contains($haystack, 'alumni portal') => 'alumni_portal',
            str_contains($haystack, 'النوادي والأنشطة الطلابية') || str_contains($haystack, 'clubs and activities') => 'student_activities',
            str_contains($haystack, 'الاتفاقيات') || str_contains($haystack, 'vision about the agreement') => 'university_agreements',
            str_contains($haystack, 'spuhospital@hotmail.com') || str_contains($haystack, 'fax : 6990222') => 'hospital_contact',
            str_contains($haystack, 'عميد الكلية') || str_contains($haystack, 'dean of the faculty') || str_contains($haystack, 'temporary campus') || str_contains($haystack, 'permanent headquarters') => 'faculty_contact',
            str_contains($haystack, 'مكتب رئاسة الجامعة') || str_contains($haystack, 'university presidency office') || str_contains($haystack, 'اتصل بنا') || str_contains($haystack, 'contact us') => 'university_contact',
            default => 'legacy_static_page',
        };
    }

    /**
     * @param  array<string, array{title: string, navigation_label: string, excerpt: ?string, body: ?string, meta_title: ?string, meta_description: ?string}>  $translations
     */
    private function normalizedSlug(string $classification, array $translations, ?int $sourceId): string
    {
        $englishTitle = Arr::get($translations, 'en.title');
        $suffix = '-'.($sourceId ?? Str::random(6));

        $baseSlug = match ($classification) {
            'university_contact' => 'legacy-university-contact'.$suffix,
            'faculty_contact' => 'legacy-faculty-contact'.$suffix,
            'hospital_contact' => 'legacy-hospital-contact'.$suffix,
            'community_service' => 'legacy-community-service'.$suffix,
            'scientific_research' => 'legacy-scientific-research'.$suffix,
            'hospital_overview' => 'legacy-spu-hospital'.$suffix,
            'dental_clinics' => 'legacy-dental-clinics'.$suffix,
            'alumni_portal' => 'legacy-alumni-portal'.$suffix,
            'student_activities' => 'legacy-student-clubs-and-activities'.$suffix,
            'university_agreements' => 'legacy-university-agreements'.$suffix,
            'placeholder_page' => 'legacy-placeholder-page'.$suffix,
            default => 'legacy-'.Str::slug((string) ($englishTitle ?? 'static-page')).$suffix,
        };

        return $baseSlug;
    }

    /**
     * @return array<string, array{title: string, navigation_label: string, excerpt: ?string, body: ?string, meta_title: ?string, meta_description: ?string}>
     */
    private function buildTranslations(object $row): array
    {
        $result = [];

        foreach (['ar', 'en'] as $candidate) {
            $title = $this->cleanedString($row, [$candidate.'_comment', 'title_'.$candidate, 'name_'.$candidate, $candidate.'_brief']);
            $body = $this->htmlSanitizer()->sanitize($this->cleanedString($row, [$candidate.'_page_data', 'body_'.$candidate, 'content_'.$candidate, 'details_'.$candidate]));
            $excerpt = $this->cleanedString($row, [$candidate.'_brief', 'excerpt_'.$candidate, 'summary_'.$candidate]);

            if ($title === null && $body === null && $excerpt === null) {
                continue;
            }

            $title ??= strtoupper($candidate).' Legacy Page '.($this->normalizedInteger($this->rowValue($row, 'id')) ?? '');

            $result[$candidate] = [
                'title' => $title,
                'navigation_label' => $title,
                'excerpt' => $excerpt,
                'body' => $body,
                'meta_title' => $this->cleanedString($row, ['meta_title_'.$candidate, 'seo_title_'.$candidate]) ?? $title,
                'meta_description' => $this->cleanedString($row, ['meta_description_'.$candidate, 'seo_description_'.$candidate]) ?? $excerpt,
            ];
        }

        return $result;
    }
}
