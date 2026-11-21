<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Content\FacultyMemberActivityReportServiceInterface;
use Illuminate\Console\Command;

final class FacultyMembersActivityCommand extends Command
{
    protected $signature = 'faculty-members:activity {faculty : The faculty public slug} {--since= : Date to report activity from (default today)}';

    protected $description = 'Show a faculty\'s members (listed, hidden, deleted) and the admin activity on them since a date. Read-only.';

    public function __construct(
        private readonly FacultyMemberActivityReportServiceInterface $reportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $since = is_string($this->option('since')) && $this->option('since') !== '' ? $this->option('since') : now()->toDateString();

        foreach ($this->reportService->report((string) $this->argument('faculty'), $since) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
