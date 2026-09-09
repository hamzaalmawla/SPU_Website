<?php

declare(strict_types=1);

use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $category = NewsCategory::query()->where('slug', 'agreements')->first();

        if ($category === null) {
            return;
        }

        $seededArticles = NewsArticle::query()
            ->with('translations')
            ->where('news_category_id', $category->getKey())
            ->whereIn('slug', [
                'memorandum-latakia-university',
                'scientific-cultural-manara-university',
                'scientific-cultural-al-sham-university',
                'memorandum-planning-statistics-authority',
                'virtual-university-delegation',
                'memorandum-amman-ahliya',
                'cipher-cooperation-agreement',
                'human-resources-management-association',
                'india-universities-agreements',
                'damascus-hospital-cooperation',
                'al-hawash-university-agreement',
                'almujtahid-hospital-agreement',
                'zahrawi-hospital-agreement',
                'asas-human-resources-agreement',
                'damascus-university-agreement',
            ])
            ->get();

        foreach ($seededArticles as $duplicate) {
            $titles = $duplicate->translations
                ->pluck('title')
                ->filter(fn (mixed $title): bool => is_string($title) && trim($title) !== '')
                ->map(fn (string $title): string => trim($title))
                ->values();

            $original = NewsArticle::query()
                ->with('translations')
                ->whereKeyNot($duplicate->getKey())
                ->whereHas('translations', fn ($query) => $query->whereIn('title', $titles->all()))
                ->orderByRaw('CASE WHEN cover_media_id IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('published_at')
                ->first();

            if ($original === null) {
                continue;
            }

            $original->forceFill(['news_category_id' => $category->getKey()])->save();
            $duplicate->delete();
        }
    }

    public function down(): void
    {
        // The merge is intentionally not reversed: restoring duplicates would lose
        // the original imported article relationship and its media attachments.
    }
};
