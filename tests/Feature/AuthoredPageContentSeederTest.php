<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cms\CmsTargetContent;
use App\Models\User\Role;
use App\Models\User\User;
use Database\Seeders\AuthoredPageContentSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The fixture/fallback removal took Admissions and Campus Life to 404 because
 * their content had nowhere to live once the non-CMS fallback was gone. Unlike
 * Research, that content is real reviewed SPU material, so it is migrated into
 * the CMS rather than deleted.
 *
 * These tests pin both halves of that: the pages are genuinely unavailable
 * before the migration, and genuinely restored after it.
 */
final class AuthoredPageContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Publishing runs through the CMS workflow, and publish-content grants to
        // the editor role only — not super_admin. Production has a dedicated
        // content.editor account for exactly this; mirror it here.
        $role = Role::query()->where('slug', 'editor')->firstOrFail();

        User::query()->forceCreate([
            'name' => 'Seeder Content Editor',
            'email' => 'seeder.editor@spu.edu.sy',
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),
            'role_id' => (int) $role->getKey(),
            'role_slug' => 'editor',
            'failed_login_attempts' => 0,
            'failed_attempts' => 0,
            'is_locked' => false,
        ]);
    }

    public function test_admissions_and_campus_life_are_unavailable_before_the_content_is_migrated(): void
    {
        self::assertSame(
            0,
            CmsTargetContent::query()
                ->where(fn ($q) => $q->where('target_key', 'like', 'admissions.%')
                    ->orWhere('target_key', 'like', 'campus_life.%'))
                ->count(),
            'Expected no published Admissions or Campus Life CMS content before seeding.',
        );

        $this->get('/ar/admissions')->assertNotFound();
        $this->get('/ar/campus-life')->assertNotFound();
    }

    public function test_seeding_restores_the_pages_from_real_content(): void
    {
        $this->seed(AuthoredPageContentSeeder::class);

        foreach (['ar', 'en'] as $locale) {
            $this->get('/'.$locale.'/admissions')->assertOk();
            $this->get('/'.$locale.'/campus-life')->assertOk();
        }

        // The Arabic landing must carry the real authored copy, not an empty shell.
        $this->get('/ar/admissions')->assertOk()->assertSee('القبول والتسجيل', false);
        $this->get('/ar/campus-life')->assertOk()->assertSee('الحياة الجامعية', false);
    }

    public function test_seeded_sections_resolve_rather_than_404(): void
    {
        $this->seed(AuthoredPageContentSeeder::class);

        foreach (['requirements', 'tuition', 'how-to-apply', 'faq'] as $slug) {
            $this->get('/ar/admissions/'.$slug)
                ->assertOk();
        }

        foreach (['services', 'transport', 'hospital', 'dental'] as $slug) {
            $this->get('/ar/campus-life/'.$slug)
                ->assertOk();
        }
    }

    public function test_reseeding_never_overwrites_content_an_editor_has_changed(): void
    {
        $this->seed(AuthoredPageContentSeeder::class);

        $before = CmsTargetContent::query()->where('target_key', 'admissions.landing')->firstOrFail();
        $originalUpdatedAt = $before->updated_at;

        $this->travel(1)->minutes();
        $this->seed(AuthoredPageContentSeeder::class);

        $after = CmsTargetContent::query()->where('target_key', 'admissions.landing')->firstOrFail();

        self::assertEquals(
            $originalUpdatedAt,
            $after->updated_at,
            'A second run must skip targets that already have published content.',
        );
    }

    public function test_e_services_migrates_as_a_group_and_keeps_working(): void
    {
        $this->seed(AuthoredPageContentSeeder::class);

        // The landing is the gate. Publishing "e_services" cuts its legacy
        // settings fallback, so it may only be published together with the detail
        // targets — otherwise the section ends up worse than before.
        self::assertTrue(
            CmsTargetContent::query()->where('target_key', 'e_services')->exists(),
            'The landing should have migrated once bilingual settings content was available.',
        );

        foreach (['library', 'staff-email', 'it-support', 'suggestions-complaints'] as $slug) {
            self::assertTrue(
                CmsTargetContent::query()->where('target_key', 'e_services.'.$slug)->exists(),
                sprintf('e_services.%s must migrate in the same pass as the landing.', $slug),
            );
        }

        foreach (['ar', 'en'] as $locale) {
            $this->get('/'.$locale.'/e-services')->assertOk();
        }
    }

    public function test_e_services_detail_pages_resolve_after_migration(): void
    {
        $this->seed(AuthoredPageContentSeeder::class);

        foreach (['library', 'staff-email', 'it-support'] as $slug) {
            $this->get('/ar/e-services/'.$slug)->assertOk();
        }

        $this->get('/ar/e-services/suggestions-complaints')->assertOk();
    }
}
