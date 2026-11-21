<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPageContentServiceInterface;
use App\DTOs\Legacy\LegacyPageCoverageDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class LegacyPageContentCoverageCommand extends Command
{
    protected $signature = 'legacy-import:page-coverage
        {source : The exported page content (.json), or the old site SQL dump (.sql) with --export}
        {--export= : Read the SQL dump and write the page content to this JSON path}
        {--site= : Only one site (main, medicine, research, hospital, ...)}
        {--gaps : Only pages whose text is not fully on the new site}
        {--json= : Also write the result to this JSON path}';

    protected $description = 'Read-only: how much of each old page\'s text the matching new page shows, in Arabic and English.';

    public function __construct(
        private readonly LegacyPageContentServiceInterface $pageContentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $export = $this->option('export');

        try {
            if (is_string($export) && $export !== '') {
                $this->info('Exported '.$this->pageContentService->export($source, $export).' old pages to '.$export);

                return self::SUCCESS;
            }

            $site = $this->option('site');
            $results = $this->pageContentService->coverage($source, is_string($site) && $site !== '' ? $site : null);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $shown = $this->option('gaps')
            ? array_values(array_filter($results, static fn (LegacyPageCoverageDTO $row): bool => in_array($row->status, ['missing', 'partial', 'no new page'], true) || str_starts_with((string) $row->status, 'HTTP')))
            : $results;

        $this->table(
            ['Old id', 'Site', 'Old page', 'New page', 'AR', 'EN', 'Status'],
            array_map(static fn (LegacyPageCoverageDTO $row): array => [
                $row->legacyId,
                $row->site,
                mb_strimwidth($row->breadcrumb, 0, 60, '…'),
                $row->newPath ?? '—',
                $row->coverage['ar'] === null ? '—' : $row->coverage['ar'].'%',
                $row->coverage['en'] === null ? '—' : $row->coverage['en'].'%',
                $row->status,
            ], $shown),
        );

        $byStatus = [];
        foreach ($results as $row) {
            $byStatus[(string) $row->status] = ($byStatus[(string) $row->status] ?? 0) + 1;
        }
        ksort($byStatus);
        $this->line('Old pages: '.count($results).' · '.implode(' · ', array_map(static fn (string $status, int $count): string => $status.' '.$count, array_keys($byStatus), $byStatus)));

        $lists = $this->pageContentService->recordLists($source);
        $this->table(['Old list', 'Service', 'Visible on old site', 'Migrated', 'Into'], array_map(static fn (array $list): array => [
            $list['list'], $list['service'], $list['old_visible'], $list['migrated'], implode(', ', $list['into']),
        ], $lists));

        $json = $this->option('json');
        if (is_string($json) && $json !== '') {
            file_put_contents($json, json_encode([
                'generated_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'pages' => array_map(static fn (LegacyPageCoverageDTO $row): array => (array) $row, $results),
                'record_lists' => $lists,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }

        return self::SUCCESS;
    }
}
