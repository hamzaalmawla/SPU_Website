<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Content\PeopleMergeServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class PeopleMergeCommand extends Command
{
    protected $signature = 'people:merge
        {--write : Merge (without it nothing is changed)}
        {--approve= : Required approval token for --write}';

    protected $description = 'Merge researchers and faculty members entered twice, and link staff to their leadership profile.';

    public function __construct(
        private readonly PeopleMergeServiceInterface $mergeService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->mergeService->merge((bool) $this->option('write'), is_string($this->option('approve')) ? $this->option('approve') : null);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($result->written ? 'Merged.' : 'Dry run: nothing was changed. Re-run with --write --approve=<token>.');
        foreach ($result->lines as $line) {
            $this->line('  • '.$line);
        }
        $this->table(['', 'Count'], [
            ['Researchers merged away', $result->researchersMerged],
            ['Faculty members merged away (address redirects to the kept profile)', $result->membersMerged],
            ['Faculty members linked to their leadership profile', $result->membersLinked],
            ['Groups left alone (different faculties)', $result->leftAlone],
        ]);

        return self::SUCCESS;
    }
}
