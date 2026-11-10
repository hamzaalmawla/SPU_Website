<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyNewsImportReviewServiceInterface;
use App\DTOs\Legacy\LegacyNewsImportReviewDTO;
use Illuminate\Console\Command;

final class LegacyImportNewsReviewCommand extends Command
{
    protected $signature = 'legacy-import:news-review {--json : Output machine-readable JSON}';

    protected $description = 'Review already-imported legacy news and announcements without mutating data.';

    public function __construct(
        private readonly LegacyNewsImportReviewServiceInterface $reviewService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $review = $this->reviewService->review();
        $payload = $this->toArray($review);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Legacy News Import Review');
        $this->line('Status: '.$review->status);
        $this->table(['Metric', 'Value'], collect($payload)->except(['rejection_reason_counts', 'status'])->map(
            fn (mixed $value, string $key): array => [str_replace('_', ' ', $key), (string) $value]
        )->values()->all());

        if ($review->rejectionReasonCounts !== []) {
            $this->warn('Rejection reasons');
            $this->table(['Reason', 'Rows'], collect($review->rejectionReasonCounts)->map(
                fn (int $count, string $reason): array => [$reason, (string) $count]
            )->values()->all());
        }

        if ($review->status !== 'review_ready') {
            $this->warn('News import should remain in review/quarantine until cleanup blockers are resolved.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyNewsImportReviewDTO $review): array
    {
        return [
            'status' => $review->status,
            'categories' => $review->categories,
            'articles' => $review->articles,
            'legacy_articles' => $review->legacyArticles,
            'published_articles' => $review->publishedArticles,
            'news_articles' => $review->newsArticles,
            'announcement_articles' => $review->announcementArticles,
            'article_translations' => $review->articleTranslations,
            'article_seo_rows' => $review->articleSeoRows,
            'attachments' => $review->attachments,
            'attachments_with_media' => $review->attachmentsWithMedia,
            'migration_log_rows' => $review->migrationLogRows,
            'migration_success_rows' => $review->migrationSuccessRows,
            'rejection_rows' => $review->rejectionRows,
            'missing_arabic_translations' => $review->missingArabicTranslations,
            'missing_english_translations' => $review->missingEnglishTranslations,
            'missing_seo_rows' => $review->missingSeoRows,
            'long_slug_rows' => $review->longSlugRows,
            'published_without_published_at_rows' => $review->publishedWithoutPublishedAtRows,
            'attachments_without_media_rows' => $review->attachmentsWithoutMediaRows,
            'orphaned_attachment_media_rows' => $review->orphanedAttachmentMediaRows,
            'rejection_reason_counts' => $review->rejectionReasonCounts,
        ];
    }
}
