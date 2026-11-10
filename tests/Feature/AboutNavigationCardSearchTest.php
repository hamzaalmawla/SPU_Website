<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Filament\Resources\AboutNavigationCardResource\Pages\ListAboutNavigationCards;
use App\Models\Page\AboutNavigationCard;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AboutNavigationCardResource type-hints its searchable() closure with a bare
 * `Builder` and never imports one, so the name resolves to
 * App\Filament\Resources\Builder - a class that does not exist.
 *
 * PHP resolves a parameter type only when the function is actually called, so
 * this stayed invisible: the resource loads, the table renders, and the whole
 * suite passes. It fatals the first time an editor types in the search box,
 * which is the one path nothing covered.
 */
final class AboutNavigationCardSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::query()->where('role_slug', 'super_admin')->firstOrFail(), 'web');
    }

    public function test_searching_the_table_does_not_fatal(): void
    {
        app(AboutNavigationCardServiceInterface::class)->createCard('about.vision-mission');

        // Invokes the searchable() closure - the call that resolves the type hint.
        Livewire::test(ListAboutNavigationCards::class)
            ->searchTable('vision')
            ->assertOk();
    }

    public function test_searching_matches_on_the_target_key(): void
    {
        app(AboutNavigationCardServiceInterface::class)->createCard('about.vision-mission');
        app(AboutNavigationCardServiceInterface::class)->createCard('about.history');

        Livewire::test(ListAboutNavigationCards::class)
            ->searchTable('history')
            ->assertCanSeeTableRecords(
                AboutNavigationCard::query()->where('target_key', 'about.history')->get()
            );
    }
}
