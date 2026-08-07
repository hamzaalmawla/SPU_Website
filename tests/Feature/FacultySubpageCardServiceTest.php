<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Models\Faculty\FacultySubpageCard;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        $faculty = \App\Models\Faculty\Faculty::query()->where('public_slug', 'medicine')->firstOrFail();

        \Illuminate\Support\Facades\DB::table('faculty_pages')->insert([
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

        $component = Livewire::test(\App\Filament\Pages\ManageDentistryFaculty::class);
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

        app(FacultySubpageCardServiceInterface::class)->unpublish((int) $card->getKey());

        Cache::flush();

        $this->assertNull($service->getSubpage('dentistry', 'projects', 'en'));
    }

    private function editor(): \App\Models\User\User
    {
        return \App\Models\User\User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
