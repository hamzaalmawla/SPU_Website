<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HonorListGpaOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_honor_list_ranks_each_years_students_by_gpa_highest_first(): void
    {
        $facilities = app(FacultyPageServiceInterface::class);
        $workflow = app(CmsWorkflowServiceInterface::class);
        $author = User::query()->where('role_slug', 'super_admin')->firstOrFail();
        $targetKey = 'facilities.artificial-intelligence.valedictorians';

        $student = static fn (string $title, string $year, ?string $gpa): array => [
            'title' => $title, 'academicYear' => $year, 'department' => 'Computer Science', 'faculty' => 'AI Engineering', 'gpa' => $gpa, 'image' => '/images/slider-1.webp',
        ];
        $items = [
            $student('Gpa Low Student', '2026', '88.10'),
            $student('Gpa None Student', '2026', null),
            $student('Gpa Top Student', '2026', '97.25'),
            $student('Gpa Mid Student', '2026', '92.5 %'),
            // An older year stays after the newest one, whatever its GPA.
            $student('Gpa Older Student', '2025', '99.00'),
        ];

        $payload = $facilities->getEditablePayload($targetKey);
        $payload['translations']['en']['items'] = $items;
        $payload['translations']['ar']['items'] = $items;
        $workflow->saveDraft($targetKey, $payload, (int) $author->getKey());
        $this->assertTrue($workflow->publish($targetKey, (int) $author->getKey()));

        $titles = collect($facilities->getSubpage('artificial-intelligence', 'valedictorians', 'en')?->items ?? [])
            ->pluck('title')
            ->filter(static fn (mixed $title): bool => is_string($title) && str_starts_with($title, 'Gpa '))
            ->values()
            ->all();

        $this->assertSame(['Gpa Top Student', 'Gpa Mid Student', 'Gpa Low Student', 'Gpa None Student', 'Gpa Older Student'], $titles);

        $this->get('/en/faculties/artificial-intelligence/valedictorians')
            ->assertOk()
            ->assertSeeInOrder(['Gpa Top Student', 'Gpa Mid Student', 'Gpa Low Student']);
    }
}
