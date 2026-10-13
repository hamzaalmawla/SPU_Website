<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class FacultyStudyPlanImportTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'facilities.dentistry.study_plan';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_dry_run_validates_without_storing_anything(): void
    {
        $user = $this->editor();
        $result = $this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), false);

        $this->assertTrue($result->valid, json_encode($result->errors, JSON_THROW_ON_ERROR));
        $this->assertFalse($result->written);
        $this->assertNull($result->draftVersion);
        $this->assertSame(['one' => 3], $result->coursesByDepartment);
        $this->assertSame(0, CmsDraft::query()->where('target_key', self::TARGET)->count());
    }

    public function test_write_stores_a_localized_unpublished_draft(): void
    {
        $user = $this->editor();
        $result = $this->importer()->import('dentistry', $this->source(), $user->email, true);

        $this->assertTrue($result->written);
        $this->assertNotNull($result->draftVersion);

        $payload = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $user->getKey());
        $this->assertIsArray($payload);

        $en = $payload['translations']['en']['payload']['plan']['departments'][0];
        $ar = $payload['translations']['ar']['payload']['plan']['departments'][0];
        $this->assertSame('Dept One', $en['name']);
        $this->assertSame('القسم الأول', $ar['name']);

        $course = $en['terms'][1]['courses'][0];
        $this->assertSame('Data Structures', $course['title']);
        $this->assertSame('هياكل البيانات', $ar['terms'][1]['courses'][0]['title']);
        $this->assertSame(['cs-101'], $course['prerequisites']);
        $this->assertSame(140, $course['prerequisiteCredits']);
        $this->assertSame([], $course['lessons']);
        $this->assertSame(['theory' => 2, 'practical' => 2], $course['hours']);
    }

    public function test_a_plan_with_editor_drafts_is_skipped_and_left_untouched(): void
    {
        $user = $this->editor();
        $first = $this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), true);
        $this->assertTrue($first->written);
        $before = CmsDraft::query()->where('target_key', self::TARGET)->orderBy('id')->get()->map->only(['id', 'version', 'status'])->all();

        $source = $this->source();
        $source['departments'][0]['nameEn'] = 'Should Never Appear';
        $second = $this->importer()->import('dentistry', $source, (string) $user->getKey(), true);

        $this->assertTrue($second->skipped);
        $this->assertFalse($second->written);
        $this->assertNull($second->draftVersion);
        $this->assertStringContainsString('left exactly as it is', (string) $second->skipReason);
        $this->assertSame($before, CmsDraft::query()->where('target_key', self::TARGET)->orderBy('id')->get()->map->only(['id', 'version', 'status'])->all());
    }

    public function test_a_published_plan_is_skipped_even_without_any_draft(): void
    {
        $user = $this->editor();
        CmsTargetContent::query()->create([
            'target_key' => self::TARGET,
            'payload_json' => ['translations' => []],
            'status' => 'published',
            'updated_by' => $user->getKey(),
            'published_at' => now(),
        ]);

        $result = $this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), true);

        $this->assertTrue($result->skipped);
        $this->assertSame(0, CmsDraft::query()->where('target_key', self::TARGET)->count());
        $this->assertSame(['translations' => []], CmsTargetContent::query()->where('target_key', self::TARGET)->firstOrFail()->payload_json);
    }

    public function test_the_explicit_override_replaces_an_edited_plan_as_a_new_draft(): void
    {
        $user = $this->editor();
        $this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), true);

        $result = $this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), true, true);

        $this->assertFalse($result->skipped);
        $this->assertTrue($result->written);
        $this->assertSame(2, $result->draftVersion);
    }

    public function test_a_dry_run_on_an_edited_plan_reports_the_skip_too(): void
    {
        $user = $this->editor();
        $this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), true);

        $this->assertTrue($this->importer()->import('dentistry', $this->source(), (string) $user->getKey(), false)->skipped);
    }

    public function test_fractional_credits_are_kept(): void
    {
        $user = $this->editor();
        $source = $this->source();
        $source['departments'][0]['terms'][1]['courses'][1]['credits'] = 4.5;

        $this->importer()->import('dentistry', $source, (string) $user->getKey(), true);

        $payload = app(CmsWorkflowServiceInterface::class)->latestEditableDraftPayload(self::TARGET, (int) $user->getKey());
        $this->assertSame(4.5, $payload['translations']['en']['payload']['plan']['departments'][0]['terms'][1]['courses'][1]['credits']);
    }

    public function test_dangling_prerequisites_are_rejected_and_nothing_is_saved(): void
    {
        $user = $this->editor();
        $source = $this->source();
        $source['departments'][0]['terms'][1]['courses'][0]['prerequisites'] = ['does-not-exist'];

        $result = $this->importer()->import('dentistry', $source, (string) $user->getKey(), true);

        $this->assertFalse($result->valid);
        $this->assertFalse($result->written);
        $this->assertSame(0, CmsDraft::query()->where('target_key', self::TARGET)->count());
    }

    public function test_unknown_user_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer()->import('dentistry', $this->source(), 'nobody@example.test', false);
    }

    public function test_empty_source_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer()->import('dentistry', ['departments' => []], (string) $this->editor()->getKey(), false);
    }

    private function importer(): FacultyStudyPlanImportServiceInterface
    {
        return app(FacultyStudyPlanImportServiceInterface::class);
    }

    private function editor(): User
    {
        return User::factory()->create(['role_slug' => 'editor', 'is_locked' => false]);
    }

    /** @return array<string, mixed> */
    private function source(): array
    {
        $course = static fn (string $id, string $code, string $titleEn, string $titleAr, int $term, array $prerequisites = [], int $credits = 0): array => [
            'id' => $id,
            'code' => $code,
            'titleEn' => $titleEn,
            'titleAr' => $titleAr,
            'term' => $term,
            'type' => 'specialization',
            'required' => true,
            'forced' => false,
            'credits' => 3,
            'prerequisites' => $prerequisites,
            'prerequisiteCredits' => $credits,
            'instructor' => null,
            'hours' => ['theory' => 2, 'practical' => 2],
            'descriptionEn' => '',
            'descriptionAr' => '',
            'lessons' => [],
        ];

        return ['departments' => [[
            'id' => 'one',
            'nameEn' => 'Dept One',
            'nameAr' => 'القسم الأول',
            'totalCredits' => 9,
            'terms' => [
                ['id' => 1, 'labelEn' => '1', 'labelAr' => '1', 'courses' => [
                    $course('cs-101', 'CS-101', 'Programming', 'البرمجة', 1),
                ]],
                ['id' => 2, 'labelEn' => '2', 'labelAr' => '2', 'courses' => [
                    $course('cs-201', 'CS-201', 'Data Structures', 'هياكل البيانات', 2, ['cs-101'], 140),
                    $course('cs-202', 'CS-202', 'Databases', 'قواعد البيانات', 2, ['cs-101']),
                ]],
            ],
        ]]];
    }
}
