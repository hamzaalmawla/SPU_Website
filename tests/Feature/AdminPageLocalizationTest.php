<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The CMS pages (Manage*, two-factor setup, homepage form) open in Arabic,
 * so their forms and notifications must not mix English into the RTL panel,
 * and must switch cleanly to English.
 */
final class AdminPageLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCES = [
        'app/Filament/Pages/ManageAbout.php',
        'app/Filament/Pages/ManageAdmissions.php',
        'app/Filament/Pages/ManageCampusLife.php',
        'app/Filament/Pages/ManageContactPage.php',
        'app/Filament/Pages/ManageEServicesPage.php',
        'app/Filament/Pages/ManageHomepage.php',
        'app/Filament/Pages/ManageMenu.php',
        'app/Filament/Pages/ManageNews.php',
        'app/Filament/Pages/ManageSettings.php',
        'app/Filament/Pages/TwoFactorSetup.php',
        'app/Filament/Resources/Concerns/InteractsWithAboutEntityCmsWorkflow.php',
        'app/Filament/Support/HomepageFormSchema.php',
        'resources/views/filament/pages/manage-homepage.blade.php',
        'resources/views/filament/pages/manage-menu.blade.php',
        'resources/views/filament/pages/two-factor-setup.blade.php',
    ];

    public function test_every_page_label_key_has_both_translations(): void
    {
        $english = require lang_path('en/admin_pages.php');
        $arabic = require lang_path('ar/admin_pages.php');

        $this->assertSame($this->flatKeys($english), $this->flatKeys($arabic));
    }

    public function test_every_key_the_pages_use_is_translated_in_both_locales(): void
    {
        $missing = [];

        foreach (self::SOURCES as $source) {
            preg_match_all("/__\\('(admin_pages\\.[a-z0-9_.]+[a-z0-9_])'/", (string) file_get_contents(base_path($source)), $matches);

            foreach (array_unique($matches[1]) as $key) {
                foreach (['ar', 'en'] as $locale) {
                    if (! trans()->has($key, $locale, false)) {
                        $missing[] = "{$source}: {$locale} {$key}";
                    }
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_arabic_translations_are_not_copies_of_the_english_text(): void
    {
        $english = $this->flatValues(require lang_path('en/admin_pages.php'));
        $arabic = $this->flatValues(require lang_path('ar/admin_pages.php'));

        $untranslated = array_keys(array_filter(
            $arabic,
            static fn (string $value, string $key): bool => $value === $english[$key] && preg_match('/[A-Za-z]{3,}/', $value) === 1,
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->assertSame([], $untranslated);
    }

    public function test_settings_page_follows_the_admin_locale(): void
    {
        $this->actingAs(User::factory()->create(['role_slug' => 'super_admin']), 'web');

        app()->setLocale('ar');
        Livewire::test(ManageSettings::class)
            ->assertSee('شريط الروابط العلوي')
            ->assertSee('إعدادات محركات البحث الافتراضية')
            ->assertDontSee('Utility Navigation')
            ->assertDontSee('Default SEO Settings');

        app()->setLocale('en');
        Livewire::test(ManageSettings::class)
            ->assertSee('Utility Navigation')
            ->assertDontSee('شريط الروابط العلوي');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function flatKeys(array $values): array
    {
        $keys = array_keys($this->flatValues($values));
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flatValues(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $flat = is_array($value)
                ? [...$flat, ...$this->flatValues($value, $prefix.$key.'.')]
                : [...$flat, $prefix.$key => (string) $value];
        }

        return $flat;
    }
}
