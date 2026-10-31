<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFacultyResearchImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

final class LegacyImportFacultyResearchCommand extends Command
{
    protected $signature = 'legacy-import:faculty-research
        {source : The exported research data (.json), or the old site SQL dump (.sql) with --export}
        {--export= : Read the SQL dump and write the research data to this JSON path instead of importing}
        {--write : Save the result (without it nothing is stored)}
        {--approve= : Required approval token for --write}';

    protected $description = 'Import the old research portal\'s per-faculty research lists and give every legacy publication its faculty.';

    public function __construct(
        private readonly LegacyFacultyResearchImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $export = $this->option('export');

        try {
            if (is_string($export) && $export !== '') {
                $count = $this->importService->export($source, $export);
                $this->info('Exported '.$count.' faculty research entries to '.$export);

                return self::SUCCESS;
            }

            $result = $this->importService->import(
                $source,
                (bool) $this->option('write'),
                is_string($this->option('approve')) ? $this->option('approve') : null,
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($result->written ? 'Written.' : 'Dry run: nothing was stored. Re-run with --write --approve=<token>.');
        $this->table(['', 'Count'], [
            ['Entries in the faculty lists', $result->scannedEntries],
            ['Visible entries with a title', $result->visibleEntries],
            ['Matched a publication already here (faculty set)', $result->linkedToExisting],
            ['New publications', $result->created],
            ['Updated from an earlier run', $result->updated],
            ['Same paper listed twice (kept once)', $result->duplicatesInLists],
            ['Staff-page publications given their owner\'s faculty', $result->facultyFromOwner],
            ['Without a faculty', $result->withoutFaculty],
            ['Card image paths repaired', $result->imagesRepaired],
            ['Public publications now', $result->publicPublications],
        ]);
        $this->table(['Faculty', 'Visible list entries'], collect($result->entriesByFaculty)
            ->map(fn (int $count, string $slug): array => [$slug !== '' ? $slug : '(students, no faculty)', $count])->values()->all());
        $this->table(['Faculty', 'Public publications'], collect($result->publicPublicationsByFaculty)
            ->map(fn (int $count, string $slug): array => [$slug !== '' ? $slug : '(no faculty)', $count])->values()->all());

        return self::SUCCESS;
    }
}
