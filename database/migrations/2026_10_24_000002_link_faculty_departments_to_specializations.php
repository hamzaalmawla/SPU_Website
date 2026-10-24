<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * In Business and AI each specialization (study plan program) is taught by an
 * academic department of the same field, but the names differ too much to be
 * matched automatically (قسم الموارد البشرية / إدارة الموارد البشرية). This
 * records which program each department teaches, by department slug, in the
 * departments page payload the page reads before a list is published.
 *
 * Business's MIS program (mis) has no department in the old site's sections
 * page, and Basic Sciences teaches no program, so neither gets a link. A link an
 * editor already set is kept, and a department that is not there is skipped.
 * A link to a program the published plan does not have is ignored by the page.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> faculty public slug => department slug => program id */
    private const LINKS = [
        'business-administration' => [
            'human-resources' => 'hr',
            'accounting' => 'accounting',
            'business-administration' => 'management',
            'marketing' => 'marketing',
            'finance-banking' => 'finance',
        ],
        'artificial-intelligence' => [
            'software-engineering-intelligent-information-systems' => 'se',
            'ai-data-science' => 'ai',
            'intelligent-information-security' => 'sec',
            'robotics-intelligent-systems' => 'ctrl',
            'intelligent-communications' => 'comm',
            'intelligent-medical-engineering-bioinformatics' => 'med',
        ],
    ];

    public function up(): void
    {
        foreach (self::LINKS as $facultySlug => $links) {
            $facultyId = DB::table('faculties')->where('public_slug', $facultySlug)->value('id');
            $page = $facultyId === null ? null : DB::table('faculty_pages')->where('faculty_id', $facultyId)->where('slug', 'departments')->first();

            if ($page === null) {
                continue;
            }

            $existingSlugs = DB::table('departments')->where('faculty_id', $facultyId)->pluck('slug')->all();
            $payload = json_decode((string) ($page->payload_json ?? ''), true);
            $payload = is_array($payload) ? $payload : [];
            $rows = [];

            foreach (is_array($payload['departments'] ?? null) ? $payload['departments'] : [] as $row) {
                if (is_array($row) && is_string($row['slug'] ?? null)) {
                    $rows[$row['slug']] = $row;
                }
            }

            foreach ($links as $departmentSlug => $programId) {
                if (! in_array($departmentSlug, $existingSlugs, true) || trim((string) ($rows[$departmentSlug]['studyPlanDepartmentId'] ?? '')) !== '') {
                    continue;
                }

                $rows[$departmentSlug] = [...($rows[$departmentSlug] ?? ['slug' => $departmentSlug]), 'studyPlanDepartmentId' => $programId];
            }

            $payload['departments'] = array_values($rows);

            DB::table('faculty_pages')->where('id', $page->id)->update([
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The links are data an editor may since have relied on; they are left in place.
    }
};
