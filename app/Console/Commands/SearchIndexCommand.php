<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Search\SearchIndexServiceInterface;
use Illuminate\Console\Command;

/**
 * Rebuild the derived public-search index.
 *
 * Safe to run at any time and safe to run repeatedly: the rebuild upserts every
 * public record and then drops the index rows it did not touch, so re-running it
 * converges on the same result whether the index was empty, stale, or current.
 */
final class SearchIndexCommand extends Command
{
    protected $signature = 'search:index
        {--source= : Rebuild a single source only (news, research, pages, faculty-members, persons, faculties, faculty-pages)}
        {--fresh : Delete each source\'s existing documents before rebuilding}';

    protected $description = 'Rebuild the public site-search index from published content';

    public function __construct(
        private readonly SearchIndexServiceInterface $searchIndexService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->searchIndexService->isAvailable()) {
            $this->error('The search_documents table does not exist. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $source = $this->option('source');
        $source = is_string($source) && $source !== '' ? $source : null;

        if ($source !== null && ! in_array($source, SearchIndexServiceInterface::SOURCES, true)) {
            $this->error('Unknown source "'.$source.'". Expected one of: '.implode(', ', SearchIndexServiceInterface::SOURCES).'.');

            return self::FAILURE;
        }

        $this->info($source === null ? 'Rebuilding the site-search index...' : 'Rebuilding the site-search index for "'.$source.'"...');

        $written = $this->searchIndexService->rebuild($source, (bool) $this->option('fresh'));

        $this->info('Indexed '.$written.' document'.($written === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
