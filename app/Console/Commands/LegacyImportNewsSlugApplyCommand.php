<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyNewsSlugCleanupApplyServiceInterface;
use App\DTOs\Legacy\LegacyNewsSlugCleanupApplyResultDTO;
use Illuminate\Console\Command;

final class LegacyImportNewsSlugApplyCommand extends Command
{
    private const APPROVAL_TOKEN = 'news-slug-cleanup';

    protected $signature = 'legacy-import:news-slug-apply
        {--limit= : Optional number of long slugs to mutate}
        {--all : Apply every planned long-slug cleanup}
        {--approve= : Required approval token: news-slug-cleanup}
        {--json : Output machine-readable JSON}';

    protected $description = 'Apply approved legacy news slug cleanup and exact redirects in one transaction.';

    public function __construct(
        private readonly LegacyNewsSlugCleanupApplyServiceInterface $applyService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('approve') !== self::APPROVAL_TOKEN) {
            $this->error('Refusing to mutate news slugs without --approve='.self::APPROVAL_TOKEN.'.');

            return self::FAILURE;
        }

        $limit = $this->resolveLimit();
        $result = $this->applyService->apply($limit);
        $payload = $this->toArray($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Legacy News Slug Cleanup Apply');
        $this->line('Status: '.$result->status);
        $this->table(['Metric', 'Value'], collect($payload)->map(
            fn (mixed $value, string $key): array => [str_replace('_', ' ', $key), (string) $value]
        )->values()->all());

        return self::SUCCESS;
    }

    private function resolveLimit(): ?int
    {
        if ((bool) $this->option('all')) {
            return null;
        }

        $limit = $this->option('limit');

        return is_numeric($limit) ? max(1, (int) $limit) : 1;
    }

    /** @return array<string, int|string> */
    private function toArray(LegacyNewsSlugCleanupApplyResultDTO $result): array
    {
        return [
            'status' => $result->status,
            'planned_rows' => $result->plannedRows,
            'updated_articles' => $result->updatedArticles,
            'created_redirects' => $result->createdRedirects,
            'updated_redirects' => $result->updatedRedirects,
            'skipped_rows' => $result->skippedRows,
        ];
    }
}
