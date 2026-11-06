<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyPageTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyFacultyDescriptiveContentTest extends TestCase
{
    use RefreshDatabase;

    private function overviewTranslation(string $slug, string $locale, array $sections = []): FacultyPageTranslation
    {
        $faculty = Faculty::query()->firstOrCreate(
            ['slug' => $slug],
            ['public_slug' => $slug, 'faculty_scope_slug' => $slug, 'sort_order' => 1, 'is_enabled' => true],
        );

        $page = FacultyPage::query()->firstOrCreate(
            ['faculty_id' => $faculty->getKey(), 'slug' => 'overview'],
            ['kind' => 'overview', 'sort_order' => 1, 'is_enabled' => true],
        );

        return FacultyPageTranslation::query()->create([
            'faculty_page_id' => $page->getKey(),
            'locale' => $locale,
            'title' => 'Overview',
            'sections_json' => $sections,
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_04_000003_import_legacy_faculty_descriptive_content.php');
    }

    public function test_it_imports_the_dean_message_and_vision_into_the_overview_page(): void
    {
        $ar = $this->overviewTranslation('pharmacy', 'ar');

        $this->migration()->up();

        $sections = $ar->fresh()->sections_json;
        $ids = array_column($sections, 'id');

        self::assertContains('dean-message', $ids, 'The dean message must be imported');
        self::assertContains('vision-objectives', $ids, 'Vision and objectives must be imported');

        $dean = $sections[array_search('dean-message', $ids, true)];
        self::assertStringContainsString('الصيدلة', $dean['body'], 'The pharmacy dean message must carry its own content');
        self::assertStringNotContainsString('<p', $dean['body'], 'Bodies are printed escaped, so they must not contain markup');
        self::assertNotEmpty($dean['legacy_source_id'] ?? null, 'Provenance must be recorded');
    }

    public function test_it_never_overwrites_a_section_an_editor_already_wrote(): void
    {
        $ar = $this->overviewTranslation('medicine', 'ar', [
            ['id' => 'dean-message', 'title' => 'كلمة العميد', 'body' => 'نص كتبه المحرر'],
        ]);

        $this->migration()->up();

        $sections = $ar->fresh()->sections_json;
        $dean = $sections[array_search('dean-message', array_column($sections, 'id'), true)];

        self::assertSame('نص كتبه المحرر', $dean['body'], 'An existing section must be left exactly as the editor wrote it');
    }

    public function test_it_is_idempotent_and_reversible(): void
    {
        $ar = $this->overviewTranslation('dentistry', 'ar');

        $m = $this->migration();
        $m->up();
        $first = count($ar->fresh()->sections_json);
        $m->up();

        self::assertSame($first, count($ar->fresh()->sections_json), 'Re-running must not duplicate sections');

        $m->down();
        self::assertSame([], $ar->fresh()->sections_json, 'down() must remove what up() added');
    }

    public function test_it_does_not_invent_an_english_tab_when_there_is_no_english_source(): void
    {
        $en = $this->overviewTranslation('petroleum', 'en');

        $this->migration()->up();

        $ids = array_column($en->fresh()->sections_json, 'id');
        self::assertNotContains('departments', $ids, 'Petroleum has no English departments text, so no English tab may appear');
        self::assertContains('dean-message', $ids, 'Sections that do have English must still import');
    }
}
