<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $faculties = DB::table('faculties')
            ->where('is_enabled', true)
            ->whereIn('public_slug', [
                'medicine',
                'dentistry',
                'pharmacy',
                'artificial-intelligence',
                'building-construction-engineering',
                'petroleum',
                'business-administration',
            ])
            ->get();

        foreach ($faculties as $faculty) {
            $page = DB::table('faculty_pages')
                ->where('faculty_id', $faculty->id)
                ->where('slug', 'research')
                ->first();

            if ($page === null) {
                $pageId = (int) DB::table('faculty_pages')->insertGetId([
                    'faculty_id' => $faculty->id,
                    'slug' => 'research',
                    'kind' => 'research',
                    'hero_image' => $faculty->hero_image,
                    'payload_json' => json_encode([], JSON_THROW_ON_ERROR),
                    'sort_order' => (int) DB::table('faculty_pages')->where('faculty_id', $faculty->id)->max('sort_order') + 1,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $pageId = (int) $page->id;
                DB::table('faculty_pages')->where('id', $pageId)->update([
                    'is_enabled' => true,
                    'updated_at' => now(),
                ]);
            }

            $facultyNames = DB::table('faculty_translations')
                ->where('faculty_id', $faculty->id)
                ->whereIn('locale', ['ar', 'en'])
                ->pluck('name', 'locale');

            foreach (['ar', 'en'] as $locale) {
                $facultyName = (string) ($facultyNames[$locale] ?? $faculty->public_slug);
                DB::table('faculty_page_translations')->insertOrIgnore([
                    [
                        'faculty_page_id' => $pageId,
                        'locale' => $locale,
                        'title' => $locale === 'ar' ? 'أحدث الأبحاث' : 'Latest Research',
                        'summary' => $locale === 'ar'
                            ? 'استكشف أحدث المنشورات والأبحاث العلمية في '.$facultyName.'.'
                            : 'Explore the latest scholarly publications and research from '.$facultyName.'.',
                        'body' => null,
                        'sections_json' => json_encode([], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('faculty_pages')->where('slug', 'research')->delete();
    }
};
