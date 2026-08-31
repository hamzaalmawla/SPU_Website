<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production runs five PHP-FPM workers with no OPcache. A page that quietly
 * grows from twenty queries to eighty still renders correctly, still passes
 * every other test, and still takes the site down under load.
 *
 * These budgets are deliberately blunt. They are not a performance target —
 * they are a tripwire, so that anything adding per-request queries to a public
 * page has to do so on purpose and move the number here in the same commit.
 */
final class PublicPageQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Measured on a cold cache, which is the worst case and the only case that
     * matters — a cache hit runs no queries at all. Budgets sit a little above
     * the measured figures so ordinary work does not trip them: /ar and /en
     * measure 71, /ar/about 62, /ar/news 63.
     *
     * These are worst-case numbers. Each page is measured with the cache
     * flushed, so the published-payload cache cannot carry between them; in
     * production it survives until a publish, and the saving is larger.
     *
     * One known repeat remains and is worth a later pass: mapItem lazy-loads
     * children on menu items one level below the eager-loaded depth, about
     * thirteen queries that each return nothing.
     *
     * @var array<string, int>
     */
    private const BUDGETS = [
        '/ar' => 79,
        '/en' => 79,
        '/ar/about' => 70,
        '/ar/news' => 71,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_pages_stay_within_their_query_budget(): void
    {
        $exceeded = [];

        foreach (self::BUDGETS as $path => $budget) {
            $count = $this->countQueriesFor($path);

            if ($count > $budget) {
                $exceeded[] = sprintf('%s used %d queries, budget is %d', $path, $count, $budget);
            }
        }

        $this->assertSame([], $exceeded, implode("\n", $exceeded)."\n\nIf the increase is deliberate, raise the budget in this file and say why in the commit message.");
    }

    private function countQueriesFor(string $path): int
    {
        // Every page is measured cold and independently. Without the flush the
        // first page pays for navigation and settings and the rest look cheap,
        // which makes the numbers depend on the order of this array.
        Cache::flush();

        $count = 0;
        $counting = true;

        DB::listen(static function () use (&$count, &$counting): void {
            if ($counting) {
                $count++;
            }
        });

        $this->get($path)->assertOk();

        $counting = false;

        return $count;
    }
}
