<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\DTOs\Legacy\LegacyNewsImportResultDTO;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportNewsCommand extends Command
{
    protected $signature = 'legacy-import:news
        {input : Approved category review packet CSV}
        {--disk=local : Storage disk containing the approved packet}
        {--write : Persist approved candidates as disabled drafts}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import only explicitly approved legacy news packet rows into quarantine.';

    public function __construct(private readonly LegacyNewsImportServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->service->import(
                write: (bool) $this->option('write'),
                approval: is_string($this->option('approve')) ? $this->option('approve') : null,
                batch: is_string($this->option('batch')) ? $this->option('batch') : null,
                input: (string) $this->argument('input'),
                disk: (string) $this->option('disk'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $payload = $this->payload($result);
        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Approved Legacy News Import');
        $this->line('Mode: '.($result->written ? 'write' : 'dry-run'));
        $this->line('Scanned/importable/imported/skipped: '.$result->scannedRows.'/'.$result->importableRows.'/'.$result->importedRows.'/'.$result->skippedRows);
        foreach ($result->skipReasonCounts as $reason => $count) {
            $this->line($reason.': '.$count);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function payload(LegacyNewsImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'scanned_rows' => $result->scannedRows,
            'importable_rows' => $result->importableRows,
            'imported_rows' => $result->importedRows,
            'created_translations' => $result->createdTranslations,
            'created_attachments' => $result->createdAttachments,
            'skipped_rows' => $result->skippedRows,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
