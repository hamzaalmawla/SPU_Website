<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brings the faculty descriptive content across from the old site.
 *
 * The Dean's message, vision and objectives, and departments existed for six
 * faculties on spu.edu.sy and nowhere on v2. Checked across medicine, dentistry,
 * pharmacy, petroleum and business administration, on each faculty landing page,
 * /overview and /departments: zero occurrences of كلمة عميد, مجلس الكلية or
 * الرؤية والأهداف. A faculty page without a dean's message reads as unfinished,
 * and this is the content a university is judged on.
 *
 * Source is database/seeders/Data/legacy_faculty_descriptive_content.json,
 * extracted from jx_categories service types 21/31/41/51/61/71 - the mapping the
 * project importer already established, confirmed here by reading the petroleum
 * dean's message and finding it describes هندسة البترول.
 *
 * Stored as plain text, deliberately. The faculty views print section bodies with
 * {{ }}, which escapes, so markup would appear on the page as literal
 * <p style="..."> rather than as formatting. Block ends became blank lines so the
 * paragraphs survive as structure for whenever the template renders them richly.
 *
 * Service type 81 is NOT a faculty despite looking like one in the numbering - it
 * is the research portal (بوابة البحث العلمي). It is left out of this on purpose
 * rather than filed under a faculty it does not belong to.
 *
 * Appends only. A section whose id is already present is left exactly as it is,
 * so an editor who rewrites the dean's message does not get it overwritten by a
 * re-run, and down() removes only the ids this added.
 */
return new class extends Migration
{
    private const SOURCE = 'database/seeders/Data/legacy_faculty_descriptive_content.json';

    /** Section order on the page, most important first. */
    private const ORDER = ['dean-message', 'vision-objectives', 'departments', 'mission-objectives'];

    public function up(): void
    {
        $data = $this->source();
        $added = 0;
        $skippedFaculty = [];

        foreach ($data as $facultySlug => $sections) {
            $facultyId = DB::table('faculties')
                ->where('slug', $facultySlug)
                ->orWhere('public_slug', $facultySlug)
                ->value('id');

            if ($facultyId === null) {
                $skippedFaculty[] = $facultySlug;

                continue;
            }

            $pageId = DB::table('faculty_pages')
                ->where('faculty_id', $facultyId)
                ->where('slug', 'overview')
                ->value('id');

            if ($pageId === null) {
                $skippedFaculty[] = $facultySlug.' (no overview page)';

                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                $translation = DB::table('faculty_page_translations')
                    ->where('faculty_page_id', $pageId)
                    ->where('locale', $locale)
                    ->first();

                if ($translation === null) {
                    continue;
                }

                $existing = json_decode((string) ($translation->sections_json ?? '[]'), true);
                $existing = is_array($existing) ? $existing : [];
                $present = array_flip(array_values(array_filter(array_map(
                    static fn (mixed $s): ?string => is_array($s) && isset($s['id']) ? (string) $s['id'] : null,
                    $existing,
                ))));

                foreach (self::ORDER as $sectionId) {
                    if (! isset($sections[$sectionId]) || isset($present[$sectionId])) {
                        continue;
                    }

                    $section = $sections[$sectionId];
                    $body = $locale === 'ar' ? ($section['body_ar'] ?? null) : ($section['body_en'] ?? null);

                    // No English for a section means no English tab, rather than
                    // an English tab showing Arabic.
                    if (! is_string($body) || trim($body) === '') {
                        continue;
                    }

                    $existing[] = [
                        'id' => $sectionId,
                        'title' => (string) ($locale === 'ar' ? $section['title_ar'] : $section['title_en']),
                        'body' => $body,
                        'legacy_source_id' => $section['legacy_id'] ?? null,
                    ];
                    $added++;
                }

                DB::table('faculty_page_translations')
                    ->where('id', $translation->id)
                    ->update(['sections_json' => json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
        }

        if (app()->runningInConsole()) {
            echo "  Imported {$added} faculty descriptive section(s).".PHP_EOL;
            if ($skippedFaculty !== []) {
                echo '  Skipped: '.implode(', ', $skippedFaculty).PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        $ids = array_flip(self::ORDER);

        DB::table('faculty_page_translations')->orderBy('id')->each(function (object $row) use ($ids): void {
            $sections = json_decode((string) ($row->sections_json ?? '[]'), true);
            if (! is_array($sections) || $sections === []) {
                return;
            }

            $kept = array_values(array_filter($sections, static function (mixed $s) use ($ids): bool {
                // Only remove sections this migration added - identified by both
                // a known id AND the legacy marker, so a hand-written section
                // that happens to share an id survives.
                return ! (is_array($s) && isset($s['id'], $s['legacy_source_id']) && isset($ids[$s['id']]));
            }));

            if (count($kept) !== count($sections)) {
                DB::table('faculty_page_translations')
                    ->where('id', $row->id)
                    ->update(['sections_json' => json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
        });
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function source(): array
    {
        $path = base_path(self::SOURCE);

        if (! is_file($path)) {
            throw new RuntimeException('Missing faculty descriptive content source: '.self::SOURCE);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
};
