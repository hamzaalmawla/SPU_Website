<?php

declare(strict_types=1);

namespace Tests\Feature\PX06;

use App\Filament\Pages\ManageHomepage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Feature tests for ManageHomepage Filament page.
 *
 * Requirements: 19.1–19.5
 */
class ManageHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_manage_homepage(): void
    {
        $user = $this->createUser('super_admin');

        $this->actingAs($user);

        $this->assertTrue(ManageHomepage::canAccess());
    }

    public function test_editor_can_access_manage_homepage(): void
    {
        $user = $this->createUser('editor');

        $this->actingAs($user);

        $this->assertTrue(ManageHomepage::canAccess());
    }

    public function test_faculty_editor_cannot_access_manage_homepage(): void
    {
        $user = $this->createUser('faculty_editor');

        $this->actingAs($user);

        $this->assertFalse(ManageHomepage::canAccess());
    }

    public function test_unauthenticated_user_cannot_access_manage_homepage(): void
    {
        $this->assertFalse(ManageHomepage::canAccess());
    }

    public function test_homepage_form_payload_preserves_public_homepage_shape(): void
    {
        $payload = $this->invokeFormArrayToPayload([
            'headline' => 'Preview Hero',
            'content' => [
                'images' => ['/images/slider-1.webp'],
                'legalLinks' => [['label' => 'Apply', 'url' => '/en/admissions']],
            ],
            'featured_items' => [
                [
                    'title' => 'Faculty of Medicine',
                    'summary' => 'Medicine summary',
                    'imageUrl' => '/images/faculty-medicine-logo.png',
                    'accent' => '#bc2428',
                    'metric' => '6 Years',
                    'action' => ['label' => 'LEARN MORE', 'url' => '/en/faculties'],
                ],
            ],
            'stats' => [
                [
                    'value' => '20',
                    'label' => 'Years Since Founding',
                    'suffix' => '+',
                    'helperText' => 'Institutional journey.',
                    'sortOrder' => 1,
                ],
            ],
            'articles' => [
                [
                    'id' => 7,
                    'locale' => 'en',
                    'title' => 'Campus Story',
                    'slug' => 'campus-story',
                    'excerpt' => 'Story excerpt',
                    'imageUrl' => '/images/story.webp',
                    'publishedAt' => '2026-03-15',
                    'categoryLabel' => 'Campus',
                    'url' => '/en/news',
                ],
            ],
            'copyright_text' => 'Copyright SPU',
        ]);

        $this->assertSame('Preview Hero', $payload->title);
        $this->assertSame(['/images/slider-1.webp'], $payload->content['images']);
        $this->assertSame('Copyright SPU', $payload->content['copyrightText']);
        $this->assertSame('/en/admissions', $payload->content['legalLinks'][0]['url']);
        $this->assertSame('/images/faculty-medicine-logo.png', $payload->items[0]['imageUrl']);
        $this->assertSame('#bc2428', $payload->items[0]['accent']);
        $this->assertSame('/en/faculties', $payload->items[0]['action']['url']);
        $this->assertSame('Institutional journey.', $payload->stats[0]->helperText);
        $this->assertSame(1, $payload->stats[0]->sortOrder);
        $this->assertSame('/images/story.webp', $payload->articles[0]->imageUrl);
        $this->assertSame('2026-03-15', $payload->articles[0]->publishedAt);
        $this->assertSame('/en/news', $payload->articles[0]->url);
    }

    private function invokeFormArrayToPayload(array $data): \App\DTOs\HomepageSectionDataDTO
    {
        $method = new ReflectionMethod(ManageHomepage::class, 'formArrayToPayload');
        $method->setAccessible(true);

        return $method->invoke(new ManageHomepage(), $data);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'role_slug' => $role,
            'is_locked' => false,
        ]);
    }
}
