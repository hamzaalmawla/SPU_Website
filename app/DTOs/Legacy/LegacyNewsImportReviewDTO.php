<?php

declare(strict_types=1);

namespace App\DTOs\Legacy;

final readonly class LegacyNewsImportReviewDTO
{
    /**
     * @param array<string, int> $rejectionReasonCounts
     */
    public function __construct(
        public int $categories,
        public int $articles,
        public int $legacyArticles,
        public int $publishedArticles,
        public int $newsArticles,
        public int $announcementArticles,
        public int $articleTranslations,
        public int $articleSeoRows,
        public int $attachments,
        public int $attachmentsWithMedia,
        public int $migrationLogRows,
        public int $migrationSuccessRows,
        public int $rejectionRows,
        public int $missingArabicTranslations,
        public int $missingEnglishTranslations,
        public int $missingSeoRows,
        public int $longSlugRows,
        public int $publishedWithoutPublishedAtRows,
        public int $attachmentsWithoutMediaRows,
        public int $orphanedAttachmentMediaRows,
        public array $rejectionReasonCounts,
        public string $status,
    ) {}
}
