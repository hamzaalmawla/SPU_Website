<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use Illuminate\Console\Command;

/**
 * Read-only: where staff intake requests are, per link, for the deploy log
 * (the server has no shell). Answers "a teacher got a reference but nobody
 * sees the request" without opening the database.
 */
final class StaffIntakeStatusCommand extends Command
{
    protected $signature = 'staff-intake:status {--reference=* : Also say where these request references are}';

    protected $description = 'List staff intake links with their request counts by status, and find request references';

    public function handle(StaffIntakeReviewServiceInterface $reviews): int
    {
        $references = array_values(array_filter(array_map(
            static fn (mixed $reference): string => strtoupper(trim((string) $reference)),
            (array) $this->option('reference'),
        )));
        $report = $reviews->statusReport($references);

        foreach ($report['links'] as $link) {
            $requests = $link['requests'] === [] ? 'none'
                : implode(', ', array_map(static fn (string $status, int $total): string => $status.' '.$total, array_keys($link['requests']), $link['requests']));
            $this->line(sprintf(
                '   link #%d  %s  %s  submissions %d  requests: %s  (made %s)',
                $link['link'], $link['faculty'], $link['active'] ? 'open' : 'closed', $link['submissions'], $requests, $link['created'] ?? '?',
            ));
        }
        $this->line(sprintf('   requests hidden from every reviewer (faculty disabled or missing): %d', $report['orphans']));

        foreach ($report['references'] as $reference) {
            $this->line('   '.implode('  ', array_map(
                static fn (string $key, mixed $value): string => $key.'='.(is_scalar($value) ? (string) $value : '-'),
                array_keys($reference),
                $reference,
            )));
        }

        return self::SUCCESS;
    }
}
