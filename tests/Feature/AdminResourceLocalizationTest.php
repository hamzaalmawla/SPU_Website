<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\AboutPageResource\Pages\EditAboutPage;
use App\Filament\Resources\FacultySubpageCardResource;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Page\AboutPage;
use App\Models\User\User;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin panel opens in Arabic, so resource tables and forms must not
 * mix English headers into it, and must switch cleanly to English.
 */
final class AdminResourceLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_resource_label_key_has_both_translations(): void
    {
        $english = require lang_path('en/admin_resources.php');
        $arabic = require lang_path('ar/admin_resources.php');

        $this->assertSame($this->flatKeys($english), $this->flatKeys($arabic));
    }

    public function test_pages_table_headers_follow_the_admin_locale(): void
    {
        $this->actingAs($this->superAdmin(), 'web');

        $this->assertSame(
            ['العنوان', 'المعرّف', 'الحالة', 'الصفحة الأم', 'مفعّلة', 'آخر تحديث'],
            $this->pageColumnLabels('ar'),
        );
        $this->assertSame(
            ['Title', 'Slug', 'Status', 'Parent', 'Enabled', 'Updated'],
            $this->pageColumnLabels('en'),
        );
    }

    public function test_columns_without_their_own_label_use_the_shared_field_dictionary(): void
    {
        AboutPage::query()->create(['slug' => 'about', 'template' => 'about-landing', 'status' => 'published', 'is_enabled' => true]);

        $this->actingAs($this->superAdmin(), 'web')
            ->get('/admin/about-pages')
            ->assertOk()
            ->assertSee('القالب')
            ->assertSee('آخر تحديث')
            ->assertDontSee('>Template<', false);
    }

    public function test_users_table_and_model_labels_are_localized(): void
    {
        $this->actingAs($this->superAdmin(), 'web')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('آخر تسجيل دخول')
            ->assertSee('المستخدمون');
    }

    public function test_faculty_subpage_cards_sit_in_the_localized_facilities_group(): void
    {
        app()->setLocale('ar');
        $this->assertSame(__('admin.navigation.groups.facilities'), FacultySubpageCardResource::getNavigationGroup());
        $this->assertSame('تنقل الصفحات الفرعية', FacultySubpageCardResource::getNavigationLabel());

        app()->setLocale('en');
        $this->assertSame('Facilities', FacultySubpageCardResource::getNavigationGroup());
    }

    public function test_user_editor_shows_when_an_automatic_lock_lifts(): void
    {
        $admin = $this->superAdmin();
        $locked = User::factory()->create(['role_slug' => 'editor']);
        $locked->forceFill(['is_locked' => true, 'locked_at' => now(), 'locked_until' => now()->addMinutes(20)])->save();

        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $locked->getKey()])
            ->assertFormSet(['is_locked' => true])
            ->assertSee('يُفتح تلقائياً');
    }

    public function test_user_editor_shows_an_expired_lock_as_unlocked(): void
    {
        $admin = $this->superAdmin();
        $expired = User::factory()->create(['role_slug' => 'editor']);
        $expired->forceFill(['is_locked' => true, 'locked_at' => now()->subHour(), 'locked_until' => now()->subMinute()])->save();

        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $expired->getKey()])
            ->assertFormSet(['is_locked' => false]);
    }

    public function test_about_page_editor_keeps_nested_payload_intact(): void
    {
        $payload = [
            'ar' => ['quote' => 'اقتباس', 'description' => 'وصف'],
            'images' => ['primary' => '/images/about-hero-1.webp'],
            'stats' => [
                ['value' => '7', 'label' => ['ar' => 'كليات', 'en' => 'Faculties']],
                ['value' => '120', 'label' => ['ar' => 'أستاذ', 'en' => 'Professors']],
            ],
        ];
        $page = AboutPage::query()->create(['slug' => 'about', 'template' => 'about-landing', 'status' => 'published', 'is_enabled' => true, 'payload_json' => $payload]);
        foreach (['ar' => 'عن الجامعة', 'en' => 'About'] as $locale => $title) {
            $page->translations()->create(['locale' => $locale, 'title' => $title, 'sections_json' => ['cards' => [['title' => $title, 'items' => ['a', 'b']]]]]);
        }

        $this->actingAs($this->superAdmin(), 'web');

        $payload['stats'][] = ['value' => '30', 'label' => ['ar' => 'مختبر', 'en' => 'Labs']];

        Livewire::test(EditAboutPage::class, ['record' => $page->getKey()])
            ->assertFormSet(fn (array $state): bool => str_contains((string) $state['payload_json'], '"label": {'))
            ->fillForm(['payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE)])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame($payload, $page->payload_json);
        $this->assertSame([['title' => 'About', 'items' => ['a', 'b']]], $page->translations->firstWhere('locale', 'en')?->sections_json['cards']);
    }

    public function test_about_page_editor_rejects_invalid_json(): void
    {
        $page = AboutPage::query()->create(['slug' => 'about', 'template' => 'about-landing', 'status' => 'published', 'is_enabled' => true, 'payload_json' => ['a' => 'b']]);
        foreach (['ar', 'en'] as $locale) {
            $page->translations()->create(['locale' => $locale, 'title' => 'T '.$locale]);
        }

        $this->actingAs($this->superAdmin(), 'web');

        Livewire::test(EditAboutPage::class, ['record' => $page->getKey()])
            ->fillForm(['payload_json' => '{"broken": '])
            ->call('save')
            ->assertHasFormErrors(['payload_json']);

        $this->assertSame(['a' => 'b'], $page->refresh()->payload_json);
    }

    /** @return list<string> */
    private function pageColumnLabels(string $locale): array
    {
        app()->setLocale($locale);

        $table = Livewire::test(ListPages::class)->instance()->getTable();

        return array_values(array_map(
            static fn (Column $column): string => (string) $column->getLabel(),
            $table->getColumns(),
        ));
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role_slug' => 'super_admin']);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function flatKeys(array $values, string $prefix = ''): array
    {
        $keys = [];

        foreach ($values as $key => $value) {
            $keys = is_array($value)
                ? [...$keys, ...$this->flatKeys($value, $prefix.$key.'.')]
                : [...$keys, $prefix.$key];
        }

        sort($keys);

        return $keys;
    }
}
