<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use Illuminate\Console\Command;

final class LegacyPublishStudentProfilesCommand extends Command
{
    protected $signature = 'legacy-import:publish-student-profiles
        {lane : alumni or honor_students}
        {--write : Enable eligible imported records}
        {--approve= : Required lane publication token}
        {--batch= : Optional publication batch name}
        {--json : Output machine-readable JSON}';

    protected $description = 'Publish provenance-backed visible legacy student profiles.';

    public function __construct(private readonly LegacyStudentProfileImportServiceInterface $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $lane = $this->argument('lane');
        $result = $this->service->publishImported(
            lane: is_string($lane) ? $lane : '',
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? (string) $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? (string) $this->option('batch') : null,
        );
        $payload = [
            'lane' => $result->lane, 'written' => $result->written, 'batch' => $result->batch,
            'imported_mappings' => $result->importedMappings, 'visible_source_rows' => $result->visibleSourceRows,
            'eligible_rows' => $result->eligibleRows, 'enabled_rows' => $result->enabledRows,
            'already_enabled_rows' => $result->alreadyEnabledRows, 'blocked_rows' => $result->blockedRows,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Student Profile Publication');
        foreach ($payload as $key => $value) {
            $this->line(str_replace('_', ' ', ucfirst($key)).': '.(is_bool($value) ? ($value ? 'yes' : 'no') : $value));
        }

        return self::SUCCESS;
    }
}
