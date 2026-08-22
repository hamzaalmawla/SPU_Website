<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Filament\Pages\ManageDentistryFaculty;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultySubpageCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class FacultySubpageCardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_available_subpage_options_are_dynamic_per_faculty(): void
    {
        $service = app(FacultySubpageCardServiceInterface::class);

        $medicine = $service->availableSubpageOptions('medicine');
        $pharmacy = $service->availableSubpageOptions('pharmacy');

        $this->assertArrayHasKey('overview', $medicine);
        $this->assertArrayHasKey('departments', $medicine);
        $this->assertArrayHasKey('research', $medicine);
        $this->assertArrayNotHasKey('training', $medicine);
        $this->assertArrayNotHasKey('study-plan-course', $medicine);

        $this->assertArrayHasKey('training', $pharmacy);
        $this->assertArrayNotHasKey('study-plan-course', $pharmacy);
    }

    public function test_newly_enabled_custom_page_appears_in_available_options(): void
    {
        $service = app(FacultySubpageCardServiceInterface::class);
        $faculty = Faculty::query()->where('public_slug', 'medicine')->firstOrFail();

        DB::table('faculty_pages')->insert([
            'faculty_id' => $faculty->getKey(),
            'slug' => 'careers',
            'kind' => 'careers',
            'payload_json' => json_encode([]),
            'sort_order' => 99,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $options = $service->availableSubpageOptions('medicine');

        $this->assertArrayHasKey('careers', $options);
    }

    public function test_workspace_add_card_dropdown_includes_uncarded_subpages(): void
    {
        $this->actingAs($this->editor(), 'web');

        FacultySubpageCard::query()
            ->where('faculty_slug', 'dentistry')
            ->where('subpage_slug', 'projects')
            ->delete();

        $component = Livewire::test(ManageDentistryFaculty::class);
        $options = $component->instance()->getNavAvailableSubpages();

        $this->assertArrayHasKey('projects', $options);
        $this->assertArrayNotHasKey('overview', $options);
    }

    public function test_hiding_a_card_removes_the_subpage_from_public_availability(): void
    {
        $service = app(FacultyPageServiceInterface::class);

        $this->assertNotNull($service->getSubpage('dentistry', 'labs', 'en'));

        FacultySubpageCard::query()
            ->where('faculty_slug', 'dentistry')
            ->where('subpage_slug', 'labs')
            ->update(['is_visible' => false]);

        Cache::flush();

        $this->assertNull($service->getSubpage('dentistry', 'labs', 'en'));
        $this->assertNotNull($service->getSubpage('dentistry', 'overview', 'en'));
    }

    public function test_unpublishing_a_card_removes_the_subpage_from_public_availability(): void
    {
        $service = app(FacultyPageServiceInterface::class);

        $this->assertNotNull($service->getSubpage('dentistry', 'projects', 'en'));

        $card = FacultySubpageCard::query()
            ->where('faculty_slug', 'dentistry')
            ->where('subpage_slug', 'projects')
            ->firstOrFail();

        app(FacultySubpageCardServiceInterface::class)->unpublish((int) $card->getKey(), $this->editor()->id);

        Cache::flush();

        $this->assertNull($service->getSubpage('dentistry', 'projects', 'en'));
    }

    public function test_creation_defaults_to_draft_and_audits_the_mutation(): void
    {
        $editor = $this->editor();
        $card = app(FacultySubpageCardServiceInterface::class)->createCard(
            facultySlug: 'medicine',
            subpageSlug: 'new-draft-card',
            userId: $editor->id,
        );

        $this->assertSame('draft', $card->status);
        $this->assertNull($card->publishedAt);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'faculty_subpage_card.created',
            'user_id' => $editor->id,
            'entity_id' => $card->id,
        ]);
    }

    public function test_faculty_editor_cannot_publish_or_bypass_publication_through_update(): void
    {
        $service = app(FacultySubpageCardServiceInterface::class);
        $editor = $this->editor();
        $card = $service->createCard('medicine', 'publication-bypass', $editor->id);
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
            'is_locked' => false,
        ]);

        $this->assertTrue($service->updateCard($card->id, ['status' => 'published'], $facultyEditor->id));
        $this->assertDatabaseHas('faculty_subpage_cards', ['id' => $card->id, 'status' => 'draft']);

        $this->expectException(AuthorizationException::class);
        $service->publish($card->id, $facultyEditor->id);
    }

    public function test_faculty_editor_cannot_mutate_an_out_of_scope_card(): void
    {
        $card = FacultySubpageCard::query()->where('faculty_slug', 'dentistry')->firstOrFail();
        $facultyEditor = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'medicine',
            'is_locked' => false,
        ]);

        $this->expectException(AuthorizationException::class);
        app(FacultySubpageCardServiceInterface::class)->toggleVisibility((int) $card->getKey(), $facultyEditor->id);
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
