<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every faculty gets a Faculty Council page (`/faculties/{faculty}/council`) and
 * its navigation card: the dean, vice deans and other positions, and the council,
 * all picked from the faculty's academic staff. Until an editor publishes the
 * page it shows the dean from About › Leadership and the department heads.
 * A faculty that already has the page or card keeps it as it is.
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
            $this->upsertCouncilPage($faculty);
            $this->insertCouncilCard($faculty);
            $this->appendToSubpagesJson($faculty);
        }

        Cache::flush();
    }

    public function down(): void
    {
        if (! Schema::hasTable('faculties') || ! Schema::hasTable('faculty_pages') || ! Schema::hasTable('faculty_subpage_cards')) {
            return;
        }

        $faculties = DB::table('faculties')
            ->whereIn('public_slug', self::FACULTY_SLUGS)
            ->get();

        foreach ($faculties as $faculty) {
            $page = DB::table('faculty_pages')->where('faculty_id', $faculty->id)->where('slug', 'council')->first();

            if ($page !== null) {
                DB::table('faculty_page_translations')->where('faculty_page_id', $page->id)->delete();
                DB::table('faculty_pages')->where('id', $page->id)->delete();
            }

            DB::table('faculty_subpage_cards')
                ->where('faculty_slug', $faculty->public_slug ?: $faculty->slug)
                ->where('subpage_slug', 'council')
                ->delete();

            $this->removeFromSubpagesJson($faculty);
        }

        Cache::flush();
    }

    /** @param object{id: int|string, public_slug: ?string, slug: string, hero_image: ?string} $faculty */
    private function upsertCouncilPage(object $faculty): void
    {
        $facultyId = (int) $faculty->id;

        if (DB::table('faculty_pages')->where('faculty_id', $facultyId)->where('slug', 'council')->exists()) {
            return;
        }

        $now = now();
        $pageId = DB::table('faculty_pages')->insertGetId([
            'faculty_id' => $facultyId,
            'slug' => 'council',
            'kind' => 'council',
            'hero_image' => (string) ($faculty->hero_image ?: '/images/uni-main-place.JPG'),
            'payload_json' => json_encode([], JSON_THROW_ON_ERROR),
            'sort_order' => (int) (DB::table('faculty_pages')->where('faculty_id', $facultyId)->max('sort_order') ?? 0) + 1,
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $names = DB::table('faculty_translations')->where('faculty_id', $facultyId)->pluck('name', 'locale');

        foreach ($this->labels($names->all()) as $locale => $label) {
            DB::table('faculty_page_translations')->insert([
                'faculty_page_id' => $pageId,
                'locale' => $locale,
                'title' => $label['title'],
                'summary' => $label['summary'],
                'body' => null,
                'sections_json' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param object{id: int|string, public_slug: ?string, slug: string} $faculty */
    private function insertCouncilCard(object $faculty): void
    {
        $facultySlug = $faculty->public_slug ?: $faculty->slug;

        if (DB::table('faculty_subpage_cards')->where('faculty_slug', $facultySlug)->where('subpage_slug', 'council')->exists()) {
            return;
        }

        $now = now();
        DB::table('faculty_subpage_cards')->insert([
            'faculty_slug' => $facultySlug,
            'subpage_slug' => 'council',
            'sort_order' => (int) (DB::table('faculty_subpage_cards')->where('faculty_slug', $facultySlug)->max('sort_order') ?? 0) + 1,
            'is_visible' => true,
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param object{id: int|string, subpages_json: mixed} $faculty */
    private function appendToSubpagesJson(object $faculty): void
    {
        $subpages = $this->subpages($faculty);

        if (! in_array('council', $subpages, true)) {
            $subpages[] = 'council';
            DB::table('faculties')->where('id', $faculty->id)->update([
                'subpages_json' => json_encode($subpages, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param object{id: int|string, subpages_json: mixed} $faculty */
    private function removeFromSubpagesJson(object $faculty): void
    {
        $subpages = array_values(array_filter($this->subpages($faculty), static fn (mixed $slug): bool => $slug !== 'council'));

        DB::table('faculties')->where('id', $faculty->id)->update([
            'subpages_json' => json_encode($subpages, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  object{subpages_json: mixed}  $faculty
     * @return list<mixed>
     */
    private function subpages(object $faculty): array
    {
        $subpages = is_string($faculty->subpages_json) ? json_decode($faculty->subpages_json, true) : $faculty->subpages_json;

        return is_array($subpages) ? array_values($subpages) : [];
    }

    /**
     * @param  array<string, string>  $names  faculty name by locale
     * @return array<string, array{title: string, summary: string}>
     */
    private function labels(array $names): array
    {
        $ar = trim((string) ($names['ar'] ?? ''));
        $en = trim((string) ($names['en'] ?? ''));

        return [
            'ar' => ['title' => 'مجلس الكلية', 'summary' => $ar !== '' ? 'عميد '.$ar.' ونوابه وأعضاء مجلس الكلية.' : 'عميد الكلية ونوابه وأعضاء مجلس الكلية.'],
            'en' => ['title' => 'Faculty Council', 'summary' => $en !== '' ? 'The dean, vice deans and council of '.$en.'.' : 'The dean, vice deans and council of the faculty.'],
        ];
    }
};
