<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('achievements')->exists()) {
            return;
        }

        $sectionId = DB::table('homepage_sections')->where('key', 'achievements_highlights')->value('id');
        if ($sectionId === null) {
            return;
        }

        $payloads = DB::table('homepage_section_translations')
            ->where('section_id', $sectionId)
            ->whereIn('locale', ['ar', 'en'])
            ->pluck('payload_json', 'locale')
            ->map(static fn (mixed $payload): array => is_string($payload) ? (json_decode($payload, true) ?: []) : (is_array($payload) ? $payload : []));
        $items = $payloads->map(static fn (array $payload): array => is_array($payload['items'] ?? null) ? $payload['items'] : []);
        $now = now();

        foreach ($items->get('en', []) as $index => $english) {
            if (! is_array($english)) {
                continue;
            }

            $arabic = is_array($items->get('ar', [])[$index] ?? null) ? $items->get('ar', [])[$index] : [];
            $slug = (Str::slug((string) ($english['typeTag'] ?? 'achievement')) ?: 'achievement').'-'.($index + 1);
            $categoryId = DB::table('achievement_categories')->insertGetId(['slug' => $slug, 'is_active' => true, 'sort_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now]);
            $achievementId = DB::table('achievements')->insertGetId(['legacy_image_path' => $english['image'] ?? $arabic['image'] ?? null, 'status' => 'published', 'published_at' => $now->copy()->subSeconds($index), 'is_public' => true, 'pin_to_homepage' => false, 'sort_order' => $index + 1, 'created_at' => $now, 'updated_at' => $now]);

            foreach (['ar' => $arabic, 'en' => $english] as $locale => $item) {
                $action = is_array($item['action'] ?? null) ? $item['action'] : [];
                DB::table('achievement_category_translations')->insert(['achievement_category_id' => $categoryId, 'locale' => $locale, 'name' => $item['typeTag'] ?? ($locale === 'ar' ? 'إنجاز' : 'Achievement'), 'created_at' => $now, 'updated_at' => $now]);
                DB::table('achievement_translations')->insert(['achievement_id' => $achievementId, 'locale' => $locale, 'title' => $item['title'] ?? '', 'summary' => $item['summary'] ?? null, 'meta' => $item['meta'] ?? null, 'action_label' => $action['label'] ?? null, 'action_url' => $action['url'] ?? null, 'created_at' => $now, 'updated_at' => $now]);
            }

            DB::table('achievement_category')->insert(['achievement_id' => $achievementId, 'achievement_category_id' => $categoryId]);
        }
    }
}
