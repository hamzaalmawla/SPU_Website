<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ContactPageServiceInterface;
use App\DTOs\ContactPageContentDTO;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_contact_page_renders_frontend_sections(): void
    {
        $response = $this->get('/en/contact')
            ->assertOk()
            ->assertHeader('X-Cache', 'BYPASS')
            ->assertHeader('Content-Security-Policy')
            ->assertSee('CONTACT US')
            ->assertSee('Send us a Message')
            ->assertSee('Get In Touch')
            ->assertSee('Campus Location')
            ->assertSee('Contact Information')
            ->assertSee('Campus Map')
            ->assertSee('/en/contact', false)
            ->assertSee('/en/contact#campus-map', false)
            ->assertSee('https://www.google.com/maps/embed', false);

        $this->assertStringContainsString('frame-src', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('https://www.google.com', (string) $response->headers->get('Content-Security-Policy'));

        $this->get('/ar/contact')
            ->assertOk()
            ->assertSee('تواصل معنا')
            ->assertSee('أرسل لنا رسالة')
            ->assertSee('موقع الحرم الجامعي')
            ->assertSee('معلومات التواصل')
            ->assertSee('خريطة الحرم الجامعي')
            ->assertSee('/ar/contact', false)
            ->assertSee('/ar/contact#campus-map', false);
    }

    public function test_contact_form_submission_is_stored(): void
    {
        $this->post('/en/contact', [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Admissions question',
            'message' => 'Please send more information about admissions.',
        ])
            ->assertRedirect('/en/contact')
            ->assertSessionHas('contact_status');

        $this->assertDatabaseHas('contact_messages', [
            'locale' => 'en',
            'name' => 'Test Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Admissions question',
            'status' => 'new',
        ]);
    }

    public function test_contact_page_content_is_cms_managed(): void
    {
        $service = app(ContactPageServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $current = $service->getContent('en');

        $this->assertTrue($service->updatePage(
            'en',
            new ContactPageContentDTO(
                hero: ['title' => 'CONTACT THE UNIVERSITY', 'bgImage' => $current->hero['bgImage']],
                info: $current->info,
                socialsTitle: $current->socialsTitle,
                socials: $current->socials,
                form: $current->form,
                location: $current->location,
                seoTitle: 'Contact CMS Title',
                seoDescription: $current->seoDescription,
                seoImage: $current->seoImage,
            ),
            (int) $author->id,
        ));

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('CONTACT THE UNIVERSITY')
            ->assertSee('Contact CMS Title');

        $this->assertDatabaseHas('settings', [
            'group_key' => 'contact_page',
            'key' => 'content',
            'locale' => 'en',
        ]);
    }
}
