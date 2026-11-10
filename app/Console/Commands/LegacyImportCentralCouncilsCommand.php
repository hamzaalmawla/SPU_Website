<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyCentralCouncilImportServiceInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class LegacyImportCentralCouncilsCommand extends Command
{
    protected $signature = 'legacy-import:central-councils
        {input? : Privately approved service 1 or 2 council packet CSV}
        {--disk=local : Storage disk containing the packet}
        {--write : Persist eligible rows as disabled council review data}
        {--approve= : Required write approval token}
        {--batch= : Optional migration batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Import approved jx_councils service 1 and 2 rows as disabled central council members.';

    public function __construct(private readonly LegacyCentralCouncilImportServiceInterface $service)
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

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Approved Legacy Central Council Import');
        $this->line('Mode: '.($result->written ? 'write' : 'dry-run'));
        $this->line('Scanned/importable/imported/skipped: '.$result->scanned.'/'.$result->importable.'/'.$result->imported.'/'.$result->skipped);
        $this->line('Councils/members/translations created: '.$result->councilsCreated.'/'.$result->membersCreated.'/'.$result->translationsCreated);
        foreach ($result->reasonCounts as $reason => $count) {
            $this->line($reason.': '.$count);
        }

        return self::SUCCESS;
    }
}
