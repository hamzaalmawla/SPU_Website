<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyQuarantineExportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportQuarantineExportCommand extends Command
{
    protected $signature = 'legacy-import:quarantine-export
        {module? : Optional module filter}
        {--reason= : Optional reason_code filter}
        {--format=csv : Export format: csv or json}
        {--disk=local : Storage disk}
        {--dir=legacy-import-exports/quarantine : Export directory}';

    protected $description = 'Export migration_rejections quarantine/review rows for editor review.';

    public function __construct(
        private readonly LegacyQuarantineExportServiceInterface $exportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $module = $this->argument('module');
        $reason = $this->option('reason');

        try {
            $result = $this->exportService->export(
                module: is_string($module) ? $module : null,
                reasonCode: is_string($reason) ? $reason : null,
                format: (string) $this->option('format'),
                disk: (string) $this->option('disk'),
                directory: (string) $this->option('dir'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info('Legacy quarantine review export created.');
        $this->line('Disk: '.$result->disk);
        $this->line('Path: '.$result->path);
        $this->line('Format: '.$result->format);
        $this->line('Rows: '.$result->rowCount);

        return self::SUCCESS;
    }
}
