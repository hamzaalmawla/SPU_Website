<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Models\Page\AboutNavigationCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AboutNavigationCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_returns_fallback_when_no_cards_exist(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $cards = $service->getVisibleCards('en');

        $this->assertNotEmpty($cards);
        $this->assertArrayHasKey('title', $cards[0]);
        $this->assertArrayHasKey('link', $cards[0]);
    }

    public function test_create_card_persists_and_returns_dto(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.vision-mission');

        $this->assertDatabaseHas('about_navigation_cards', [
            'target_key' => 'about.vision-mission',
            'is_visible' => true,
            'status' => 'draft',
        ]);

        $this->assertSame('about.vision-mission', $dto->targetKey);
        $this->assertTrue($dto->isVisible);
        $this->assertSame('draft', $dto->status);
    }

    public function test_publish_makes_card_visible(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.history');
        $service->publish($dto->id);

        $this->assertDatabaseHas('about_navigation_cards', [
            'id' => $dto->id,
            'status' => 'published',
        ]);

        $cards = $service->getVisibleCards('en');
        $keys = array_column($cards, 'target_key');
        $this->assertContains('about.history', $keys);
    }

    public function test_draft_card_is_not_visible(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $service->createCard('about.history');

        $cards = $service->getVisibleCards('en');
        $keys = array_column($cards, 'target_key');

        $this->assertNotContains('about.history', $keys);
    }

    public function test_visible_cards_filter_by_visibility(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.history');
        $service->publish($dto->id);
        AboutNavigationCard::query()->where('target_key', 'about.history')->update(['is_visible' => false]);

        $cards = $service->getVisibleCards('en');
        $keys = array_column($cards, 'target_key');

        $this->assertNotContains('about.history', $keys);
    }

    public function test_delete_card_removes_it(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.leadership');

        $result = $service->deleteCard($dto->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('about_navigation_cards', [
            'target_key' => 'about.leadership',
        ]);
    }

    public function test_reorder_updates_sort_order(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $card1 = $service->createCard('about.vision-mission');
        $card2 = $service->createCard('about.history');

        $service->reorder([$card2->id, $card1->id]);

        $this->assertSame(1, AboutNavigationCard::query()->find($card2->id)->sort_order);
        $this->assertSame(2, AboutNavigationCard::query()->find($card1->id)->sort_order);
    }

    public function test_auto_create_skips_when_already_exists(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $service->createCard('about.partnerships');

        $service->autoCreateForTarget('about.partnerships');

        $this->assertDatabaseCount('about_navigation_cards', 1);
    }

    public function test_auto_create_ignores_invalid_target(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $service->autoCreateForTarget('about.nonexistent-target');

        $this->assertDatabaseCount('about_navigation_cards', 0);
    }

    public function test_all_cards_returns_collection_with_resolved_titles(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $service->createCard('about.quality-policy');

        $all = $service->getAllCards();

        $this->assertCount(1, $all);
        $dto = $all->first();
        $this->assertNotEmpty($dto->resolvedTitleAr);
        $this->assertNotEmpty($dto->resolvedTitleEn);
    }

    public function test_schedule_sets_future_publish_date(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.ethical-charter');
        $future = now()->addDay()->toDateTimeString();

        $service->schedule($dto->id, $future);

        $this->assertDatabaseHas('about_navigation_cards', [
            'id' => $dto->id,
            'status' => 'scheduled',
        ]);

        $cards = $service->getVisibleCards('en');
        $keys = array_column($cards, 'target_key');
        $this->assertNotContains('about.ethical-charter', $keys);
    }

    public function test_unpublish_reverts_to_draft(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.accreditation');
        $service->publish($dto->id);
        $service->unpublish($dto->id);

        $this->assertDatabaseHas('about_navigation_cards', [
            'id' => $dto->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function test_move_up_swaps_with_previous(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $card1 = $service->createCard('about.vision-mission');
        $card2 = $service->createCard('about.history');

        $this->assertGreaterThan($card1->sortOrder, $card2->sortOrder);

        $service->moveUp($card2->id);

        $updated1 = AboutNavigationCard::query()->find($card1->id);
        $updated2 = AboutNavigationCard::query()->find($card2->id);

        $this->assertSame($card1->sortOrder, $updated2->sort_order);
        $this->assertSame($card2->sortOrder, $updated1->sort_order);
    }

    public function test_move_down_swaps_with_next(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $card1 = $service->createCard('about.vision-mission');
        $card2 = $service->createCard('about.history');

        $service->moveDown($card1->id);

        $updated1 = AboutNavigationCard::query()->find($card1->id);
        $updated2 = AboutNavigationCard::query()->find($card2->id);

        $this->assertSame($card2->sortOrder, $updated1->sort_order);
        $this->assertSame($card1->sortOrder, $updated2->sort_order);
    }

    public function test_move_up_returns_false_when_already_first(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.vision-mission');

        $this->assertFalse($service->moveUp($dto->id));
    }

    public function test_move_down_returns_false_when_already_last(): void
    {
        $service = app(AboutNavigationCardServiceInterface::class);
        $dto = $service->createCard('about.vision-mission');

        $this->assertFalse($service->moveDown($dto->id));
    }
}
