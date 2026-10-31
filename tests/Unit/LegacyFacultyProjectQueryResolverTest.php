<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyFacultyProjectQueryResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_legacy_project_redirects_to_existing_faculty_detail_page(): void
    {
        $faculty = Faculty::query()->create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        FacultyStudentProject::query()->create([
            'faculty_id' => (int) $faculty->getKey(),
            'legacy_source_id' => 6147,
            'legacy_service_type' => 44,
            'slug' => 'pharmacy-project-6147',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $resolution = app(LegacyQueryRedirectResolverInterface::class)->resolve(
            '/pharm/index.php',
            'page=show&ex=2&dir=items&lang=1&service=44&cat_id=6147',
        );

        self::assertSame('/ar/faculties/pharmacy/projects/pharmacy-project-6147', $resolution?->destinationUrl);
        self::assertSame(301, $resolution?->statusCode);
    }

    public function test_hidden_or_wrong_service_project_does_not_redirect(): void
    {
        $faculty = Faculty::query()->create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);
        FacultyStudentProject::query()->create([
            'faculty_id' => (int) $faculty->getKey(),
            'legacy_source_id' => 7002,
            'legacy_service_type' => 44,
            'slug' => 'pharmacy-project-7002',
            'sort_order' => 1,
            'is_enabled' => false,
        ]);
        $resolver = app(LegacyQueryRedirectResolverInterface::class);

        self::assertSame(
            '/ar/faculties/pharmacy/research',
            $resolver->resolve('/pharm/index.php', 'page=show&dir=items&lang=1&service=44&cat_id=7002')?->destinationUrl,
        );
        self::assertNull($resolver->resolve('/info/index.php', 'page=show&dir=items&lang=1&service=44&cat_id=7002'));
    }
}
