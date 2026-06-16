<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
