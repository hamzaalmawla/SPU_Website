<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class FacultyImportStudyPlanCommand extends Command
{
    protected $signature = 'faculty:import-study-plan
        {faculty : Public faculty slug, e.g. artificial-intelligence}
        {file : Path to the converted study plan JSON}
        {--user= : Id or email of the CMS user the draft is saved as}
        {--write : Save the result as a CMS draft (without it nothing is stored)}
        {--replace-edited : Also replace a plan that editors have already saved or published (manual use only)}
        {--publish : Publish the saved draft right away (needs --write)}';

    protected $description = 'Load a converted curriculum into a faculty study plan as an unpublished CMS draft.';

    public function __construct(
        private readonly FacultyStudyPlanImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $user = $this->option('user');

        if (! is_string($user) || $user === '') {
            $this->error('Pass the CMS user with --user=<id or email>.');

            return self::INVALID;
        }

        if ($this->option('publish') && ! $this->option('write')) {
            $this->error('--publish needs --write.');

            return self::INVALID;
        }

        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::INVALID;
        }

        try {
            $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $result = $this->importService->import((string) $this->argument('faculty'), is_array($source) ? $source : [], $user, (bool) $this->option('write'), (bool) $this->option('replace-edited'), (bool) $this->option('publish'));
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info('Target: '.$result->targetKey);

        if ($result->skipped) {
            $this->line('SKIPPED: '.$result->skipReason);

            return self::SUCCESS;
        }

        $this->table(['Department', 'Courses'], collect($result->coursesByDepartment)
            ->map(fn (int $count, string $department): array => [$department, (string) $count])
            ->values()
            ->all());

        if (! $result->valid) {
            $this->error('Validation failed, nothing was saved:');

            foreach ($result->errors as $scope => $messages) {
                foreach ($messages as $message) {
                    $this->line(' - ['.$scope.'] '.$message);
                }
            }

            return self::FAILURE;
        }

        $this->line(match (true) {
            $result->published => 'Saved as draft version '.$result->draftVersion.' and published.',
            $result->written => 'Saved as draft version '.$result->draftVersion.'. Review it in the faculty admin page, then publish.',
            default => 'Dry run: the data is valid. Re-run with --write to save it as a draft.',
        });

        return self::SUCCESS;
    }
}
