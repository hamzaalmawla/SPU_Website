<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Faculty\FacultyPositionMatchServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class FacultyMatchPositionsCommand extends Command
{
    protected $signature = 'faculty:match-positions
        {--faculty= : One public faculty slug (every faculty when left out)}
        {--user= : Id or email of the CMS user the council drafts are saved as (needed with --write)}
        {--write : Save the matched positions as unpublished council drafts (without it nothing is stored)}';

    protected $description = 'Match positions typed on faculty profiles to the shared positions list and add them to each faculty\'s council page as a draft.';

    public function __construct(
        private readonly FacultyPositionMatchServiceInterface $matchService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $user = $this->option('user');

        try {
            $faculty = $this->option('faculty');
            $result = $this->matchService->run(is_string($faculty) && $faculty !== '' ? $faculty : null, $write, is_string($user) ? $user : null);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->line($write ? 'Faculty positions (write):' : 'Faculty positions (dry run, nothing saved):');
        $this->section('Added to the council page'.($write ? '' : ' on --write'), ['Faculty', 'Profile', 'Typed', 'Position'], $result->matched);
        $this->section('Already on the council page', ['Faculty', 'Profile', 'Typed', 'Position'], $result->present);
        $this->section('Heads of department (set on the departments page)', ['Faculty', 'Profile', 'Typed'], $result->heads);
        $this->section('Not added', ['Faculty', 'Profile', 'Typed', 'Position', 'Why'], $result->skipped);
        $this->section('No matching position (an editor picks it on the council page)', ['Faculty', 'Profile', 'Typed'], $result->unmatched);

        if ($write) {
            $this->info($result->savedFaculties === []
                ? 'Nothing to save.'
                : 'Saved unpublished council drafts for: '.implode(', ', $result->savedFaculties).'. An editor reviews and publishes each one.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, string>>  $rows
     */
    private function section(string $title, array $headers, array $rows): void
    {
        $this->line(sprintf('%s: %d', $title, count($rows)));

        if ($rows !== []) {
            $this->table($headers, array_map(static fn (array $row): array => array_values($row), $rows));
        }
    }
}
