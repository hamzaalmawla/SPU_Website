<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Faculty\FacultyStudyPlanUniversityElectivesServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class FacultyUniversityElectiveBoxesCommand extends Command
{
    protected $signature = 'faculty:university-elective-boxes
        {--faculty= : One public faculty slug (every faculty when left out)}
        {--user= : Id or email of the CMS user the study plan drafts are saved as (needed with --write)}
        {--write : Save the changed plans as drafts (without it nothing is stored)}
        {--publish : With --write, also publish each saved draft, unless an editor already had one open or it fails validation}';

    protected $description = 'Remove the university electives listed in each faculty study plan and give every program three "University elective course" boxes that open the Requirements Unit\'s elective list.';

    public function __construct(
        private readonly FacultyStudyPlanUniversityElectivesServiceInterface $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $publish = (bool) $this->option('publish');
        $user = $this->option('user');

        if ($publish && ! $write) {
            $this->error('--publish needs --write.');

            return self::INVALID;
        }

        try {
            $faculty = $this->option('faculty');
            $result = $this->service->run(is_string($faculty) && $faculty !== '' ? $faculty : null, $write, is_string($user) ? $user : null, $publish);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->line($write ? 'University elective boxes (write):' : 'University elective boxes (dry run, nothing saved):');
        $this->line(sprintf('Programs that change: %d', count($result->changes)));

        if ($result->changes !== []) {
            $this->table(['Faculty', 'Program', 'Electives removed', 'Boxes added', 'Boxes kept'], array_map(static fn (array $row): array => array_values($row), $result->changes));
        }

        $this->line('Already done: '.($result->unchanged === [] ? 'none' : implode(', ', $result->unchanged)).'.');

        if ($write) {
            $this->info($result->savedFaculties === [] ? 'Nothing to save.' : 'Saved study plan drafts for: '.implode(', ', $result->savedFaculties).'.');
        }

        if ($publish) {
            $this->info('Published: '.($result->publishedFaculties === [] ? 'none' : implode(', ', $result->publishedFaculties)).'.');
        }

        foreach ($result->notPublished as $faculty => $reason) {
            $this->warn(sprintf('Left as a draft: %s (%s)', $faculty, $reason));
        }

        return self::SUCCESS;
    }
}
