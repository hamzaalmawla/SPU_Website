<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\ContinuityServiceInterface;
use App\DTOs\UnresolvedRequestDTO;
use App\Models\LegacyExactRedirect;
use App\Models\LegacyFileInventory;
use App\Models\LegacyPatternRule;
use App\Models\UnresolvedLegacyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\PropertyTestHelpers;
use Tests\TestCase;

/**
 * Property-based tests for ContinuityService.
 *
 * Feature: spu-homepage-admin-foundation
 */
#[Group('property')]
class ContinuityServiceTest extends TestCase
{
    use PropertyTestHelpers;
    use RefreshDatabase;

    private ContinuityServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContinuityServiceInterface::class);

        // The upsert in logUnresolved requires a unique index on (url, method).
        // The production migration uses a prefix index (MySQL-only). For SQLite
        // testing we add a plain unique index so the upsert works correctly.
        if (\Illuminate\Support\Facades\Schema::hasTable('unresolved_legacy_requests')) {
            try {
                \Illuminate\Support\Facades\Schema::table('unresolved_legacy_requests', function (\Illuminate\Database\Schema\Blueprint $table): void {
                    $table->unique(['url', 'method'], 'uq_url_method');
                });
            } catch (\Throwable) {
                // Index may already exist from a previous test in the same transaction
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 5: Exact redirect resolution correctness
    // Feature: spu-homepage-admin-foundation, Property 5: Exact redirect resolution correctness
    // **Validates: Requirements 17.1**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function exactRedirectProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 110; $i++) {
            $legacyPath = '/' . self::randomSlugPath();
            $destination = '/' . self::randomLocale() . '/' . self::randomSlugPath();
            $statusCode = [301, 302][random_int(0, 1)];
            $cases["iteration_{$i}"] = [$legacyPath, $destination, $statusCode];
        }

        return $cases;
    }

    #[DataProvider('exactRedirectProvider')]
    public function test_exact_redirect_resolution_returns_correct_destination(
        string $legacyPath,
        string $destination,
        int $statusCode,
    ): void {
        LegacyExactRedirect::create([
            'legacy_path' => $legacyPath,
            'destination_url' => $destination,
            'status_code' => $statusCode,
            'is_active' => true,
        ]);

        $result = $this->service->resolveRedirect($legacyPath);

        $this->assertNotNull($result, "resolveRedirect must find exact match for: {$legacyPath}");
        $this->assertSame($destination, $result->destinationUrl, 'Destination URL must match seeded rule');
        $this->assertSame($statusCode, $result->statusCode, 'Status code must match seeded rule');
        $this->assertSame('exact', $result->matchType, 'Match type must be exact');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 6: Pattern redirect resolution correctness
    // Feature: spu-homepage-admin-foundation, Property 6: Pattern redirect resolution correctness
    // **Validates: Requirements 17.2**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function patternRedirectProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 110; $i++) {
            $prefix = self::randomSlugSegment();
            $suffix = self::randomSlugSegment();
            $locale = self::randomLocale();

            $pattern = '#^/' . preg_quote($prefix, '#') . '/(.+)$#';
            $replacement = '/' . $locale . '/' . $prefix . '/$1';
            $inputPath = '/' . $prefix . '/' . $suffix;
            $expectedDestination = '/' . $locale . '/' . $prefix . '/' . $suffix;

            $cases["iteration_{$i}"] = [$pattern, $replacement, $inputPath, $expectedDestination];
        }

        return $cases;
    }

    #[DataProvider('patternRedirectProvider')]
    public function test_pattern_redirect_resolution_returns_resolved_destination(
        string $pattern,
        string $replacement,
        string $inputPath,
        string $expectedDestination,
    ): void {
        LegacyPatternRule::create([
            'pattern' => $pattern,
            'replacement' => $replacement,
            'status_code' => 301,
            'priority' => 100,
            'is_active' => true,
        ]);

        $result = $this->service->resolveRedirect($inputPath);

        $this->assertNotNull($result, "resolveRedirect must find pattern match for: {$inputPath}");
        $this->assertSame($expectedDestination, $result->destinationUrl, 'Destination must have capture groups applied');
        $this->assertSame('pattern', $result->matchType, 'Match type must be pattern');
        $this->assertSame(301, $result->statusCode, 'Status code must match seeded rule');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 7: Unresolved request logging completeness
    // Feature: spu-homepage-admin-foundation, Property 7: Unresolved request logging completeness
    // **Validates: Requirements 17.3**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unresolvedRequestProvider(): array
    {
        $cases = [];
        $extensions = ['pdf', 'doc', 'jpg', 'png', 'xlsx', 'zip'];

        for ($i = 0; $i < 110; $i++) {
            $isFile = random_int(0, 1) === 1;

            if ($isFile) {
                $ext = $extensions[random_int(0, count($extensions) - 1)];
                $path = '/' . self::randomSlugPath() . '.' . $ext;
                $expectedType = 'file';
            } else {
                $path = '/' . self::randomSlugPath();
                $expectedType = 'page';
            }

            $cases["iteration_{$i}"] = [$path, $expectedType];
        }

        return $cases;
    }

    #[DataProvider('unresolvedRequestProvider')]
    public function test_unresolved_request_logging_persists_all_fields(
        string $path,
        string $expectedType,
    ): void {
        $dto = new UnresolvedRequestDTO(
            url: $path,
            queryString: random_int(0, 1) === 1 ? 'page=' . random_int(1, 100) : null,
            method: 'GET',
            referrer: random_int(0, 1) === 1 ? 'https://google.com' : null,
            resolvedLocale: self::randomLocale(),
            requestType: $expectedType,
            timestamp: now()->toIso8601String(),
        );

        $result = $this->service->logUnresolved($dto);

        $this->assertTrue($result, 'logUnresolved must return true on success');

        $record = UnresolvedLegacyRequest::where('url', $path)->first();

        $this->assertNotNull($record, "Record must be persisted for path: {$path}");
        $this->assertSame($path, $record->url, 'URL must match');
        $this->assertSame($dto->queryString, $record->query_string, 'Query string must match');
        $this->assertSame('GET', $record->method, 'Method must match');
        $this->assertSame($dto->referrer, $record->referrer, 'Referrer must match');
        $this->assertSame($dto->resolvedLocale, $record->resolved_locale, 'Locale must match');
        $this->assertSame($expectedType, $record->request_type, 'Request type must match');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 8: No redirect loops
    // Feature: spu-homepage-admin-foundation, Property 8: No redirect loops
    // **Validates: Requirements 17.4**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: list<array{legacy_path: string, destination_url: string}>, 1: string, 2: bool}>
     */
    public static function redirectChainProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 110; $i++) {
            $hasCycle = random_int(0, 1) === 1;
            $chainLength = random_int(2, 6);
            $chain = [];
            $paths = [];

            // Generate unique paths for the chain
            for ($j = 0; $j <= $chainLength; $j++) {
                $paths[] = '/' . self::randomSlugSegment() . '-chain-' . $i . '-' . $j;
            }

            // Build chain: path[0] -> path[1] -> path[2] -> ...
            for ($j = 0; $j < $chainLength; $j++) {
                $chain[] = [
                    'legacy_path' => $paths[$j],
                    'destination_url' => $paths[$j + 1],
                ];
            }

            if ($hasCycle) {
                // Create a cycle: last path points back to first
                $chain[] = [
                    'legacy_path' => $paths[$chainLength],
                    'destination_url' => $paths[0],
                ];
            }

            $cases["iteration_{$i}"] = [$chain, $paths[0], $hasCycle];
        }

        return $cases;
    }

    /**
     * @param  list<array{legacy_path: string, destination_url: string}>  $chain
     */
    #[DataProvider('redirectChainProvider')]
    public function test_redirect_resolution_terminates_within_max_hops(
        array $chain,
        string $startPath,
        bool $hasCycle,
    ): void {
        foreach ($chain as $link) {
            LegacyExactRedirect::create([
                'legacy_path' => $link['legacy_path'],
                'destination_url' => $link['destination_url'],
                'status_code' => 301,
                'is_active' => true,
            ]);
        }

        $result = $this->service->resolveRedirect($startPath);

        // Resolution must always terminate (not hang or throw)
        // If there's a cycle, we still get a result (the last valid destination before the loop)
        if (count($chain) > 0) {
            $this->assertNotNull($result, 'Chain with at least one redirect must return a result');
        }

        // The result destination must be one of the paths in the chain
        if ($result !== null) {
            $allDestinations = array_map(fn (array $link) => $link['destination_url'], $chain);
            $this->assertContains(
                $result->destinationUrl,
                $allDestinations,
                'Result destination must be from the chain'
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 9: Exact rules take priority over pattern rules
    // Feature: spu-homepage-admin-foundation, Property 9: Exact rules take priority over pattern rules
    // **Validates: Requirements 17.5**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function exactOverPatternProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 110; $i++) {
            $prefix = self::randomSlugSegment();
            $suffix = self::randomSlugSegment();
            $path = '/' . $prefix . '/' . $suffix;

            $exactDestination = '/' . self::randomLocale() . '/exact-' . self::randomSlugSegment();
            $patternDestination = '/' . self::randomLocale() . '/pattern-' . self::randomSlugSegment();

            $cases["iteration_{$i}"] = [$path, $exactDestination, $patternDestination];
        }

        return $cases;
    }

    #[DataProvider('exactOverPatternProvider')]
    public function test_exact_rules_take_priority_over_pattern_rules(
        string $path,
        string $exactDestination,
        string $patternDestination,
    ): void {
        // Seed an exact rule for this path
        LegacyExactRedirect::create([
            'legacy_path' => $path,
            'destination_url' => $exactDestination,
            'status_code' => 301,
            'is_active' => true,
        ]);

        // Seed a pattern rule that also matches this path
        $parts = explode('/', trim($path, '/'));
        $prefix = $parts[0] ?? 'fallback';
        LegacyPatternRule::create([
            'pattern' => '#^/' . preg_quote($prefix, '#') . '/(.+)$#',
            'replacement' => $patternDestination,
            'status_code' => 302,
            'priority' => 100,
            'is_active' => true,
        ]);

        $result = $this->service->resolveRedirect($path);

        $this->assertNotNull($result, "resolveRedirect must find a match for: {$path}");
        $this->assertSame($exactDestination, $result->destinationUrl, 'Exact rule destination must be returned, not pattern');
        $this->assertSame('exact', $result->matchType, 'Match type must be exact when both rules match');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 10: File continuity resolution correctness
    // Feature: spu-homepage-admin-foundation, Property 10: File continuity resolution correctness
    // **Validates: Requirements 18.1**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function fileContinuityProvider(): array
    {
        $cases = [];
        $extensions = ['pdf', 'doc', 'jpg', 'png', 'xlsx', 'zip', 'pptx'];

        for ($i = 0; $i < 110; $i++) {
            $ext = $extensions[random_int(0, count($extensions) - 1)];
            $legacyPath = '/files/' . self::randomSlugSegment() . '.' . $ext;
            $isMapped = random_int(0, 1) === 1;

            if ($isMapped) {
                $currentPath = '/media/' . self::randomSlugSegment() . '.' . $ext;
                $status = 'mapped';
            } else {
                $currentPath = null;
                $status = 'unmapped';
            }

            $cases["iteration_{$i}"] = [$legacyPath, $currentPath, $status];
        }

        return $cases;
    }

    #[DataProvider('fileContinuityProvider')]
    public function test_file_continuity_resolution_returns_correct_path(
        string $legacyPath,
        ?string $currentPath,
        string $status,
    ): void {
        LegacyFileInventory::create([
            'legacy_path' => $legacyPath,
            'current_path' => $currentPath,
            'status' => $status,
        ]);

        $result = $this->service->resolveFileContinuity($legacyPath);

        if ($status === 'mapped') {
            $this->assertSame($currentPath, $result, 'Mapped entry must return current_path');
        } else {
            $this->assertNull($result, 'Unmapped entry must return null');
        }
    }

    /**
     * Verify that non-matching paths return null.
     */
    public function test_file_continuity_returns_null_for_nonexistent_path(): void
    {
        $result = $this->service->resolveFileContinuity('/nonexistent/file.pdf');
        $this->assertNull($result, 'Non-matching path must return null');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Property 11: Redirect rule conflict detection
    // Feature: spu-homepage-admin-foundation, Property 11: Redirect rule conflict detection
    // **Validates: Requirements 27.2**
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: list<array{legacy_path: string, destination_url: string}>, 1: list<array{pattern: string, replacement_a: string, replacement_b: string}>, 2: int, 3: int}>
     */
    public static function conflictDetectionProvider(): array
    {
        $cases = [];

        for ($i = 0; $i < 110; $i++) {
            $duplicateCount = random_int(0, 3);
            $conflictCount = random_int(0, 2);

            $duplicates = [];
            for ($d = 0; $d < $duplicateCount; $d++) {
                $path = '/' . self::randomSlugSegment() . '-dup-' . $i . '-' . $d;
                $duplicates[] = [
                    'legacy_path' => $path,
                    'destination_url' => '/' . self::randomLocale() . '/' . self::randomSlugSegment(),
                ];
            }

            $conflicts = [];
            for ($c = 0; $c < $conflictCount; $c++) {
                $segment = self::randomSlugSegment();
                $pattern = '#^/' . preg_quote($segment, '#') . '-conflict-' . $i . '-' . $c . '/(.+)$#';
                $conflicts[] = [
                    'pattern' => $pattern,
                    'replacement_a' => '/ar/' . self::randomSlugSegment() . '/$1',
                    'replacement_b' => '/en/' . self::randomSlugSegment() . '/$1',
                ];
            }

            $cases["iteration_{$i}"] = [$duplicates, $conflicts, $duplicateCount, $conflictCount];
        }

        return $cases;
    }

    /**
     * @param  list<array{legacy_path: string, destination_url: string}>  $duplicates
     * @param  list<array{pattern: string, replacement_a: string, replacement_b: string}>  $conflicts
     */
    #[DataProvider('conflictDetectionProvider')]
    public function test_validate_redirect_rules_detects_duplicates_and_conflicts(
        array $duplicates,
        array $conflicts,
        int $expectedDuplicateCount,
        int $expectedConflictCount,
    ): void {
        // Seed duplicate exact rules (same legacy_path, different destinations)
        foreach ($duplicates as $dup) {
            LegacyExactRedirect::create([
                'legacy_path' => $dup['legacy_path'],
                'destination_url' => $dup['destination_url'],
                'status_code' => 301,
                'is_active' => true,
            ]);
            // Create a second rule with the same path but different destination
            LegacyExactRedirect::create([
                'legacy_path' => $dup['legacy_path'],
                'destination_url' => '/' . self::randomLocale() . '/other-' . self::randomSlugSegment(),
                'status_code' => 301,
                'is_active' => true,
            ]);
        }

        // Seed conflicting pattern rules (same pattern, different replacements)
        $priority = 100;
        foreach ($conflicts as $conflict) {
            LegacyPatternRule::create([
                'pattern' => $conflict['pattern'],
                'replacement' => $conflict['replacement_a'],
                'status_code' => 301,
                'priority' => $priority,
                'is_active' => true,
            ]);
            LegacyPatternRule::create([
                'pattern' => $conflict['pattern'],
                'replacement' => $conflict['replacement_b'],
                'status_code' => 301,
                'priority' => $priority + 1,
                'is_active' => true,
            ]);
            $priority += 10;
        }

        $result = $this->service->validateRedirectRules();

        $totalExpectedIssues = $expectedDuplicateCount + $expectedConflictCount;

        if ($totalExpectedIssues > 0) {
            $this->assertFalse($result->isValid, 'Validation must fail when duplicates or conflicts exist');
            $this->assertNotEmpty($result->errors, 'Errors must be reported for duplicates/conflicts');

            // Count errors by field
            $exactErrors = array_filter($result->errors, fn ($e) => $e->field === 'legacy_exact_redirects');
            $patternErrors = array_filter($result->errors, fn ($e) => $e->field === 'legacy_pattern_rules');

            if ($expectedDuplicateCount > 0) {
                $this->assertNotEmpty($exactErrors, 'Duplicate exact paths must be reported');
            }
            if ($expectedConflictCount > 0) {
                $this->assertNotEmpty($patternErrors, 'Conflicting patterns must be reported');
            }
        } else {
            $this->assertTrue($result->isValid, 'Validation must pass when no duplicates or conflicts exist');
        }
    }
}
