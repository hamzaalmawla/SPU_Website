<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            $this->upsertMembersPage($faculty);
            $this->upsertMembersCard($faculty);
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
            $page = DB::table('faculty_pages')->where('faculty_id', $faculty->id)->where('slug', 'members')->first();

            if ($page !== null) {
                DB::table('faculty_page_translations')->where('faculty_page_id', $page->id)->delete();
                DB::table('faculty_pages')->where('id', $page->id)->delete();
            }

            DB::table('faculty_subpage_cards')
                ->where('faculty_slug', $faculty->public_slug ?: $faculty->slug)
                ->where('subpage_slug', 'members')
                ->delete();

            $this->removeFromSubpagesJson($faculty);
        }

        Cache::flush();
    }

    /** @param object{id: int|string, public_slug: ?string, slug: string, hero_image: ?string} $faculty */
    private function upsertMembersPage(object $faculty): void
    {
        $now = now();
        $facultyId = (int) $faculty->id;
        $heroImage = (string) ($faculty->hero_image ?: '/images/uni-main-place.JPG');
        $maxSort = (int) (DB::table('faculty_pages')->where('faculty_id', $facultyId)->max('sort_order') ?? 0);

        DB::table('faculty_pages')->updateOrInsert(
            ['faculty_id' => $facultyId, 'slug' => 'members'],
            [
                'kind' => 'members',
                'hero_image' => $heroImage,
                'payload_json' => json_encode([], JSON_THROW_ON_ERROR),
                'sort_order' => $maxSort + 1,
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $pageId = (int) DB::table('faculty_pages')->where('faculty_id', $facultyId)->where('slug', 'members')->value('id');

        foreach ($this->memberLabels() as $locale => $label) {
            DB::table('faculty_page_translations')->updateOrInsert(
                ['faculty_page_id' => $pageId, 'locale' => $locale],
                [
                    'title' => $label['title'],
                    'summary' => $label['summary'],
                    'body' => null,
                    'sections_json' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    /** @param object{id: int|string, public_slug: ?string, slug: string} $faculty */
    private function upsertMembersCard(object $faculty): void
    {
        $now = now();
        $facultySlug = $faculty->public_slug ?: $faculty->slug;
        $maxSort = (int) (DB::table('faculty_subpage_cards')->where('faculty_slug', $facultySlug)->max('sort_order') ?? 0);

        DB::table('faculty_subpage_cards')->updateOrInsert(
            ['faculty_slug' => $facultySlug, 'subpage_slug' => 'members'],
            [
                'sort_order' => $maxSort + 1,
                'is_visible' => true,
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    /** @param object{id: int|string, public_slug: ?string, slug: string, subpages_json: ?string} $faculty */
    private function appendToSubpagesJson(object $faculty): void
    {
        $subpages = is_string($faculty->subpages_json)
            ? json_decode($faculty->subpages_json, true)
            : (is_array($faculty->subpages_json) ? $faculty->subpages_json : []);

        if (! is_array($subpages)) {
            $subpages = [];
        }

        if (! in_array('members', $subpages, true)) {
            $subpages[] = 'members';
            DB::table('faculties')->where('id', $faculty->id)->update([
                'subpages_json' => json_encode($subpages, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param object{id: int|string, public_slug: ?string, slug: string, subpages_json: ?string} $faculty */
    private function removeFromSubpagesJson(object $faculty): void
    {
        $subpages = is_string($faculty->subpages_json)
            ? json_decode($faculty->subpages_json, true)
            : (is_array($faculty->subpages_json) ? $faculty->subpages_json : []);

        if (! is_array($subpages)) {
            return;
        }

        $subpages = array_values(array_filter($subpages, static fn (mixed $slug): bool => $slug !== 'members'));

        DB::table('faculties')->where('id', $faculty->id)->update([
            'subpages_json' => json_encode($subpages, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, array{title: string, summary: string}> */
    private function memberLabels(): array
    {
        return [
            'ar' => ['title' => 'أعضاء الهيئة الأكاديمية', 'summary' => 'تعرف على أعضاء الهيئة الأكاديمية في الكلية.'],
            'en' => ['title' => 'Faculty Members', 'summary' => 'Meet the academic staff members of the faculty.'],
        ];
    }
};
