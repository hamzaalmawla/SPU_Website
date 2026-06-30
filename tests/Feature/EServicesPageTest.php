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
        $this->get('/en/e-services')
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertSee('DIGITAL CAMPUS GATEWAY')
            ->assertSee('University E-Services')
            ->assertSee('Digital Services')
            ->assertSee('Student Portal')
            ->assertSee('Appeals & Forms')
            ->assertSee('/en/e-services#portal-access', false)
            ->assertSee('/en/e-services#library', false)
            ->assertSee('/en/e-services#appeals-forms', false)
            ->assertSee('services-card-icon')
            ->assertDontSee('$store.servicesPage', false);

        $this->get('/ar/e-services')
            ->assertOk()
            ->assertSee('بوابة الحرم الجامعي الرقمية')
            ->assertSee('الخدمات الإلكترونية الجامعية')
            ->assertSee('/ar/e-services#portal-access', false);
    }

    public function test_e_services_page_content_is_cms_managed(): void
    {
        $service = app(EServicesPageServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $current = $service->getContent('en');

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
        $service = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->eServicesContentArray($service->getContent('ar'), 'الخدمات المنشورة'),
                'en' => $this->eServicesContentArray($service->getContent('en'), 'Published E-Services Workflow'),
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

        $this->assertDatabaseHas('cms_target_contents', [
            'target_key' => 'e_services',
            'status' => 'published',
        ]);
    }

    public function test_e_services_workflow_preview_renders_draft_snapshot(): void
    {
        $service = app(EServicesPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->eServicesContentArray($service->getContent('ar'), 'معاينة الخدمات'),
                'en' => $this->eServicesContentArray($service->getContent('en'), 'E-Services Preview Workflow'),
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
    private function eServicesContentArray(EServicesPageContentDTO $content, string $title): array
    {
        return [
            'hero' => array_merge($content->hero, ['title' => $title]),
            'digitalServices' => $content->digitalServices,
            'supportCards' => $content->supportCards,
            'seo' => [
                'title' => $content->seoTitle,
                'description' => $content->seoDescription,
                'image' => $content->seoImage,
            ],
        ];
    }
}
