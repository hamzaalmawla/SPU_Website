<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Legacy\LegacyNewsPublicationServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\DTOs\Legacy\LegacyNewsPublicationResultDTO;
use App\DTOs\News\NewsArticleCmsDataDTO;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleTranslation;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class LegacyNewsPublicationService implements LegacyNewsPublicationServiceInterface
{
    private const APPROVAL_TOKEN = 'publish-legacy-news';

    private const MAX_BATCH_SIZE = 25;

    public function __construct(
        private readonly NewsArticleCmsServiceInterface $articleCmsService,
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
    ) {}

    public function publish(
        array $sourceIds,
        array $featuredSourceIds,
        int $actorUserId,
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        bool $allowDeferredMedia = false,
    ): LegacyNewsPublicationResultDTO {
        $sourceIds = $this->normalizedIds($sourceIds, 'source');
        $featuredSourceIds = $this->normalizedIds($featuredSourceIds, 'featured source');
        $batch = trim((string) $batch) ?: 'legacy-news-publication-'.now()->format('Ymd_His');

        if ($sourceIds === []) {
            throw new InvalidArgumentException('At least one explicit legacy news source ID is required.');
        }
        if (count($sourceIds) > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('A legacy news publication batch may contain at most '.self::MAX_BATCH_SIZE.' source IDs.');
        }
        if (array_diff($featuredSourceIds, $sourceIds) !== []) {
            throw new InvalidArgumentException('Every featured source ID must also be included in the publication source IDs.');
        }
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Publishing legacy news requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $actor = User::query()->find($actorUserId);
        if (! $actor instanceof User || $actor->isAccountLocked() || ! Gate::forUser($actor)->allows('publish-content')) {
            throw new InvalidArgumentException('The publication actor must be an unlocked user with content publication permission.');
        }

        $articles = NewsArticle::query()
            ->where('legacy_source_table', 'jx_categories')
            ->whereIn('legacy_source_id', $sourceIds)
            ->with(['category', 'translations', 'seoMeta', 'attachments.mediaAsset'])
            ->get()
            ->keyBy(fn (NewsArticle $article): int => (int) $article->legacy_source_id);
        $importedTargetIds = MigrationLog::query()
            ->where('module', 'news')
            ->where('source_table', 'jx_categories')
            ->where('target_table', 'news_articles')
            ->where('status', 'success')
            ->whereIn('source_id', $sourceIds)
            ->pluck('target_id', 'source_id');

        $eligible = [];
        $alreadyPublished = 0;
        $reasons = [];

        foreach ($sourceIds as $sourceId) {
            $article = $articles->get($sourceId);
            $reason = $this->blockReason($article, $sourceId, $importedTargetIds->get($sourceId), $allowDeferredMedia);

            if ($reason === 'already_published') {
                $alreadyPublished++;

                continue;
            }
            if ($reason !== null) {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;

                continue;
            }

            $eligible[$sourceId] = $article;
        }

        $publishedSourceIds = [];
        if ($write) {
            foreach ($sourceIds as $sourceId) {
                $article = $eligible[$sourceId] ?? null;
                if (! $article instanceof NewsArticle) {
                    continue;
                }

                $targetKey = 'entity.news-article.'.(int) $article->getKey();
                $stored = $this->articleCmsService->getStoredData($targetKey);
                if ($stored === null) {
                    $reasons['missing_cms_payload'] = ($reasons['missing_cms_payload'] ?? 0) + 1;

                    continue;
                }

                $payload = $stored->payload;
                $payload['is_enabled'] = true;
                $payload['is_featured'] = in_array($sourceId, $featuredSourceIds, true);
                $prepared = $this->articleCmsService->prepareDraft(
                    new NewsArticleCmsDataDTO((int) $article->getKey(), $payload),
                    $actorUserId,
                );
                $this->cmsWorkflowService->saveDraft((string) $prepared->targetKey, $prepared->payload, $actorUserId);
                $this->cmsWorkflowService->publish((string) $prepared->targetKey, $actorUserId);

                MigrationLog::query()->create([
                    'module' => 'news_publication',
                    'batch_name' => $batch,
                    'source_table' => 'jx_categories',
                    'source_id' => $sourceId,
                    'target_table' => 'news_articles',
                    'target_id' => (int) $article->getKey(),
                    'status' => 'success',
                    'message' => 'Published an explicitly selected, provenance-backed legacy news article.',
                    'metadata' => [
                        'actor_user_id' => $actorUserId,
                        'featured' => in_array($sourceId, $featuredSourceIds, true),
                        'deferred_media_allowed' => $allowDeferredMedia,
                        'retained_robots_directive' => 'noindex,nofollow',
                    ],
                ]);
                $publishedSourceIds[] = $sourceId;
            }
        }

        $blockedRows = count($sourceIds) - count($eligible) - $alreadyPublished;
        if ($write) {
            $blockedRows += count($eligible) - count($publishedSourceIds);
        }

        ksort($reasons);

        return new LegacyNewsPublicationResultDTO(
            written: $write,
            batch: $batch,
            requestedRows: count($sourceIds),
            eligibleRows: count($eligible),
            publishedRows: count($publishedSourceIds),
            alreadyPublishedRows: $alreadyPublished,
            blockedRows: $blockedRows,
            publishedSourceIds: $publishedSourceIds,
            blockReasonCounts: $reasons,
        );
    }

    private function blockReason(?NewsArticle $article, int $sourceId, mixed $importedTargetId, bool $allowDeferredMedia): ?string
    {
        if (! $article instanceof NewsArticle || (int) $importedTargetId !== (int) $article->getKey()) {
            return 'missing_import_provenance';
        }
        if ($article->status === 'published' && (bool) $article->is_enabled) {
            return 'already_published';
        }
        if ($article->status !== 'draft' || (bool) $article->is_enabled) {
            return 'invalid_publication_state';
        }
        if ($article->category === null || ! (bool) $article->category->is_enabled) {
            return 'disabled_or_missing_category';
        }
        if (($article->legacy_service_type === 3 && $article->category->type !== 'news')
            || ($article->legacy_service_type === 4 && $article->category->type !== 'announcement')) {
            return 'category_type_mismatch';
        }

        $arabic = $article->translations->firstWhere('locale', 'ar');
        if (! $this->isCompleteTranslation($arabic)) {
            return 'incomplete_ar_content';
        }
        if ($article->seoMeta->firstWhere('locale', 'ar') === null) {
            return 'missing_ar_seo';
        }

        $english = $article->translations->firstWhere('locale', 'en');
        if ($this->isCompleteTranslation($english) && $article->seoMeta->firstWhere('locale', 'en') === null) {
            return 'missing_en_seo';
        }

        if (! $allowDeferredMedia
            && $article->attachments->contains(fn ($attachment): bool => $attachment->media_asset_id === null || $attachment->mediaAsset === null)) {
            return 'unresolved_attachments';
        }

        return null;
    }

    private function isCompleteTranslation(mixed $translation): bool
    {
        return $translation instanceof NewsArticleTranslation
            && trim((string) $translation->title) !== ''
            && trim(strip_tags((string) $translation->body)) !== '';
    }

    /** @param array<int, mixed> $values @return list<int> */
    private function normalizedIds(array $values, string $label): array
    {
        $ids = [];
        foreach ($values as $value) {
            if ((! is_int($value) && ! (is_string($value) && ctype_digit($value))) || (int) $value < 1) {
                throw new InvalidArgumentException("Every {$label} ID must be a positive integer.");
            }
            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }
}
