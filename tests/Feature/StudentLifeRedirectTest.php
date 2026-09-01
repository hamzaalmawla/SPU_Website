<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * /student-life was published, is linked from the footer of every page and from
 * seeded homepage payloads, and 404s. Correcting the seeder does not help: the
 * deployed database was seeded long ago and a code change cannot reach content
 * rows. A redirect fixes every source at once, including bookmarks and whatever
 * search engines already hold.
 */
final class StudentLifeRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('locales')]
    public function test_it_redirects_to_campus_life(string $locale): void
    {
        $this->get("/{$locale}/student-life")
            ->assertRedirect("/{$locale}/campus-life")
            ->assertStatus(301);
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['ar' => ['ar'], 'en' => ['en']];
    }

    public function test_the_destination_is_a_real_route(): void
    {
        // A redirect that lands on a 404 is the failure the continuity guide
        // forbids, and it is the only way this fix could be worse than the bug.
        // Asserted at the routing layer rather than by rendering: whether the
        // landing page has publishable content is an editorial question with its
        // own tests, and coupling this to seeded content would make a content
        // gap look like a broken redirect.
        $resolved = Route::getRoutes()->match(
            Request::create('/ar/campus-life', 'GET'),
        );

        $this->assertSame('public.campus-life.landing', $resolved->getName());
    }
}
