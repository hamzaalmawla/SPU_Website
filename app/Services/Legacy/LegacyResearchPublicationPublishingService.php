<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyResearchPublicationPublishingServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyResearchPublicationPublicationResultDTO;
use App\Models\Research\ResearchPublication;
use App\Models\Shared\MigrationLog;
use App\Models\User\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class LegacyResearchPublicationPublishingService implements LegacyResearchPublicationPublishingServiceInterface
{
    private const APPROVAL_TOKEN = 'publish-legacy-research';

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function publishImported(
        int $actorUserId,
        bool $write = false,
        ?string $approval = null,
        ?string $batch = null,
        bool $includeDuplicateReview = true,
    ): LegacyResearchPublicationPublicationResultDTO {
        $batch = trim((string) $batch) ?: 'legacy-research-publication-'.$this->timestamp();
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Publishing legacy research requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        $actor = User::query()->find($actorUserId);
        if (! $actor instanceof User || $actor->isAccountLocked() || ! Gate::forUser($actor)->allows('publish-content')) {
            throw new InvalidArgumentException('The publication actor must be an unlocked user with content publication permission.');
        }

        $publications = ResearchPublication::query()
            ->where('legacy_source_table', 'jx_member_categories')
            ->with('translations')
            ->orderBy('legacy_source_id')
            ->get();
        $successSources = MigrationLog::query()
            ->where('module', 'research')
            ->where('source_table', 'jx_member_categories')
            ->where('target_table', 'research_publications')
            ->where('status', 'success')
            ->pluck('target_id', 'source_id');

        $eligible = [];
        $alreadyPublished = 0;
        $blocked = [];

        foreach ($publications as $publication) {
            $sourceId = (int) $publication->legacy_source_id;
            if ((int) $successSources->get($sourceId) !== (int) $publication->getKey()) {
                $blocked['missing_import_provenance'] = ($blocked['missing_import_provenance'] ?? 0) + 1;

                continue;
            }
            if ($publication->is_enabled && $publication->extraction_status === 'published') {
                $alreadyPublished++;

                continue;
            }
            if (! $publication->translations->contains(fn ($translation): bool => trim((string) $translation->title) !== '')) {
                $blocked['missing_title'] = ($blocked['missing_title'] ?? 0) + 1;

                continue;
            }
            if (! $includeDuplicateReview && $publication->extraction_status === 'duplicate_review') {
                $blocked['duplicate_review'] = ($blocked['duplicate_review'] ?? 0) + 1;

                continue;
            }

            $eligible[] = $publication;
        }

        $published = 0;
        if ($write) {
            foreach ($eligible as $publication) {
                $publication->forceFill([
                    'is_enabled' => true,
                    'extraction_status' => 'published',
                ])->save();
                MigrationLog::query()->create([
                    'module' => 'research_publication',
                    'batch_name' => $batch,
                    'source_table' => 'jx_member_categories',
                    'source_id' => (int) $publication->legacy_source_id,
                    'target_table' => 'research_publications',
                    'target_id' => (int) $publication->getKey(),
                    'status' => 'success',
                    'message' => 'Published imported legacy research publication for public archive display.',
                    'metadata' => [
                        'actor_user_id' => $actorUserId,
                        'publication_year' => $publication->publication_year,
                        'date_unknown' => $publication->published_at === null,
                        'duplicate_review_at_publish' => $publication->getOriginal('extraction_status') === 'duplicate_review',
                    ],
                ]);
                $this->auditService->log(
                    'research.publication.published',
                    $actorUserId,
                    ResearchPublication::class,
                    (int) $publication->getKey(),
                    ['legacy_source_id' => (int) $publication->legacy_source_id, 'batch' => $batch],
                );
                $published++;
            }

            if ($published > 0 && ! $this->cacheService->flushTags(['research', 'public-pages', 'seo', 'sitemap'])) {
                $this->cacheService->flushAll();
            }
        }

        ksort($blocked);

        return new LegacyResearchPublicationPublicationResultDTO(
            written: $write,
            batch: $batch,
            requestedRows: $publications->count(),
            eligibleRows: count($eligible),
            publishedRows: $published,
            alreadyPublishedRows: $alreadyPublished,
            blockedRows: array_sum($blocked),
            blockedReasonCounts: $blocked,
        );
    }

    private function timestamp(): string
    {
        return now()->format('Ymd_His');
    }
}
