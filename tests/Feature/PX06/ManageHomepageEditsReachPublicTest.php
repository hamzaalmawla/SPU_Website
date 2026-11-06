<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageHomepage;
use App\Models\Homepage\HomepageDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Edits made on the homepage editor must reach what the public page reads.
 * The editor also loads saved fields under a second name (imageUrl beside
 * image, summary beside description, a contact field beside the contact
 * list), and the loaded copy used to win on save.
 */
class ManageHomepageEditsReachPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail());
    }

    public function test_a_new_faculty_card_logo_is_published_to_the_homepage(): void
    {
        $page = $this->editor('academic_faculties');
        $card = $this->firstCardPath($page, 'academic_faculties', 'featured_items');

        $page->set("{$card}.image", '/images/new-medicine-logo.png')
            ->callAction('publish')
            ->assertHasNoErrors();

        $this->get('/en')
            ->assertOk()
            ->assertSee('/images/new-medicine-logo.png', false)
            ->assertDontSee('/images/faculty-medicine-logo.png', false);
    }

    public function test_medical_card_text_loads_into_the_form_and_edits_are_saved(): void
    {
        $page = $this->editor('medical_facilities_services');
        $card = $this->firstCardPath($page, 'medical_facilities_services', 'items');

        $this->assertSame(
            'SPU provides students with access to practical medical and dental exposure on campus',
            $page->get("{$card}.description"),
        );

        $page->set("{$card}.description", 'New healthcare text')
            ->set("{$card}.image", '/images/new-healthcare.webp')
            ->call('save')
            ->assertHasNoErrors();

        $saved = $this->savedItems('medical_facilities_services')[0];
        $this->assertSame('New healthcare text', $saved['summary']);
        $this->assertSame('/images/new-healthcare.webp', $saved['imageUrl']);
        $this->assertSame(['University Hospital', 'Dental Clinic', 'Clinical Learning Support'], $saved['features']);
    }

    public function test_clearing_a_medical_card_button_removes_it(): void
    {
        $page = $this->editor('medical_facilities_services');
        $card = $this->firstCardPath($page, 'medical_facilities_services', 'items');

        $page->set("{$card}.cta_label", null)
            ->set("{$card}.cta_url", null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertArrayNotHasKey('action', $this->savedItems('medical_facilities_services')[0]);
    }

    public function test_the_event_button_label_is_saved_and_cleared(): void
    {
        $this->editor('events_activities')
            ->set('data.events_activities.en.content.event_cta_label', 'See the event')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('See the event', $this->savedContent('events_activities')['eventCtaLabel'] ?? null);

        $this->editor('events_activities')
            ->set('data.events_activities.en.content.event_cta_label', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertArrayNotHasKey('eventCtaLabel', $this->savedContent('events_activities'));
    }

    public function test_the_footer_logo_can_be_changed_and_removed(): void
    {
        $this->editor('footer')
            ->set('data.footer.en.logo', '/images/new-footer-logo.png')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('/images/new-footer-logo.png', $this->savedContent('footer')['brandBlock']['logoUrl'] ?? null);

        $this->editor('footer')
            ->set('data.footer.en.logo', null)
            ->call('save')
            ->assertHasNoErrors();

        $content = $this->savedContent('footer');
        $this->assertArrayNotHasKey('logo', $content);
        $this->assertArrayNotHasKey('logoUrl', $content['brandBlock'] ?? []);
    }

    public function test_a_phone_changed_in_the_contact_links_list_is_kept(): void
    {
        $page = $this->editor('footer');

        $page->set($this->contactRowPath($page, 'phone').'.value', '+963 11 0000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+963 11 0000', $this->savedContactValue('phone'));
    }

    public function test_the_contact_phone_field_updates_and_clears_the_phone_link(): void
    {
        $this->editor('footer')
            ->set('data.footer.en.content.contact_phone', '+963 11 1111')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+963 11 1111', $this->savedContactValue('phone'));

        $this->editor('footer')
            ->set('data.footer.en.content.contact_phone', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->savedContactValue('phone'));
        $this->assertNotNull($this->savedContactValue('email'));
        $this->assertArrayNotHasKey('contact_phone', $this->savedContent('footer'));
    }

    private function editor(string $section): Testable
    {
        return Livewire::withQueryParams(['section' => $section])->test(ManageHomepage::class);
    }

    private function firstCardPath(Testable $page, string $section, string $repeater): string
    {
        $path = "data.{$section}.en.{$repeater}";

        return $path.'.'.array_key_first($page->get($path));
    }

    private function contactRowPath(Testable $page, string $type): string
    {
        $rows = $page->get('data.footer.en.contact_links');

        return 'data.footer.en.contact_links.'.collect($rows)->search(fn (array $row): bool => $row['type'] === $type);
    }

    /** @return array<string, mixed> */
    private function savedSection(string $key): array
    {
        $sections = HomepageDraft::query()->latest('id')->firstOrFail()->payload_json['homepage']['sections'];

        return collect($sections)->firstWhere('key', $key)['englishPayload'];
    }

    /** @return list<array<string, mixed>> */
    private function savedItems(string $key): array
    {
        return $this->savedSection($key)['items'];
    }

    /** @return array<string, mixed> */
    private function savedContent(string $key): array
    {
        return $this->savedSection($key)['content'] ?? [];
    }

    private function savedContactValue(string $type): ?string
    {
        return collect($this->savedSection('footer')['contactLinks'])->firstWhere('type', $type)['value'] ?? null;
    }
}
