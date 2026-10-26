<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every faculty publishes a final exam schedule, but only the faculties whose
 * old site had one got the page and its card. This adds them to the others.
 * Rows that already exist are left as they are, so a page or card an editor
 * switched off stays off. The card shows only once a schedule is published.
 */
return new class extends Migration
{
    private const FACULTY_SLUGS = ['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'];

    public function up(): void
    {
        if (! Schema::hasTable('faculties') || ! Schema::hasTable('faculty_pages') || ! Schema::hasTable('faculty_subpage_cards')) {
            return;
        }

        $faculties = DB::table('faculties')
            ->whereIn('public_slug', self::FACULTY_SLUGS)
            ->get();

        foreach ($faculties as $faculty) {
            $this->insertPage($faculty);
            $this->insertCard($faculty);
        }

        Cache::flush();
    }

    public function down(): void
    {
        // The pages may hold published schedules by now; they are not removed.
    }

    /** @param object{id: int|string, hero_image: ?string} $faculty */
    private function insertPage(object $faculty): void
    {
        $facultyId = (int) $faculty->id;

        if (DB::table('faculty_pages')->where('faculty_id', $facultyId)->where('slug', 'final-exams')->exists()) {
            return;
        }

        $now = now();
        $pageId = (int) DB::table('faculty_pages')->insertGetId([
            'faculty_id' => $facultyId,
            'slug' => 'final-exams',
            'kind' => 'final-exams',
            'hero_image' => (string) ($faculty->hero_image ?: '/images/uni-main-place.JPG'),
            'payload_json' => json_encode([], JSON_THROW_ON_ERROR),
            'sort_order' => (int) (DB::table('faculty_pages')->where('faculty_id', $facultyId)->max('sort_order') ?? 0) + 1,
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (['ar' => 'برنامج الامتحان النهائي', 'en' => 'Final Exam Schedule'] as $locale => $title) {
            DB::table('faculty_page_translations')->insert([
                'faculty_page_id' => $pageId,
                'locale' => $locale,
                'title' => $title,
                'summary' => null,
                'body' => null,
                'sections_json' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param object{public_slug: ?string, slug: string} $faculty */
    private function insertCard(object $faculty): void
    {
        $facultySlug = (string) ($faculty->public_slug ?: $faculty->slug);
        $cards = DB::table('faculty_subpage_cards')->where('faculty_slug', $facultySlug);

        // A faculty without cards lists its enabled pages, so it needs none.
        if (! (clone $cards)->exists() || (clone $cards)->where('subpage_slug', 'final-exams')->exists()) {
            return;
        }

        $now = now();
        DB::table('faculty_subpage_cards')->insert([
            'faculty_slug' => $facultySlug,
            'subpage_slug' => 'final-exams',
            'sort_order' => (int) ((clone $cards)->max('sort_order') ?? 0) + 1,
            'is_visible' => true,
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
