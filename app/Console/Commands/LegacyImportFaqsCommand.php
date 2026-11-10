<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFaqImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportFaqsCommand extends Command
{
    protected $signature = 'legacy-import:faqs
        {input? : Privately approved FAQ candidate CSV}
        {--disk=local : Storage disk containing the packet}
        {--write : Persist eligible rows as disabled archival review data}
        {--approve= : Required write approval token}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import explicitly approved jx_faqs rows as disabled archival review data.';

    public function __construct(private readonly LegacyFaqImportServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->import(
                is_string($this->argument('input')) ? $this->argument('input') : null,
                (string) $this->option('disk'), (bool) $this->option('write'),
                is_string($this->option('approve')) ? $this->option('approve') : null,
                is_string($this->option('batch')) ? $this->option('batch') : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Approved Legacy FAQ Import');
            $this->line('Scanned/importable/imported/skipped: '.$result->scannedRows.'/'.$result->importableRows.'/'.$result->importedRows.'/'.$result->skippedRows);
        }

        return self::SUCCESS;
    }
}
