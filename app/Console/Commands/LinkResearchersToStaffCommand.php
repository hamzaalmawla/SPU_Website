<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Research\ResearcherAdminServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LinkResearchersToStaffCommand extends Command
{
    protected $signature = 'researchers:link-staff
        {--write : Link (without it nothing is changed)}
        {--approve= : Required approval token for --write}';

    protected $description = 'Link researchers to the one staff member whose name matches exactly, so their papers show on that profile.';

    public function __construct(
        private readonly ResearcherAdminServiceInterface $researchers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $lines = $this->researchers->linkClearMatches((bool) $this->option('write'), is_string($this->option('approve')) ? $this->option('approve') : null);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(($this->option('write') ? 'Linked ' : 'Would link ').count($lines).' researcher(s) to their staff profile.');
        foreach ($lines as $line) {
            $this->line('  • '.$line);
        }

        return self::SUCCESS;
    }
}
