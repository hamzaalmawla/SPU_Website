<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Shared\ContinuityServiceInterface;
use Illuminate\Console\Command;

final class ReportUnresolvedCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'continuity:report-unresolved
        {--since= : Filter by date (ISO 8601 or Y-m-d)}
        {--type= : Filter by request type (page or file)}
        {--top= : Show only the N most-requested URLs}
        {--format=json : Output format (json or table)}';

    /**
     * @var string
     */
    protected $description = 'Report unresolved URL and file continuity issues';

    public function __construct(
        private readonly ContinuityServiceInterface $continuityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $since = $this->option('since');
        $type = $this->option('type');

        $this->info('Generating unresolved requests report...');

        $filters = [];

        if (is_string($since) && $since !== '') {
            $filters['since'] = $since;
        }

        if (is_string($type) && $type !== '') {
            $filters['type'] = $type;
        }

        $unresolved = $this->continuityService->getUnresolvedRequests($filters);

        // Totals are taken before --top narrows the list, so the summary still
        // describes the whole backlog rather than the slice being shown.
        $typeCounts = $unresolved->groupBy(fn ($r): string => $r->requestType)->map->count();
        $totalHits = $unresolved->sum(fn ($r): int => $r->hitCount ?? 0);
        $totalUnresolved = $unresolved->count();

        $top = $this->option('top');

        if (is_string($top) && $top !== '' && ctype_digit($top) && (int) $top > 0) {
            $unresolved = $unresolved->take((int) $top);
        }

        $items = $unresolved->map(fn ($request): array => [
            'url' => $request->url,
            'hit_count' => $request->hitCount ?? 0,
            'query_string' => $request->queryString ?? '',
            'method' => $request->method,
            'referrer' => $request->referrer ?? '',
            'resolved_locale' => $request->resolvedLocale ?? '',
            'request_type' => $request->requestType,
            'timestamp' => $request->timestamp,
        ])->all();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'total' => $totalUnresolved,
            'shown' => count($items),
            'summary' => [
                'page_requests' => $typeCounts->get('page', 0),
                'file_requests' => $typeCounts->get('file', 0),
                'total_hits' => $totalHits,
            ],
            'items' => $items,
        ];

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->outputToConsole($payload);
        }

        $this->newLine();
        $this->info("Total unresolved requests: {$payload['total']}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function outputToConsole(array $payload): void
    {
        $this->line("Page requests: {$payload['summary']['page_requests']}");
        $this->line("File requests: {$payload['summary']['file_requests']}");
        $this->line("Total hits across all unresolved URLs: {$payload['summary']['total_hits']}");

        if ($payload['shown'] !== $payload['total']) {
            $this->line("Showing the {$payload['shown']} most-requested of {$payload['total']}.");
        }

        $this->newLine();

        if ($payload['total'] === 0) {
            $this->warn('No unresolved requests found.');

            return;
        }

        $this->table(
            ['URL', 'Hits', 'Query String', 'Method', 'Referrer', 'Locale', 'Type', 'Last seen'],
            $payload['items'],
        );
    }
}
