<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyNewsSlugCleanupPlannerServiceInterface;
use App\DTOs\Legacy\LegacyNewsSlugCleanupItemDTO;
use App\DTOs\Legacy\LegacyNewsSlugCleanupPlanDTO;
use Illuminate\Console\Command;

final class LegacyImportNewsSlugPlanCommand extends Command
{
    protected $signature = 'legacy-import:news-slug-plan
        {--limit=50 : Number of long slugs to include in the dry-run plan}
        {--all : Include every long slug in the dry-run plan}
        {--output= : Optional JSON export path}
        {--json : Output machine-readable JSON}';

    protected $description = 'Dry-run legacy news slug cleanup and redirect requirements without mutating data.';

    public function __construct(
        private readonly LegacyNewsSlugCleanupPlannerServiceInterface $plannerService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (bool) $this->option('all') ? null : max(1, (int) $this->option('limit'));
        $plan = $this->plannerService->plan($limit);
        $payload = $this->toArray($plan);
        $json = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $outputPath = $this->option('output');

        if (is_string($outputPath) && $outputPath !== '') {
            $directory = dirname($outputPath);

            if ($directory !== '.' && ! is_dir($directory)) {
                $this->error('Output directory does not exist: '.$directory);

                return self::FAILURE;
            }

            file_put_contents($outputPath, $json);
            $this->info('Exported slug cleanup plan: '.$outputPath);
        }

        if ((bool) $this->option('json')) {
            $this->line($json);

            return self::SUCCESS;
        }

        $this->info('Legacy News Slug Cleanup Plan');
        $this->line('Status: '.$plan->status);
        $this->table(['Metric', 'Value'], [
            ['max slug length', (string) $plan->maxSlugLength],
            ['total long slug rows', (string) $plan->totalLongSlugRows],
            ['planned rows', (string) $plan->plannedRows],
            ['omitted rows', (string) $plan->omittedRows],
            ['collision adjusted rows', (string) $plan->collisionAdjustedRows],
        ]);

        $this->table(
            ['Article ID', 'Old Length', 'Proposed Length', 'Collision', 'Old Slug', 'Proposed Slug'],
            $plan->items->map(fn (LegacyNewsSlugCleanupItemDTO $item): array => [
                (string) $item->articleId,
                (string) $item->oldSlugLength,
                (string) $item->proposedSlugLength,
                $item->collisionAdjusted ? 'yes' : 'no',
                str($item->oldSlug)->limit(80)->toString(),
                $item->proposedSlug,
            ])->all(),
        );

        if ($plan->plannedRows > 0) {
            $this->warn('Dry-run only. Each changed slug requires AR and EN canonical redirects before mutation.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function toArray(LegacyNewsSlugCleanupPlanDTO $plan): array
    {
        return [
            'status' => $plan->status,
            'max_slug_length' => $plan->maxSlugLength,
            'limit' => $plan->limit,
            'total_long_slug_rows' => $plan->totalLongSlugRows,
            'planned_rows' => $plan->plannedRows,
            'omitted_rows' => $plan->omittedRows,
            'collision_adjusted_rows' => $plan->collisionAdjustedRows,
            'items' => $plan->items->map(fn (LegacyNewsSlugCleanupItemDTO $item): array => [
                'article_id' => $item->articleId,
                'legacy_source_id' => $item->legacySourceId,
                'legacy_service_type' => $item->legacyServiceType,
                'old_slug' => $item->oldSlug,
                'proposed_slug' => $item->proposedSlug,
                'old_slug_length' => $item->oldSlugLength,
                'proposed_slug_length' => $item->proposedSlugLength,
                'collision_adjusted' => $item->collisionAdjusted,
                'redirect_required' => $item->redirectRequired,
                'redirect_from_ar' => $item->redirectFromAr,
                'redirect_to_ar' => $item->redirectToAr,
                'redirect_from_en' => $item->redirectFromEn,
                'redirect_to_en' => $item->redirectToEn,
            ])->all(),
        ];
    }
}
