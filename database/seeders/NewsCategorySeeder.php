<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\News\NewsCategory;
use App\Models\News\NewsCategoryTranslation;
use Illuminate\Database\Seeder;

final class NewsCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'news' => [
                'type' => 'news',
                'sort_order' => 1,
                'ar' => 'الأخبار',
                'en' => 'News',
            ],
            'announcements' => [
                'type' => 'announcement',
                'sort_order' => 2,
                'ar' => 'الإعلانات',
                'en' => 'Announcements',
            ],
        ];

        foreach ($categories as $slug => $data) {
            $category = NewsCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'type' => $data['type'],
                    'sort_order' => $data['sort_order'],
                    'is_enabled' => true,
                ],
            );

            foreach (['ar', 'en'] as $locale) {
                NewsCategoryTranslation::query()->updateOrCreate(
                    ['news_category_id' => $category->getKey(), 'locale' => $locale],
                    ['name' => $data[$locale], 'description' => null],
                );
            }
        }
    }
}
