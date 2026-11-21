<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $category = DB::table('news_categories')->where('slug', 'society-events')->first();

            if ($category === null) {
                $categoryId = DB::table('news_categories')->insertGetId([
                    'slug' => 'society-events',
                    'type' => 'news',
                    'sort_order' => 4,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $categoryId = (int) $category->id;

                if ($category->type !== 'news' || $category->deleted_at !== null) {
                    throw new RuntimeException('The society-events slug already belongs to an incompatible or deleted category. Deployment stopped without overwriting it.');
                }
            }

            foreach ([
                'ar' => ['فعاليات المجتمع', 'فعاليات الجامعة السورية الخاصة ومبادراتها المجتمعية.'],
                'en' => ["Society's Events", 'Syrian Private University events and community initiatives.'],
            ] as $locale => [$name, $description]) {
                if (! DB::table('news_category_translations')
                    ->where('news_category_id', $categoryId)
                    ->where('locale', $locale)
                    ->exists()) {
                    DB::table('news_category_translations')->insert([
                        'news_category_id' => $categoryId,
                        'locale' => $locale,
                        'name' => $name,
                        'description' => $description,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            foreach ([
                ['locale' => 'ar', 'parent' => 'الأخبار', 'label' => 'فعاليات المجتمع', 'url' => '/ar/news/society-events'],
                ['locale' => 'en', 'parent' => 'News', 'label' => "Society's Events", 'url' => '/en/news/society-events'],
            ] as $item) {
                $parentId = DB::table('menu_items')
                    ->where('type', 'header')
                    ->where('group_key', 'header')
                    ->where('locale', $item['locale'])
                    ->where('label', $item['parent'])
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at')
                    ->value('id');

                if ($parentId === null || DB::table('menu_items')->where('url', $item['url'])->whereNull('deleted_at')->exists()) {
                    continue;
                }

                DB::table('menu_items')->insert([
                    'parent_id' => $parentId,
                    'type' => 'header',
                    'label' => $item['label'],
                    'locale' => $item['locale'],
                    'target_kind' => 'url',
                    'url' => $item['url'],
                    'group_key' => 'header',
                    'is_enabled' => true,
                    'is_utility' => false,
                    'open_in_new_tab' => false,
                    'sort_order' => 3,
                    'depth' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->addHomepageSectionSettings($now);
        });
    }

    public function down(): void
    {
        // Preserve any articles, translations, and navigation edited after deployment.
    }

    private function addHomepageSectionSettings(mixed $now): void
    {
        $sectionId = DB::table('homepage_sections')->where('key', 'university_news')->value('id');
        if ($sectionId === null) {
            return;
        }

        foreach (['ar', 'en'] as $locale) {
            $translation = DB::table('homepage_section_translations')->where('section_id', $sectionId)->where('locale', $locale)->first();
            if ($translation === null) {
                continue;
            }

            $payload = $this->decodePayload($translation->payload_json);
            $content = is_array($payload['content'] ?? null) ? $payload['content'] : [];
            $content['society_title'] ??= $locale === 'ar' ? 'فعاليات المجتمع' : "Society's Events";
            $content['society_cta_label'] ??= $locale === 'ar' ? 'عرض الكل' : 'View All';
            $content['society_cta_url'] ??= '/'.$locale.'/news/society-events';
            $payload['content'] = $content;

            DB::table('homepage_section_translations')->where('id', $translation->id)->update([
                'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
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
            throw new RuntimeException('Homepage translation payload has an unsupported format; deployment stopped without changing content.');
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Homepage translation payload is not a JSON object; deployment stopped without changing content.');
        }

        return $decoded;
    }
};
