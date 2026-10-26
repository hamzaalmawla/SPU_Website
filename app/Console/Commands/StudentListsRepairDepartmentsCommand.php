<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Career\StudentListImportServiceInterface;
use Illuminate\Console\Command;

final class StudentListsRepairDepartmentsCommand extends Command
{
    protected $signature = 'student-lists:repair-departments
        {--write : Write the changes (without it nothing is changed)}';

    protected $description = 'Clear the department of imported students whose decision printed the faculty\'s own degree, not a department.';

    public function __construct(
        private readonly StudentListImportServiceInterface $imports,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $lines = $this->imports->repairFacultyWideDepartments($write);

        $this->line($write ? 'Repaired.' : 'Dry run: nothing was changed. Re-run with --write.');
        foreach ($lines as $line) {
            $this->line('  '.$line);
        }
        if ($lines === []) {
            $this->line('  Nothing to repair.');
        }

        return self::SUCCESS;
    }
}
