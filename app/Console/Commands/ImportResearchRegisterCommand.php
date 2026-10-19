<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Research\ResearchRegisterImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class ImportResearchRegisterCommand extends Command
{
    protected $signature = 'research:import-register
        {source=database/seeders/Data/research/spu_sci_research.json : The converted research office register}
        {--write : Save the result (without it nothing is stored)}
        {--approve= : Required approval token for --write}';

    protected $description = 'Import the research office register\'s papers that the website does not have yet, with their researchers.';

    public function __construct(
        private readonly ResearchRegisterImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->importService->import(
                (string) $this->argument('source'),
                (bool) $this->option('write'),
                is_string($this->option('approve')) ? $this->option('approve') : null,
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($result->written ? 'Written.' : 'Dry run: nothing was stored. Re-run with --write --approve=<token>.');
        $this->table(['', 'Count'], [
            ['Papers in the register', $result->papersInFile],
            ['Already on the website (same title, left alone)', $result->alreadyOnSite],
            ['Imported before (same research number)', $result->alreadyImported],
            ['  of which completed now (empty DOI, link or faculty filled)', $result->updated],
            ['  of which given a faculty now', $result->facultiesAdded],
            ['New papers', $result->created],
            ['  of which hidden until a publication date is added', $result->hiddenWithoutDate],
            ['Papers given a "Read the paper" link', $result->linksAdded],
            ['Researchers in the register', $result->researchersInFile],
            ['New researchers', $result->researchersCreated],
            ['Researchers linked to a staff profile', $result->researchersLinkedToStaff],
        ]);
        $this->table(['Faculty', 'New papers'], collect($result->papersByFaculty)
            ->map(fn (int $count, string $slug): array => [$slug !== '' ? $slug : '(no faculty)', $count])->values()->all());

        return self::SUCCESS;
    }
}
