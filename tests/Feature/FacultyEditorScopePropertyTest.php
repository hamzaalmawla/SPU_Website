<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page\Page;
use App\Models\User\User;
use App\Policies\PagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for faculty editor scoped page filtering.
 *
 * Feature: codebase-audit-remediation, Property 7: Faculty Editor Scoped Page Filtering
 *
 * For any faculty_editor user with a given faculty_scope_slug and any set of pages
 * with various scope slugs, PagePolicy::update() returns true only for pages
 * matching the user's scope, ensuring no cross-faculty data leakage.
 *
 * **Validates: Requirements 2.3**
 */
#[Group('property')]
class FacultyEditorScopePropertyTest extends TestCase
{
    use PropertyTestHelpers;
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────────────────────────────

    private const FACULTY_SLUGS = [
        'medicine',
        'pharmacy',
        'engineering',
        'business',
        'arts',
        'science',
        'dentistry',
        'nursing',
        'law',
        'education',
    ];

    // ──────────────────────────────────────────────────────────────────────
    // Generators
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Pick a random faculty scope slug.
     */
    private static function randomFacultySlug(): string
    {
        return self::FACULTY_SLUGS[random_int(0, count(self::FACULTY_SLUGS) - 1)];
    }

    /**
     * Generate a random set of page scope slugs (some matching, some not).
     *
     * @return list<string|null>
     */
    private static function randomPageScopes(string $userScope): array
    {
        $scopes = [];
        $count = random_int(3, 8);

        for ($i = 0; $i < $count; $i++) {
            $choice = random_int(0, 3);
            $scopes[] = match ($choice) {
                0 => $userScope,                      // matching scope
                1 => null,                            // no scope (unscoped page)
                default => self::randomFacultySlug(),  // random scope (may or may not match)
            };
        }

        return $scopes;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Data Providers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Generate faculty editor scoping scenarios.
     *
     * Each case provides: [userScope, pageScopes]
     *
     * @return array<string, array{0: string, 1: list<string|null>}>
     */
    public static function facultyEditorScopeProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 20; $i++) {
            $userScope = self::randomFacultySlug();
            $pageScopes = self::randomPageScopes($userScope);

            $cases["scope_iteration_{$i}"] = [$userScope, $pageScopes];
        }

        return $cases;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 7 — Faculty Editor Scoped Page Filtering
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Property 7: For any faculty_editor with a given faculty_scope_slug,
     * PagePolicy::update() returns true only for pages matching the user's scope.
     *
     * Pages are created in the database and then have their faculty_scope_slug
     * attribute set in memory (the pages table uses this attribute for scope
     * filtering via the policy layer).
     *
     * **Validates: Requirements 2.3**
     */
    #[Test]
    #[DataProvider('facultyEditorScopeProvider')]
    public function faculty_editor_can_only_update_pages_matching_their_scope(
        string $userScope,
        array $pageScopes,
    ): void {
        $policy = new PagePolicy;

        // Create a faculty_editor user with the given scope
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => $userScope,
            'is_locked' => false,
        ]);

        foreach ($pageScopes as $index => $pageScope) {
            // Create a page and set the faculty_scope_slug attribute
            $page = Page::factory()->create([
                'slug' => 'scope-test-'.$userScope.'-'.$index.'-'.random_int(1000, 9999),
                'type' => 'landing',
                'template' => 'default',
                'status' => 'draft',
                'is_enabled' => true,
            ]);

            // Set the faculty_scope_slug attribute on the model instance
            // (the policy checks this attribute for scope-based authorization)
            $page->setAttribute('faculty_scope_slug', $pageScope);

            $canUpdate = $policy->update($user, $page);

            if ($pageScope === $userScope) {
                $this->assertTrue(
                    $canUpdate,
                    sprintf(
                        'Faculty editor (scope=%s) must be allowed to update page with matching scope=%s',
                        $userScope,
                        $pageScope ?? 'null',
                    )
                );
            } else {
                $this->assertFalse(
                    $canUpdate,
                    sprintf(
                        'Faculty editor (scope=%s) must NOT be allowed to update page with scope=%s',
                        $userScope,
                        $pageScope ?? 'null',
                    )
                );
            }
        }
    }

    /**
     * Property 7: viewAny always returns true for faculty_editor regardless of scope.
     *
     * **Validates: Requirements 2.3**
     */
    #[Test]
    #[DataProvider('facultyEditorScopeProvider')]
    public function faculty_editor_can_always_view_page_list(
        string $userScope,
        array $pageScopes,
    ): void {
        $policy = new PagePolicy;

        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => $userScope,
            'is_locked' => false,
        ]);

        $this->assertTrue(
            $policy->viewAny($user),
            sprintf(
                'Faculty editor (scope=%s) must always be allowed to view the page list',
                $userScope,
            )
        );
    }
}
