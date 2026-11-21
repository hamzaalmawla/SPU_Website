<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_sections') || ! Schema::hasTable('homepage_section_translations')) {
            return;
        }

        DB::transaction(function (): void {
            $sectionId = DB::table('homepage_sections')->where('key', 'achievements_highlights')->value('id');
            if ($sectionId === null) {
                return;
            }

            $translations = DB::table('homepage_section_translations')
                ->where('section_id', $sectionId)
                ->whereIn('locale', ['ar', 'en'])
                ->get()
                ->keyBy('locale');
            $payloads = $translations->map(fn (object $translation): array => $this->decodePayload($translation->payload_json));

            if (! DB::table('achievements')->exists()) {
                $this->importAchievements($payloads->all());
            }

            foreach (['ar' => 'عرض الكل', 'en' => 'View All'] as $locale => $label) {
                $translation = $translations->get($locale);
                if (! is_object($translation)) {
                    continue;
                }

                $payload = $payloads->get($locale, []);
                if (! is_array($payload['sectionAction'] ?? null)) {
                    $payload['sectionAction'] = ['label' => $label, 'url' => '/'.$locale.'/achievements'];
                    DB::table('homepage_section_translations')->where('id', $translation->id)->update([
                        'payload_json' => $this->encodePayload($payload),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Imported content and existing homepage JSON are intentionally retained.
    }

    /** @param array<string, array<string, mixed>> $payloads */
    private function importAchievements(array $payloads): void
    {
        $itemsByLocale = collect($payloads)->map(static fn (array $payload): array => is_array($payload['items'] ?? null) ? $payload['items'] : []);
        $count = max(count($itemsByLocale->get('ar', [])), count($itemsByLocale->get('en', [])));
        $now = now();

        for ($index = 0; $index < $count; $index++) {
            $arabic = is_array($itemsByLocale->get('ar', [])[$index] ?? null) ? $itemsByLocale->get('ar', [])[$index] : [];
            $english = is_array($itemsByLocale->get('en', [])[$index] ?? null) ? $itemsByLocale->get('en', [])[$index] : [];
            $arabic = $arabic !== [] ? $arabic : $english;
            $english = $english !== [] ? $english : $arabic;
            $categorySlug = Str::slug((string) ($english['typeTag'] ?? 'achievement-'.($index + 1))) ?: 'achievement-'.($index + 1);
            $categoryId = DB::table('achievement_categories')->insertGetId([
                'slug' => $categorySlug.'-'.($index + 1),
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['ar' => $arabic, 'en' => $english] as $locale => $item) {
                DB::table('achievement_category_translations')->insert([
                    'achievement_category_id' => $categoryId,
                    'locale' => $locale,
                    'name' => (string) ($item['typeTag'] ?? ($locale === 'ar' ? 'إنجاز' : 'Achievement')),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $achievementId = DB::table('achievements')->insertGetId([
                'image_media_id' => null,
                'legacy_image_path' => $english['image'] ?? $english['imageUrl'] ?? $arabic['image'] ?? $arabic['imageUrl'] ?? null,
                'status' => 'published',
                'published_at' => $now->copy()->subSeconds($index),
                'is_public' => true,
                'pin_to_homepage' => false,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (['ar' => $arabic, 'en' => $english] as $locale => $item) {
                $action = is_array($item['action'] ?? null) ? $item['action'] : [];
                DB::table('achievement_translations')->insert([
                    'achievement_id' => $achievementId,
                    'locale' => $locale,
                    'title' => (string) ($item['title'] ?? ''),
                    'summary' => $item['summary'] ?? null,
                    'meta' => $item['meta'] ?? null,
                    'action_label' => $action['label'] ?? null,
                    'action_url' => $action['url'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('achievement_category')->insert([
                'achievement_id' => $achievementId,
                'achievement_category_id' => $categoryId,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload)) {
            throw new RuntimeException('Homepage translation payload has an unsupported format; migration stopped without changing content.');
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Homepage translation payload is not a JSON object; migration stopped without changing content.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
