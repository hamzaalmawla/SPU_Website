<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Content\PeopleDuplicateReportServiceInterface;
use Illuminate\Console\Command;

final class PeopleDuplicatesCommand extends Command
{
    protected $signature = 'people:duplicates';

    protected $description = 'List researchers, faculty members and people that look like the same person. Read-only.';

    public function __construct(
        private readonly PeopleDuplicateReportServiceInterface $reportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->reportService->report();

        $this->line('Records: '.collect($report->totals)->map(fn (int $count, string $list): string => $count.' '.$list)->implode(', '));

        foreach ($report->sections as $title => $groups) {
            $this->newLine();
            $this->line('▸ '.$title.': '.count($groups));

            foreach ($groups as $group) {
                $this->line('  • '.$group[0]);

                foreach (array_slice($group, 1) as $line) {
                    $this->line('    = '.$line);
                }
            }
        }

        return self::SUCCESS;
    }
}
