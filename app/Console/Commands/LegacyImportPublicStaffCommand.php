<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyPublicStaffImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportPublicStaffCommand extends Command
{
    protected $signature = 'legacy-import:public-staff
        {input? : Privately approved public staff packet CSV}
        {--disk=local : Storage disk containing the packet}
        {--write : Persist eligible rows as disabled drafts}
        {--approve= : Required write approval token}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import explicitly approved jx_councils staff rows as disabled faculty member drafts.';

    public function __construct(private readonly LegacyPublicStaffImportServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->import(
                input: is_string($this->argument('input')) ? $this->argument('input') : null,
                disk: (string) $this->option('disk'),
                write: (bool) $this->option('write'),
                approval: is_string($this->option('approve')) ? $this->option('approve') : null,
                batch: is_string($this->option('batch')) ? $this->option('batch') : null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = get_object_vars($result);
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Approved Legacy Public Staff Import');
        $this->line('Mode: '.($result->written ? 'write' : 'dry-run'));
        $this->line('Scanned/importable/imported/skipped: '.$result->scannedRows.'/'.$result->importableRows.'/'.$result->importedRows.'/'.$result->skippedRows);
        foreach ($result->skipReasonCounts as $reason => $count) {
            $this->line($reason.': '.$count);
        }

        return self::SUCCESS;
    }
}
