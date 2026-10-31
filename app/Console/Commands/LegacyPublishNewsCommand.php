<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyNewsPublicationServiceInterface;
use Illuminate\Console\Command;

final class LegacyPublishNewsCommand extends Command
{
    protected $signature = 'legacy-import:publish-news
        {--source-id=* : Explicit legacy jx_categories source IDs}
        {--featured-source-id=* : Selected source IDs to feature}
        {--actor= : Publishing user ID}
        {--write : Publish eligible records through the CMS workflow}
        {--allow-deferred-media : Publish complete text while retaining unresolved legacy media as non-rendered references}
        {--approve= : Required publication token}
        {--batch= : Optional publication batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Publish explicitly selected, provenance-backed legacy news through the CMS workflow.';

    public function __construct(private readonly LegacyNewsPublicationServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->service->publish(
            sourceIds: $this->integerOptions('source-id'),
            featuredSourceIds: $this->integerOptions('featured-source-id'),
            actorUserId: max(0, (int) $this->option('actor')),
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? $this->option('batch') : null,
            allowDeferredMedia: (bool) $this->option('allow-deferred-media'),
        );
        $payload = [
            'written' => $result->written,
            'batch' => $result->batch,
            'requested_rows' => $result->requestedRows,
            'eligible_rows' => $result->eligibleRows,
            'published_rows' => $result->publishedRows,
            'already_published_rows' => $result->alreadyPublishedRows,
            'blocked_rows' => $result->blockedRows,
            'published_source_ids' => $result->publishedSourceIds,
            'block_reason_counts' => $result->blockReasonCounts,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy News Publication');
        foreach ($payload as $key => $value) {
            $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value;
            $this->line(str_replace('_', ' ', ucfirst($key)).': '.(is_bool($display) ? ($display ? 'yes' : 'no') : $display));
        }

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function integerOptions(string $name): array
    {
        return array_map(static fn (mixed $value): int => (int) $value, (array) $this->option($name));
    }
}
