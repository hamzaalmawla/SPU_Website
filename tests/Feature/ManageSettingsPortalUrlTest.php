<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Settings\SettingsServiceInterface;
use App\Filament\Pages\ManageSettings;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin side of the transport-registration 503.
 *
 * ManageSettings validated the portal URL with ->url() alone, so a host outside
 * security.trusted_portal_hosts saved cleanly and then resolved to null on every
 * public read - a hard 503 on /{locale}/campus-life/transport/registration that
 * the editor who caused it could not see, because the form displayed the rejected
 * value as an empty box.
 */
final class ManageSettingsPortalUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
    }

    public function test_it_refuses_to_save_a_portal_url_on_an_untrusted_host(): void
    {
        Livewire::test(ManageSettings::class)
            ->set('data.utility_student_portal_url', 'https://portal.evil.example/login')
            ->callAction('save', ['group' => 'navigation'])
            ->assertHasErrors(['data.utility_student_portal_url']);

        // The stored value must be untouched - a rejected save must not clobber it.
        $this->assertSame(
            'https://my.spu.edu.sy/ar/login',
            $this->storedPortalUrl(),
        );
    }

    public function test_it_saves_a_portal_url_on_a_trusted_host(): void
    {
        Livewire::test(ManageSettings::class)
            ->set('data.utility_student_portal_url', 'https://my.spu.edu.sy/en/login')
            ->callAction('save', ['group' => 'navigation'])
            ->assertHasNoErrors();

        $this->assertSame('https://my.spu.edu.sy/en/login', $this->storedPortalUrl());
    }

    public function test_it_still_accepts_a_site_relative_path(): void
    {
        Livewire::test(ManageSettings::class)
            ->set('data.utility_student_portal_url', '/e-services/it-support')
            ->callAction('save', ['group' => 'navigation'])
            ->assertHasNoErrors();

        $this->assertSame('/e-services/it-support', $this->storedPortalUrl());
    }

    /**
     * Regression: the fields carried ->url(), which rejects site-relative paths.
     * The seeder ships '/e-services/staff-email' as the staff access URL, so
     * saving the Utility Navigation group failed validation on a field nobody had
     * touched - the section could not be saved at all without first replacing a
     * value the policy considers perfectly valid.
     */
    public function test_the_navigation_group_saves_with_the_seeded_relative_staff_url(): void
    {
        Livewire::test(ManageSettings::class)
            ->assertSet('data.utility_staff_access_url', '/e-services/staff-email')
            ->callAction('save', ['group' => 'navigation'])
            ->assertHasNoErrors();

        $this->assertSame('https://my.spu.edu.sy/ar/login', $this->storedPortalUrl());
    }

    public function test_a_rejected_stored_value_is_shown_rather_than_blanked(): void
    {
        // Simulate the production state: a value already in the database that the
        // current allow-list rejects. The editor must be able to see it to fix it.
        DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->update(['value_text' => 'https://legacy-portal.example.com/login']);

        app(SettingsServiceInterface::class);
        $this->flushSettingsCaches();

        $this->assertNull(
            app(SettingsServiceInterface::class)->getStudentPortalUrl(),
            'Precondition: the public resolver rejects this value.',
        );

        Livewire::test(ManageSettings::class)
            ->assertSet('data.utility_student_portal_url', 'https://legacy-portal.example.com/login');
    }

    private function storedPortalUrl(): ?string
    {
        $row = DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->first();

        return $row?->value_text;
    }

    private function flushSettingsCaches(): void
    {
        cache()->flush();
    }
}
