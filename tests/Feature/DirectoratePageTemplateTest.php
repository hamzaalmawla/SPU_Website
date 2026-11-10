<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Filament\Resources\DirectorateResource\Pages\CreateDirectorate;
use App\Filament\Resources\DirectorateResource\Pages\EditDirectorate;
use App\Models\Content\Directorate;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every directorate shares one page template: the manager first (typed in, or
 * a linked staff profile), then the blocks the editor built the page from.
 */
final class DirectoratePageTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_manager_linked_to_a_staff_profile_shows_the_profile(): void
    {
        $member = $this->staff('rana-khalil', 'Dr. Rana Khalil', 'rana.khalil@spu.edu.sy');

        $this->publishDirectorate(function (array $payload) use ($member): array {
            $payload['manager_member_id'] = $member->getKey();
            $payload['manager_phone'] = '+963 11 765 4321';
            $payload['translations']['en']['manager_education_json'] = ['PhD in Information Systems'];

            return $payload;
        });

        $this->assertSame((int) $member->getKey(), Directorate::query()->where('slug', 'it-services')->value('manager_member_id'));

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSeeInOrder(['Directorate Manager', 'Dr. Rana Khalil', 'PhD in Information Systems', 'Overview'])
            // The profile's email fills the empty typed one.
            ->assertSee('mailto:rana.khalil@spu.edu.sy', false)
            ->assertSee('tel:+963117654321', false)
            ->assertSee('href="/en/about/profile/rana-khalil"', false)
            ->assertSee('View profile');

        // The central page lists the directorate with its manager.
        $this->get('/en/about/directorates')
            ->assertOk()
            ->assertSee('href="/en/about/directorates/it-services"', false)
            ->assertSee('Dr. Rana Khalil');
    }

    public function test_an_unpublished_linked_profile_shows_no_manager(): void
    {
        $member = $this->staff('hidden-manager', 'Hidden Manager', 'hidden@spu.edu.sy', false);

        $this->publishDirectorate(function (array $payload) use ($member): array {
            $payload['manager_member_id'] = $member->getKey();

            return $payload;
        });

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertDontSee('Hidden Manager')
            ->assertDontSee('Directorate Manager');
    }

    public function test_blocks_render_in_order_with_english_falling_back_to_arabic(): void
    {
        $formKey = app(FormDefinitionServiceInterface::class)->enabledKeys()[0];

        $this->publishDirectorate(function (array $payload) use ($formKey): array {
            $payload['content_blocks_json'] = [
                ['type' => 'heading', 'data' => ['text_ar' => 'خدماتنا', 'text_en' => 'Our services']],
                ['type' => 'text', 'data' => ['body_ar' => 'نص عربي فقط', 'body_en' => '']],
                ['type' => 'image', 'data' => ['image' => '/storage/media/directorates/team.webp', 'alt_ar' => 'الفريق', 'alt_en' => 'The team', 'caption_ar' => '', 'caption_en' => 'Our team at work']],
                ['type' => 'button', 'data' => ['label_ar' => 'الدليل', 'label_en' => 'Read the guide', 'link' => 'url', 'url' => 'https://example.org/guide']],
                ['type' => 'button', 'data' => ['label_ar' => 'قدّم طلبك', 'label_en' => 'Apply now', 'link' => 'form', 'formId' => $formKey]],
                // Empty blocks and unsafe links never reach the page.
                ['type' => 'heading', 'data' => ['text_ar' => '', 'text_en' => '']],
                ['type' => 'image', 'data' => ['image' => 'javascript:alert(1)']],
            ];

            return $payload;
        });

        $stored = Directorate::query()->where('slug', 'it-services')->value('content_blocks_json');
        $this->assertIsArray($stored);
        $this->assertCount(5, $stored);

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSeeInOrder(['Our services', 'نص عربي فقط', 'Our team at work', 'Read the guide', 'Apply now'])
            ->assertSee('src="/storage/media/directorates/team.webp" alt="The team"', false)
            ->assertSee('href="https://example.org/guide" class="dir-btn dir-btn-primary" dir="auto"  target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('data-form-id="'.$formKey.'"', false)
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertDontSee('javascript:alert', false);

        $this->get('/ar/about/directorates/it-services')
            ->assertOk()
            ->assertSeeInOrder(['خدماتنا', 'نص عربي فقط', 'الدليل', 'قدّم طلبك']);

        // A form a page uses cannot be deleted: the form says where it is used.
        $this->assertNotSame([], app(FormDefinitionServiceInterface::class)->usage($formKey, 'en'));
    }

    public function test_a_button_to_a_switched_off_form_is_left_out(): void
    {
        $this->publishDirectorate(function (array $payload): array {
            $payload['content_blocks_json'] = [
                ['type' => 'button', 'data' => ['label_ar' => 'زر', 'label_en' => 'Ghost form', 'link' => 'form', 'formId' => 'activity-registration']],
            ];

            return $payload;
        });

        $form = app(FormDefinitionServiceInterface::class)->find('activity-registration');
        $this->assertNotNull($form);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        app(FormDefinitionServiceInterface::class)->setEnabled($form->id, false, (int) $author->getKey());

        $this->get('/en/about/directorates/it-services')->assertOk()->assertDontSee('Ghost form');
    }

    public function test_publishing_rejects_buttons_without_a_label_or_target(): void
    {
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();
        $targetKey = 'entity.directorate.'.$directorate->getKey();
        $service = app(AboutEntityCmsServiceInterface::class);
        $payload = $service->getStoredData($targetKey)?->payload ?? [];
        $payload['content_blocks_json'] = [
            ['type' => 'button', 'data' => ['label_ar' => '', 'label_en' => '', 'link' => 'url', 'url' => 'https://example.org']],
            ['type' => 'button', 'data' => ['label_ar' => 'زر', 'link' => 'url', 'url' => 'javascript:alert(1)']],
            ['type' => 'button', 'data' => ['label_ar' => 'زر', 'link' => 'form', 'formId' => 'no-such-form']],
        ];
        $payload['manager_member_id'] = 999999;

        $errors = $service->publishErrors($targetKey, $payload);

        $this->assertArrayHasKey('content_blocks_json', $errors);
        $messages = implode(' ', $errors['content_blocks_json']);
        $this->assertStringContainsString('Button 1 needs a label', $messages);
        $this->assertStringContainsString('Button 2 needs a safe web address', $messages);
        $this->assertStringContainsString('no-such-form', $messages);
        $this->assertArrayHasKey('manager_member_id', $errors);
    }

    public function test_the_admin_links_a_staff_manager_and_builds_blocks(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->actingAs($user, 'web');
        $member = $this->staff('omar-saleh', 'Dr. Omar Saleh', 'omar.saleh@spu.edu.sy');
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();

        $this->get('/admin/directorates/'.$directorate->getKey().'/edit')
            ->assertOk()
            ->assertSee('محتوى الصفحة')
            ->assertSee('للمدير ملف في الكادر التدريسي');

        Livewire::test(EditDirectorate::class, ['record' => $directorate->getKey()])
            ->assertFormFieldIsHidden('manager_member_id')
            ->set('data.manager_is_staff', true)
            ->assertFormFieldIsVisible('manager_member_id')
            ->set('data.manager_member_id', $member->getKey())
            ->set('data.content_blocks_json', [
                'a' => ['type' => 'heading', 'data' => ['text_ar' => 'عنوان', 'text_en' => 'Admin heading']],
                'b' => ['type' => 'button', 'data' => ['label_ar' => 'اتصل', 'label_en' => 'Write to us', 'link' => 'url', 'url' => '/en/contact', 'formId' => null]],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('publish');

        $this->get('/en/about/directorates/it-services')
            ->assertOk()
            ->assertSeeInOrder(['Dr. Omar Saleh', 'Admin heading', 'Write to us'])
            ->assertSee('href="/en/contact"', false);

        // Turning the link off brings back the typed manager fields and drops the profile.
        Livewire::test(EditDirectorate::class, ['record' => $directorate->getKey()])
            ->assertSet('data.manager_is_staff', true)
            ->set('data.manager_is_staff', false)
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('publish');

        $this->assertNull(Directorate::query()->whereKey($directorate->getKey())->value('manager_member_id'));
        $this->get('/en/about/directorates/it-services')->assertOk()->assertDontSee('Dr. Omar Saleh');
    }

    public function test_a_new_directorate_is_published_as_soon_as_it_is_saved(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->actingAs($user, 'web');

        Livewire::test(CreateDirectorate::class)
            ->fillForm([
                'slug' => 'legal-affairs',
                'is_enabled' => true,
                'translations' => [
                    ['locale' => 'ar', 'title' => 'مديرية الشؤون القانونية', 'description' => 'تتابع الشؤون القانونية للجامعة.'],
                    ['locale' => 'en', 'title' => 'Legal Affairs Directorate', 'description' => 'Handles the university legal affairs.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin_pages.about_entity.entity_published'));

        $directorate = Directorate::query()->where('slug', 'legal-affairs')->firstOrFail();
        $this->assertSame('published', $directorate->publication_status);
        $this->assertNull(app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(
            'entity.directorate.'.$directorate->getKey(),
            (int) $user->getKey(),
        ));

        $this->get('/en/about/directorates')->assertOk()->assertSee('Legal Affairs Directorate');
        $this->get('/en/about/directorates/legal-affairs')->assertOk()->assertSee('Legal Affairs Directorate');
        $this->get('/ar/about/directorates/legal-affairs')->assertOk()->assertSee('مديرية الشؤون القانونية');
    }

    public function test_a_new_directorate_that_fails_the_publish_checks_stays_a_draft_with_the_reason(): void
    {
        $user = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $this->actingAs($user, 'web');

        Livewire::test(CreateDirectorate::class)
            ->fillForm([
                'slug' => 'title-only',
                'is_enabled' => true,
                'translations' => [
                    ['locale' => 'ar', 'title' => 'مديرية بلا محتوى'],
                    ['locale' => 'en', 'title' => 'Directorate Without Content'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin_pages.about_entity.publish_failed'));

        $directorate = Directorate::query()->where('slug', 'title-only')->firstOrFail();
        $this->assertSame('draft', $directorate->publication_status);
        $draft = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(
            'entity.directorate.'.$directorate->getKey(),
            (int) $user->getKey(),
        );
        $this->assertSame('Directorate Without Content', $draft['translations']['en']['title'] ?? null);
        $this->get('/en/about/directorates/title-only')->assertNotFound();
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $edit */
    private function publishDirectorate(callable $edit): void
    {
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $directorate = Directorate::query()->where('slug', 'it-services')->firstOrFail();
        $targetKey = 'entity.directorate.'.$directorate->getKey();
        $workflow = app(CmsWorkflowServiceInterface::class);

        $payload = app(AboutEntityCmsServiceInterface::class)->getStoredData($targetKey)?->payload;
        $this->assertIsArray($payload);

        $workflow->saveDraft($targetKey, $edit($payload), (int) $author->getKey());
        $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));
    }

    private function staff(string $slug, string $name, string $email, bool $published = true): FacultyMember
    {
        $member = FacultyMember::query()->create([
            'slug' => $slug,
            'email' => $email,
            'is_enabled' => true,
            'publication_status' => $published ? 'published' : 'draft',
            'published_at' => $published ? now()->subDay() : null,
        ]);
        foreach (['ar', 'en'] as $locale) {
            $member->translations()->create(['locale' => $locale, 'full_name' => $name]);
        }

        return $member;
    }
}
