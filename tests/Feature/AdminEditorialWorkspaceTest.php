<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Filament\Pages\ManageAnnouncements;
use App\Filament\Pages\ManageEvents;
use App\Models\Cms\CmsDraft;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminEditorialWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_events_are_a_direct_bilingual_workspace_without_target_selector(): void
    {
        $this->actingAs($this->editor(), 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-events')
            ->assertOk()
            ->assertSee('Manage events')
            ->assertSee('Create each event once')
            ->assertSee('Upcoming events')
            ->assertSee('Arabic content')
            ->assertSee('English content')
            ->assertDontSee('Choose a News page')
            ->assertDontSee('Page / Subpage')
            ->assertDontSee('Category ID');
    }

    public function test_saving_event_workspace_preserves_shared_bilingual_identity(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::test(ManageEvents::class);
        /** @var array<string, mixed> $data */
        $data = $component->get('data');
        $eventKey = array_key_first($data['events_workspace']['upcoming'] ?? []);

        $this->assertNotNull($eventKey);

        $component
            ->set('data.events_workspace.upcoming.'.$eventKey.'.title_ar', 'فعالية ثنائية اللغة')
            ->set('data.events_workspace.upcoming.'.$eventKey.'.title_en', 'Bilingual Editorial Event')
            ->call('save');

        $draft = CmsDraft::query()->where('target_key', 'news.events')->latest('id')->firstOrFail();
        $arEvents = collect($draft->payload_json['translations']['ar']['upcoming'] ?? []);
        $enEvents = collect($draft->payload_json['translations']['en']['upcoming'] ?? []);

        $this->assertSame($arEvents->pluck('id')->all(), $enEvents->pluck('id')->all());
        $this->assertSame('فعالية ثنائية اللغة', $arEvents->first()['title'] ?? null);
        $this->assertSame('Bilingual Editorial Event', $enEvents->first()['title'] ?? null);

        $readiness = app(CmsWorkflowServiceInterface::class)->readiness('news.events');
        $this->assertTrue($readiness->isReady, json_encode($readiness->errors));
    }

    public function test_announcements_workspace_links_to_canonical_filtered_records(): void
    {
        $this->actingAs($this->editor(), 'web');

        $component = Livewire::test(ManageAnnouncements::class)
            ->assertSet('data.target_key', 'news.announcements')
            ->assertSee('ماذا تريد أن تدير؟')
            ->assertSee('إدارة الإعلانات')
            ->assertDontSee('Page / Subpage');
        $links = $component->instance()->getNewsOperationalLinks();

        $this->assertCount(1, $links);
        $this->assertStringContainsString('tableFilters', $links[0]['url']);
        $this->assertStringContainsString('category_type', $links[0]['url']);
    }

    private function editor(): User
    {
        return User::factory()->create([
            'role_slug' => 'editor',
            'is_locked' => false,
        ]);
    }
}
