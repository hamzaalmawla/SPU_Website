<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Faculty\LegacyCourseSpecificationImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class FacultyImportCourseSpecificationsCommand extends Command
{
    protected $signature = 'faculty:import-course-specifications
        {--file= : Path to the specifications JSON (default: database/seeders/Data/legacy-course-specifications.json)}
        {--user= : Id or email of the CMS user the change is saved as}
        {--write : Save and publish the study plans (without it nothing is stored)}';

    protected $description = 'Attach the old site\'s course specification PDFs to the study plan courses.';

    public function __construct(
        private readonly LegacyCourseSpecificationImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) ($this->option('file') ?: database_path('seeders/Data/legacy-course-specifications.json'));
        $user = $this->option('user');

        if (! is_string($user) || $user === '') {
            $this->error('Pass the CMS user with --user=<id or email>.');

            return self::INVALID;
        }

        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::INVALID;
        }

        try {
            $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $result = $this->importService->import(is_array($source) ? $source : [], $user, (bool) $this->option('write'));
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->table(['Faculty', 'Old course', 'Result', 'Plan courses', 'Spec attached'], array_map(static fn (array $row): array => [
            $row['faculty'],
            $row['title'],
            $row['status'],
            implode(', ', $row['courses']),
            $row['specification'] ? 'yes' : '',
        ], $result->rows));

        $counts = array_count_values(array_column($result->rows, 'status'));
        ksort($counts);
        $this->line('Totals: '.implode(', ', array_map(static fn (string $status, int $count): string => $status.'='.$count, array_keys($counts), $counts)));

        if (! $result->written) {
            $this->line('Dry run: re-run with --write to save and publish.');
        } else {
            $this->line('Published: '.($result->published === [] ? 'none' : implode(', ', $result->published)));

            if ($result->drafted !== []) {
                $this->line('Added to an open editor draft (publish it from the faculty page): '.implode(', ', $result->drafted));
            }
        }

        return in_array('failed', array_column($result->rows, 'status'), true) ? self::FAILURE : self::SUCCESS;
    }
}
