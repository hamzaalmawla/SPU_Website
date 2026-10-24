<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\DTOs\EServices\EServicesPageContentDTO;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EServicesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_e_services_page_renders_frontend_sections_and_dropdown_links(): void
    {
        $this->publishLanding();

        $this->get('/en/e-services')
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertSee('DIGITAL CAMPUS GATEWAY')
            ->assertSee('University E-Services')
            ->assertSee('Digital Services')
            ->assertSee('Student Access Help')
            ->assertSee('Service Guidance')
            ->assertSee('/en/e-services#portal-access', false)
            ->assertSee('/en/e-services/library', false)
            ->assertSee('/en/e-services/staff-email', false)
            ->assertSee('/en/e-services/it-support', false)
            ->assertSee('/en/e-services#appeals-forms', false)
            ->assertSee('services-card-icon')
            ->assertDontSee('students.spu.edu.sy')
            ->assertDontSee('my.spu.edu.sy')
            ->assertDontSee('$store.servicesPage', false);

        $this->get('/ar/e-services')
            ->assertOk()
            ->assertSee('بوابة الحرم الجامعي الرقمية')
            ->assertSee('الخدمات الإلكترونية الجامعية')
            ->assertSee('/ar/e-services#portal-access', false)
            ->assertSee('/ar/e-services/library', false)
            ->assertSee('/ar/e-services/staff-email', false)
            ->assertSee('/ar/e-services/it-support', false);
    }

    public function test_e_services_page_content_is_cms_managed(): void
    {
        $service = app(EServicesPageServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $current = $this->contentDto('en');

        $this->assertTrue($service->updatePage(
            'en',
            new EServicesPageContentDTO(
                hero: array_merge($current->hero, ['title' => 'Managed E-Services']),
                digitalServices: $current->digitalServices,
                supportCards: $current->supportCards,
                seoTitle: 'Managed E-Services SEO',
                seoDescription: $current->seoDescription,
                seoImage: $current->seoImage,
            ),
            (int) $author->id,
        ));

        $this->get('/en/e-services')
            ->assertOk()
            ->assertSee('Managed E-Services')
            ->assertSee('Managed E-Services SEO');

        $this->assertDatabaseHas('settings', [
            'group_key' => 'e_services_page',
            'key' => 'content',
            'locale' => 'en',
        ]);
    }

    public function test_e_services_workflow_draft_does_not_leak_until_published(): void
    {
        $this->publishLanding();

        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->eServicesContentArray('ar', 'الخدمات المنشورة'),
                'en' => $this->eServicesContentArray('en', 'Published E-Services Workflow'),
            ],
        ];

        $workflow->saveDraft('e_services', $payload, (int) $author->id);

        $this->get('/en/e-services')
            ->assertOk()
            ->assertDontSee('Published E-Services Workflow');

        $this->assertTrue($workflow->publish('e_services', (int) $author->id));

        $this->get('/en/e-services')
            ->assertOk()
            ->assertSee('Published E-Services Workflow');

        $this->assertTrue($workflow->unpublish('e_services', (int) $author->id));
        $this->get('/en/e-services')->assertNotFound();

        $this->assertDatabaseHas('cms_target_contents', [
            'target_key' => 'e_services',
            'status' => 'draft',
        ]);
    }

    public function test_e_services_workflow_preview_renders_draft_snapshot(): void
    {
        $this->publishLanding();

        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->eServicesContentArray('ar', 'معاينة الخدمات'),
                'en' => $this->eServicesContentArray('en', 'E-Services Preview Workflow'),
            ],
        ];

        $workflow->saveDraft('e_services', $payload, (int) $author->id);
        $preview = $workflow->preview('e_services', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('E-Services Preview Workflow')
            ->assertSee('Preview mode');

        $this->get('/en/e-services')
            ->assertOk()
            ->assertDontSee('E-Services Preview Workflow');
    }

    /** @return array<string, mixed> */
    private function eServicesContentArray(string $locale, ?string $title = null): array
    {
        $isArabic = $locale === 'ar';

        return [
            'hero' => [
                'eyebrow' => $isArabic ? 'بوابة الحرم الجامعي الرقمية' : 'DIGITAL CAMPUS GATEWAY',
                'title' => $title ?? ($isArabic ? 'الخدمات الإلكترونية الجامعية' : 'University E-Services'),
                'summary' => $isArabic ? 'وصول رسمي وآمن إلى خدمات الجامعة الرقمية.' : 'Official, safe access to university digital services.',
                'imageHero' => '/images/slider-3.webp',
                'imageLeft' => '/images/slider-3.webp',
                'imageRight' => '/images/slider-3.webp',
            ],
            'digitalServices' => [
                'title' => $isArabic ? 'الخدمات الرقمية' : 'Digital Services',
                'services' => [
                    $this->serviceItem('portal-access', $isArabic ? 'دخول الطالب' : 'Student Portal Access', "/{$locale}/e-services#portal-access"),
                    $this->serviceItem('library', $isArabic ? 'المكتبة' : 'Library', "/{$locale}/e-services/library"),
                    $this->serviceItem('staff-email', $isArabic ? 'بريد الموظفين' : 'Staff Email', "/{$locale}/e-services/staff-email"),
                    $this->serviceItem('it-support', $isArabic ? 'الدعم التقني' : 'IT Support', "/{$locale}/e-services/it-support"),
                    $this->serviceItem('appeals-forms', $isArabic ? 'الاعتراضات والنماذج' : 'Appeals & Forms', "/{$locale}/e-services#appeals-forms"),
                ],
            ],
            'supportCards' => [
                ['id' => 'access-help', 'eyebrow' => '01', 'title' => $isArabic ? 'مساعدة دخول الطالب' : 'Student Access Help', 'summary' => $isArabic ? 'إرشادات الدخول الآمن.' : 'Safe access guidance.'],
                ['id' => 'guidance', 'eyebrow' => '02', 'title' => $isArabic ? 'إرشادات الخدمة' : 'Service Guidance', 'summary' => $isArabic ? 'استخدم الروابط الرسمية.' : 'Use official links.'],
            ],
            'seo' => [
                'title' => $isArabic ? 'الخدمات الإلكترونية | SPU' : 'E-Services | SPU',
                'description' => $isArabic ? 'خدمات الجامعة الإلكترونية الرسمية.' : 'Official university electronic services.',
                'image' => '/images/slider-3.webp',
            ],
        ];
    }

    /** @return array<string, string> */
    private function serviceItem(string $id, string $title, string $url): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'summary' => 'Official service guidance.',
            'icon' => '/images/icon-file-outline.svg',
            'url' => $url,
            'button' => 'Open service',
        ];
    }

    private function contentDto(string $locale): EServicesPageContentDTO
    {
        $content = $this->eServicesContentArray($locale);

        return new EServicesPageContentDTO(
            hero: $content['hero'],
            digitalServices: $content['digitalServices'],
            supportCards: $content['supportCards'],
            seoTitle: $content['seo']['title'],
            seoDescription: $content['seo']['description'],
            seoImage: $content['seo']['image'],
        );
    }

    private function publishLanding(): void
    {
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $workflow->saveDraft('e_services', [
            'translations' => [
                'ar' => $this->eServicesContentArray('ar'),
                'en' => $this->eServicesContentArray('en'),
            ],
        ], (int) $author->id);

        $this->assertTrue($workflow->publish('e_services', (int) $author->id));
    }
}
