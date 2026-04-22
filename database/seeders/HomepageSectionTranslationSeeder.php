<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\HomepageSectionServiceInterface;
use App\Models\HomepageSection;
use App\Models\HomepageSectionTranslation;
use Illuminate\Database\Seeder;

/**
 * Seeds editable starter copy for local development and content previews.
 */
class HomepageSectionTranslationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $key) {
            $section = HomepageSection::query()->where('key', $key)->firstOrFail();

            foreach (['ar', 'en'] as $locale) {
                HomepageSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => (int) $section->getKey(),
                        'locale' => $locale,
                    ],
                    [
                        'payload_json' => $this->payloadFor($key, $locale),
                    ]
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $key, string $locale): array
    {
        $content = [
            'ar' => [
                'hero' => ['eyebrow' => 'الجامعة الخاصة السورية', 'title' => 'منصة الجامعة الرئيسية', 'summary' => 'واجهة قابلة للإدارة للهوية والمحتوى الأساسي.', 'body' => 'هذه بيانات تمهيدية قابلة للاستبدال من لوحة التحكم.', 'primaryAction' => ['label' => 'استكشف', 'url' => '/ar'], 'secondaryAction' => ['label' => 'طلب قبول', 'url' => '/ar/admissions']],
                'hero_stats' => ['title' => 'أرقام سريعة', 'body' => 'إحصاءات أولية قابلة للتحديث من الـ CMS.'],
                'academic_faculties' => ['title' => 'الكليات الأكاديمية', 'body' => 'قوالب أولية لعرض الكليات والبرامج.'],
                'achievements_highlights' => ['title' => 'إنجازات مختارة', 'body' => 'قسم مرن لإبراز الاعتمادات والنجاحات.'],
                'university_news' => ['title' => 'أخبار الجامعة', 'body' => 'منطقة أخبار جاهزة للربط بموديول الأخبار لاحقاً.'],
                'research_studies' => ['title' => 'البحوث والدراسات', 'body' => 'واجهة تمهيدية لمحتوى البحث العلمي.'],
                'events_activities' => ['title' => 'الفعاليات والأنشطة', 'body' => 'قالب مرن للفعاليات العامة والطلابية.'],
                'medical_facilities_services' => ['title' => 'المرافق والخدمات الطبية', 'body' => 'قسم تمهيدي قابل للإدارة للمرافق الطبية.'],
                'bottom_stats' => ['title' => 'أرقام إضافية', 'body' => 'مؤشرات سفلية قابلة للتخصيص.'],
                'footer' => ['title' => 'تذييل الموقع', 'body' => 'محتوى التذييل وروابط التواصل الأساسية.'],
            ],
            'en' => [
                'hero' => ['eyebrow' => 'Syrian Private University', 'title' => 'Primary university shell', 'summary' => 'A managed homepage foundation for core institutional content.', 'body' => 'This starter content is intended to be replaced from the CMS.', 'primaryAction' => ['label' => 'Explore', 'url' => '/en'], 'secondaryAction' => ['label' => 'Apply', 'url' => '/en/admissions']],
                'hero_stats' => ['title' => 'Quick stats', 'body' => 'Starter metrics managed through the CMS foundation.'],
                'academic_faculties' => ['title' => 'Academic faculties', 'body' => 'Initial faculty presentation scaffolding.'],
                'achievements_highlights' => ['title' => 'Achievements', 'body' => 'Flexible highlights area for accreditations and milestones.'],
                'university_news' => ['title' => 'University news', 'body' => 'A news-ready area for the future module.'],
                'research_studies' => ['title' => 'Research and studies', 'body' => 'Starter shell for research content.'],
                'events_activities' => ['title' => 'Events and activities', 'body' => 'Flexible event scaffolding for public updates.'],
                'medical_facilities_services' => ['title' => 'Medical facilities and services', 'body' => 'Managed introduction area for medical services.'],
                'bottom_stats' => ['title' => 'Additional figures', 'body' => 'Lower-page metrics for future refinement.'],
                'footer' => ['title' => 'Site footer', 'body' => 'Footer content and core contact links.'],
            ],
        ];

        $payload = $content[$locale][$key];

        return [
            'headline' => $payload['title'],
            'body' => $payload['body'],
            'eyebrow' => $payload['eyebrow'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'ctaLabel' => $payload['primaryAction']['label'] ?? null,
            'imageAlt' => $payload['title'],
            'primaryAction' => $payload['primaryAction'] ?? null,
            'secondaryAction' => $payload['secondaryAction'] ?? null,
        ];
    }
}
