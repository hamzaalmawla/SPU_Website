<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Page\AdmissionsTuitionFeeImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

final class AdmissionsImportTuitionFeesCommand extends Command
{
    protected $signature = 'admissions:import-tuition-fees
        {file : Path to the approved fee table JSON}
        {--user= : Id or email of the CMS user the revision is published as}
        {--publish : Publish the fees through the CMS workflow (without it nothing is stored)}';

    protected $description = 'Publish an approved per-faculty fee table on the Admissions tuition page.';

    public function __construct(
        private readonly AdmissionsTuitionFeeImportServiceInterface $importService,
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

        if (! is_file($path)) {
            $this->error('File not found: '.$path);

            return self::INVALID;
        }

        try {
            $source = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $result = $this->importService->import(is_array($source) ? $source : [], $user, (bool) $this->option('publish'));
        } catch (JsonException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->table(['Faculty', 'Tracks (min. average)', 'Credit hours', 'Syrian fee', 'Arab/foreign fee'], array_map(
            static fn (array $row): array => array_values($row),
            $result->rows,
        ));

        if (! $result->valid) {
            $this->error('Validation failed, nothing was saved:');

            foreach ($result->errors as $locale => $messages) {
                foreach ($messages as $message) {
                    $this->line(' - ['.$locale.'] '.$message);
                }
            }

            return self::FAILURE;
        }

        if ($result->unchanged) {
            $this->line('UNCHANGED: the published tuition page already shows exactly these fees.');

            return self::SUCCESS;
        }

        if ($result->skipReason !== null) {
            $this->line('SKIPPED: '.$result->skipReason);

            return self::SUCCESS;
        }

        if ($result->published) {
            $this->info('Published the tuition page with '.count($result->rows).' fee row(s).');

            return self::SUCCESS;
        }

        if ($this->option('publish')) {
            $this->error('The draft was saved but could not be published.');

            return self::FAILURE;
        }

        $this->line('Dry run: the fees are valid. Re-run with --publish to publish them.');

        return self::SUCCESS;
    }
}
