<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyNewsImportReviewServiceInterface;
use App\DTOs\Legacy\LegacyNewsImportReviewDTO;
use Illuminate\Support\Facades\DB;

final class LegacyNewsImportReviewService implements LegacyNewsImportReviewServiceInterface
{
    public function review(): LegacyNewsImportReviewDTO
    {
        $articles = DB::table('news_articles')->count();
        $legacyArticles = DB::table('news_articles')->whereNotNull('legacy_source_table')->count();
        $missingArabicTranslations = $articles - $this->translatedArticleCount('ar');
        $missingEnglishTranslations = $articles - $this->translatedArticleCount('en');
        $missingSeoRows = max(0, ($articles * 2) - DB::table('news_article_seo_meta')->count());
        $longSlugRows = DB::table('news_articles')->whereRaw('LENGTH(slug) > 80')->count();
        $publishedWithoutPublishedAtRows = DB::table('news_articles')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->count();
        $attachmentsWithoutMediaRows = DB::table('news_article_attachments')
            ->whereNull('media_asset_id')
            ->count();
        $orphanedAttachmentMediaRows = DB::table('news_article_attachments')
            ->leftJoin('media_assets', 'news_article_attachments.media_asset_id', '=', 'media_assets.id')
            ->whereNotNull('news_article_attachments.media_asset_id')
            ->whereNull('media_assets.id')
            ->count();
        $rejectionReasonCounts = $this->rejectionReasonCounts();

        return new LegacyNewsImportReviewDTO(
            categories: DB::table('news_categories')->count(),
            articles: $articles,
            legacyArticles: $legacyArticles,
            publishedArticles: DB::table('news_articles')->where('status', 'published')->where('is_enabled', true)->count(),
            newsArticles: $this->categoryTypeArticleCount('news'),
            announcementArticles: $this->categoryTypeArticleCount('announcement'),
            articleTranslations: DB::table('news_article_translations')->count(),
            articleSeoRows: DB::table('news_article_seo_meta')->count(),
            attachments: DB::table('news_article_attachments')->count(),
            attachmentsWithMedia: DB::table('news_article_attachments')->whereNotNull('media_asset_id')->count(),
            migrationLogRows: DB::table('migration_logs')->where('module', 'news')->count(),
            migrationSuccessRows: DB::table('migration_logs')->where('module', 'news')->where('status', 'success')->count(),
            rejectionRows: DB::table('migration_rejections')->where('module', 'news')->count(),
            missingArabicTranslations: $missingArabicTranslations,
            missingEnglishTranslations: $missingEnglishTranslations,
            missingSeoRows: $missingSeoRows,
            longSlugRows: $longSlugRows,
            publishedWithoutPublishedAtRows: $publishedWithoutPublishedAtRows,
            attachmentsWithoutMediaRows: $attachmentsWithoutMediaRows,
            orphanedAttachmentMediaRows: $orphanedAttachmentMediaRows,
            rejectionReasonCounts: $rejectionReasonCounts,
            status: $this->status(
                $longSlugRows,
                $missingArabicTranslations,
                $missingEnglishTranslations,
                $missingSeoRows,
                $attachmentsWithoutMediaRows,
                $orphanedAttachmentMediaRows,
            ),
        );
    }

    private function translatedArticleCount(string $locale): int
    {
        return DB::table('news_article_translations')
            ->where('locale', $locale)
            ->distinct('news_article_id')
            ->count('news_article_id');
    }

    private function categoryTypeArticleCount(string $type): int
    {
        return DB::table('news_articles')
            ->join('news_categories', 'news_articles.news_category_id', '=', 'news_categories.id')
            ->where('news_categories.type', $type)
            ->count();
    }

    /** @return array<string, int> */
    private function rejectionReasonCounts(): array
    {
        return DB::table('migration_rejections')
            ->where('module', 'news')
            ->select('reason_code', DB::raw('count(*) as count'))
            ->groupBy('reason_code')
            ->orderBy('reason_code')
            ->pluck('count', 'reason_code')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    private function status(
        int $longSlugRows,
        int $missingArabicTranslations,
        int $missingEnglishTranslations,
        int $missingSeoRows,
        int $attachmentsWithoutMediaRows,
        int $orphanedAttachmentMediaRows,
    ): string {
        if ($missingArabicTranslations > 0 || $missingEnglishTranslations > 0 || $attachmentsWithoutMediaRows > 0 || $orphanedAttachmentMediaRows > 0) {
            return 'blocked';
        }

        if ($longSlugRows > 0 || $missingSeoRows > 0) {
            return 'cleanup_required';
        }

        return 'review_ready';
    }
}
