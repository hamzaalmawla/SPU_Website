<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Faculty\FacultyStructureImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class FacultyImportStructureCommand extends Command
{
    protected $signature = 'faculty:import-structure
        {file : Path to the faculty structure JSON (database/seeders/Data/legacy/faculty_structure.json)}
        {--write : Store the changes (without it nothing is stored)}';

    protected $description = 'Replace the faculties\' departments and labs with the old site\'s real ones and add the subpages it lists.';

    public function __construct(
        private readonly FacultyStructureImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::INVALID;
        }

        try {
            $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $result = $this->importService->import(is_array($source) ? $source : [], (bool) $this->option('write'));
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->table(
            ['Faculty', 'Departments', 'New', 'Switched off', 'Labs', 'New', 'Switched off', 'Labs hidden', 'Pages'],
            collect($result->faculties)->map(fn (array $row, string $faculty): array => [
                $faculty,
                (string) $row['departments'],
                (string) $row['departmentsCreated'],
                (string) $row['departmentsDisabled'],
                $row['labs'] === null ? 'unchanged' : (string) $row['labs'],
                (string) $row['labsCreated'],
                (string) $row['labsDisabled'],
                $row['labsCardHidden'] ? 'yes' : 'no',
                implode(', ', $row['pages']),
            ])->values()->all(),
        );

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        $this->info($result->written ? 'Saved.' : 'Dry run: nothing was saved. Add --write to store it.');

        return self::SUCCESS;
    }
}
