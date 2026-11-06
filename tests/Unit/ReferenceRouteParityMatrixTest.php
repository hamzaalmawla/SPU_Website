<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReferenceRouteParityMatrixTest extends TestCase
{
    #[Test]
    public function matrix_contains_the_complete_unique_reference_inventory(): void
    {
        $contents = (string) file_get_contents(base_path('Docs/FRONTEND_ROUTE_PARITY_MATRIX.md'));
        preg_match_all('/^\| `(?<path>\/[^`]*)` \|/m', $contents, $matches);
        $paths = $matches['path'] ?? [];

        $this->assertCount(175, $paths);
        $this->assertCount(175, array_unique($paths), 'Reference route matrix contains duplicate paths.');
        $this->assertStringContainsString('167 entries', $contents);
        $this->assertStringContainsString('eight career-detail pages', $contents);
    }
}
