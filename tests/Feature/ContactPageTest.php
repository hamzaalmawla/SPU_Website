<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
use App\DTOs\Contact\ContactPageContentDTO;
use App\Models\User\User;
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

    public function test_contact_workflow_draft_does_not_leak_until_published(): void
    {
        $service = app(ContactPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->contactContentArray($service->getContent('ar'), 'تواصل منشور'),
                'en' => $this->contactContentArray($service->getContent('en'), 'PUBLISHED CONTACT WORKFLOW'),
            ],
        ];

        $workflow->saveDraft('contact', $payload, (int) $author->id);

        $this->get('/en/contact')
            ->assertOk()
            ->assertDontSee('PUBLISHED CONTACT WORKFLOW');

        $this->assertTrue($workflow->publish('contact', (int) $author->id));

        $this->get('/en/contact')
            ->assertOk()
            ->assertSee('PUBLISHED CONTACT WORKFLOW');

        $this->assertDatabaseHas('cms_target_contents', [
            'target_key' => 'contact',
            'status' => 'published',
        ]);
    }

    public function test_contact_workflow_preview_renders_draft_snapshot(): void
    {
        $service = app(ContactPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $payload = [
            'translations' => [
                'ar' => $this->contactContentArray($service->getContent('ar'), 'معاينة التواصل'),
                'en' => $this->contactContentArray($service->getContent('en'), 'CONTACT PREVIEW WORKFLOW'),
            ],
        ];

        $workflow->saveDraft('contact', $payload, (int) $author->id);
        $preview = $workflow->preview('contact', 'en', (int) $author->id);

        $this->get($preview->previewUrl)
            ->assertOk()
            ->assertSee('CONTACT PREVIEW WORKFLOW')
            ->assertSee('Preview mode');

        $this->get('/en/contact')
            ->assertOk()
            ->assertDontSee('CONTACT PREVIEW WORKFLOW');
    }

    /** @return array<string, mixed> */
    private function contactContentArray(ContactPageContentDTO $content, string $title): array
    {
        return [
            'hero' => array_merge($content->hero, ['title' => $title]),
            'info' => $content->info,
            'socialsTitle' => $content->socialsTitle,
            'socials' => $content->socials,
            'form' => $content->form,
            'location' => $content->location,
            'seo' => [
                'title' => $content->seoTitle,
                'description' => $content->seoDescription,
                'image' => $content->seoImage,
            ],
        ];
    }
}
