<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageResearch;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminResearchWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_research_workspace_uses_task_cards_and_clear_actions(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->get('/admin/manage-research?target=research.projects')
            ->assertOk()
            ->assertSee('ما المحتوى الذي تريد إدارته؟')
            ->assertSee('المشاريع البحثية الجارية والمنجزة')
            ->assertSee('حفظ المسودة')
            ->assertSee('حفظ ومعاينة العربية')
            ->assertSee('نشر الآن');
    }

    public function test_research_workspace_loads_the_requested_task(): void
    {
        $this->actingAs($this->editor(), 'web');

        Livewire::withQueryParams(['target' => 'research.projects'])
            ->test(ManageResearch::class)
            ->assertSet('data.target_key', 'research.projects');
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
