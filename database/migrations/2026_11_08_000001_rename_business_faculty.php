<?php

declare(strict_types=1);

use App\Support\BusinessFacultyName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the business faculty to "كلية الأعمال" / "Business Faculty" (2026-11-08)
 * wherever the site names it today: the faculty record, its pages and their
 * pending drafts, the header menu, the homepage, the faculties landing page, the
 * tuition table, the organizational chart, the research faculty filter, the
 * application forms, the dean's role and the faculty logo's alt text.
 *
 * The rules are App\Support\BusinessFacultyName: "إدارة الأعمال" / "Business
 * Administration" as a subject (programs, courses, research themes, degrees,
 * the department) stays. Dated records stay as written: news articles, staff
 * profiles and older published versions. Site search and the AI assistant are
 * rebuilt from the pages by the deploy (search:index, chatbot:sync-website).
 * A row is written only when its text changes, so running it again does nothing.
 */
return new class extends Migration
{
    /** CMS pages that name the faculty, besides the faculty's own (facilities.business-administration*). */
    private const TARGETS = [
        'facilities.landing',
        'admissions.tuition',
        'about.organizational-structure',
        'research.projects',
    ];

    private const PENDING = ['draft', 'scheduled'];

    public function up(): void
    {
        $facultyId = DB::table('faculties')
            ->where(fn ($query) => $query->where('public_slug', 'business-administration')->orWhere('slug', 'business'))
            ->value('id');

        if ($facultyId !== null) {
            $this->renameFaculty((int) $facultyId);
        }

        $this->renameCmsTargets();
        $this->renameRows('menu_items', [], ['label' => 'name'], fn ($query) => $query
            ->where('url', 'like', '%/faculties/business-administration')
            ->orWhere('url', 'like', '%/faculties/business-administration/'));
        $this->renameRows('homepage_section_translations', ['payload_json'], []);
        $this->renameHomepageDrafts();
        $this->renameRows('form_definitions', ['schema_json'], []);
        $this->renameRows('person_translations', [], ['role' => 'text']);
        $this->renameRows('media_assets', [], ['title_ar' => 'text', 'title_en' => 'text', 'alt_text_ar' => 'text', 'alt_text_en' => 'text'], fn ($query) => $query
            ->where('title_en', 'like', '%Administrative Sciences%')
            ->orWhere('title_en', 'like', '%Business Administration%')
            ->orWhere('alt_text_en', 'like', '%Administrative Sciences%')
            ->orWhere('alt_text_en', 'like', '%Business Administration%'));
    }

    public function down(): void
    {
        // A rename of published text: restore from the pre-deploy database dump if needed.
    }

    private function renameFaculty(int $facultyId): void
    {
        $this->renameRows('faculty_translations', [], [
            'name' => 'name',
            'catalog_title' => 'name',
            'short_description' => 'text',
            'description' => 'text',
        ], fn ($query) => $query->where('faculty_id', $facultyId));

        $this->renameRows('faculty_pages', ['payload_json'], [], fn ($query) => $query->where('faculty_id', $facultyId));

        $pageIds = DB::table('faculty_pages')->where('faculty_id', $facultyId)->pluck('id')->all();
        $this->renameRows('faculty_page_translations', ['sections_json'], [
            'title' => 'text',
            'summary' => 'text',
            'body' => 'text',
        ], fn ($query) => $query->whereIn('faculty_page_id', $pageIds));

        $projectIds = DB::table('faculty_student_projects')->where('faculty_id', $facultyId)->pluck('id')->all();
        $this->renameRows('faculty_student_project_translations', ['body_json'], [
            'summary' => 'text',
            'team' => 'text',
        ], fn ($query) => $query->whereIn('faculty_student_project_id', $projectIds));
    }

    private function renameCmsTargets(): void
    {
        $targets = fn ($query) => $query
            ->whereIn('target_key', self::TARGETS)
            ->orWhere('target_key', 'facilities.business-administration')
            ->orWhere('target_key', 'like', 'facilities.business-administration.%');

        $this->renameRows('cms_target_contents', ['payload_json'], [], $targets);
        $this->renameRows('cms_drafts', ['payload_json'], [], fn ($query) => $query
            ->whereIn('status', self::PENDING)
            ->where($targets));

        // Leadership rows: only the role, never the person's biography.
        $persons = fn ($query) => $query->where('target_key', 'like', 'entity.person.%');
        $this->renamePersonRoles('cms_target_contents', $persons);
        $this->renamePersonRoles('cms_drafts', fn ($query) => $query->whereIn('status', self::PENDING)->where($persons));
    }

    private function renameHomepageDrafts(): void
    {
        $latest = DB::table('homepage_drafts')->max('id');

        $this->renameRows('homepage_drafts', ['payload_json'], [], fn ($query) => $query
            ->whereIn('status', self::PENDING)
            ->orWhere('id', $latest ?? 0));
    }

    private function renamePersonRoles(string $table, Closure $scope): void
    {
        DB::table($table)->where($scope)->orderBy('id')->chunkById(100, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                $payload = $this->decode($row->payload_json);

                if (! is_array($payload) || ! is_array($payload['translations'] ?? null)) {
                    continue;
                }

                $renamed = $payload;
                foreach ($renamed['translations'] as $locale => $translation) {
                    if (is_array($translation) && is_string($translation['role'] ?? null)) {
                        $renamed['translations'][$locale]['role'] = BusinessFacultyName::text($translation['role']);
                    }
                }

                if ($renamed !== $payload) {
                    DB::table($table)->where('id', $row->id)->update(['payload_json' => json_encode($renamed, JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }

    /**
     * @param  list<string>  $jsonColumns  JSON columns renamed with the payload rules
     * @param  array<string, 'name'|'text'>  $textColumns  text columns: 'name' holds only the faculty's name
     */
    private function renameRows(string $table, array $jsonColumns, array $textColumns, ?Closure $scope = null): void
    {
        $query = DB::table($table);
        if ($scope !== null) {
            $query->where($scope);
        }

        $query->orderBy('id')->chunkById(100, function ($rows) use ($table, $jsonColumns, $textColumns): void {
            foreach ($rows as $row) {
                $changes = [];

                foreach ($jsonColumns as $column) {
                    $decoded = $this->decode($row->{$column} ?? null);
                    if ($decoded === null) {
                        continue;
                    }

                    $renamed = BusinessFacultyName::payload($decoded);
                    if ($renamed !== $decoded) {
                        $changes[$column] = json_encode($renamed, JSON_THROW_ON_ERROR);
                    }
                }

                foreach ($textColumns as $column => $rule) {
                    $value = $row->{$column} ?? null;
                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $renamed = $rule === 'name' ? BusinessFacultyName::name($value) : BusinessFacultyName::text($value);
                    if ($renamed !== $value) {
                        $changes[$column] = $renamed;
                    }
                }

                if ($changes !== []) {
                    DB::table($table)->where('id', $row->id)->update($changes);
                }
            }
        });
    }

    private function decode(mixed $json): mixed
    {
        if (! is_string($json) || $json === '') {
            return null;
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }
};
