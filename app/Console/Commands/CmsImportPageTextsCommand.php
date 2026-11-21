<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class CmsImportPageTextsCommand extends Command
{
    protected $signature = 'cms:import-page-texts
        {file : JSON file of page texts per CMS target}
        {--user= : Id or email of the CMS user the pages are published as}
        {--only= : Only this target key}
        {--write : Save and publish (without it nothing is stored)}
        {--over-drafts : Also replace pages that have an editor\'s unpublished draft}';

    protected $description = 'Publish reviewed page texts (the old site\'s content in the new page structures) through the CMS workflow.';

    public function __construct(
        private readonly CmsPageTextImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $user = $this->option('user');
        if (! is_string($user) || $user === '') {
            $this->error('Pass the CMS user with --user=<id or email>.');

            return self::INVALID;
        }

        try {
            $results = $this->importService->import(
                (string) $this->argument('file'),
                $user,
                (bool) $this->option('write'),
                (bool) $this->option('over-drafts'),
                is_string($this->option('only')) && $this->option('only') !== '' ? $this->option('only') : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Page', 'Result'], array_map(static fn (array $row): array => [$row['target'], $row['result']], $results));

        return collect($results)->contains(static fn (array $row): bool => str_starts_with($row['result'], 'FAILED')) ? self::FAILURE : self::SUCCESS;
    }
}
