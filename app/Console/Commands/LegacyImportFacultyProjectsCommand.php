<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Legacy\LegacyFacultyProjectImportServiceInterface;
use App\DTOs\Legacy\LegacyFacultyProjectImportResultDTO;
use Illuminate\Console\Command;

final class LegacyImportFacultyProjectsCommand extends Command
{
    protected $signature = 'legacy-import:faculty-projects
        {dump : Path to the authoritative SQL dump}
        {--write : Persist project records}
        {--approve= : Required approval token for write mode}
        {--batch= : Optional migration batch name}
        {--enable-visible : Enable records marked visible by the legacy parent}
        {--verify-media : Verify project images and PDFs before retaining their legacy paths}
        {--json : Output machine-readable JSON}';

    protected $description = 'Inspect or import faculty student projects from an authoritative legacy SQL dump.';

    public function __construct(
        private readonly LegacyFacultyProjectImportServiceInterface $importService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->importService->import(
            dumpPath: (string) $this->argument('dump'),
            write: (bool) $this->option('write'),
            approval: is_string($this->option('approve')) ? $this->option('approve') : null,
            batch: is_string($this->option('batch')) ? $this->option('batch') : null,
            enableVisible: (bool) $this->option('enable-visible'),
            verifyMedia: (bool) $this->option('verify-media'),
        );
        $payload = $this->payload($result);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Legacy Faculty Project Import');
        $this->line('Written: '.($result->written ? 'yes' : 'no'));
        $this->line('Batch: '.$result->batch);
        $this->line('Visible projects enabled: '.($result->enabledVisibleProjects ? 'yes' : 'no'));
        $this->line('Scanned projects: '.$result->scannedProjects);
        $this->line('Importable projects: '.$result->importableProjects);
        $this->line('Imported projects: '.$result->importedProjects);
        $this->line('Visible / hidden: '.$result->visibleProjects.' / '.$result->hiddenProjects);
        $this->line('Media verified / failed: '.$result->verifiedMedia.' / '.$result->failedMedia);
        $this->table(['Faculty', 'Projects'], collect($result->facultyCounts)->map(fn (int $count, string $faculty): array => [$faculty, $count])->values()->all());

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function payload(LegacyFacultyProjectImportResultDTO $result): array
    {
        return [
            'written' => $result->written,
            'batch' => $result->batch,
            'enabled_visible_projects' => $result->enabledVisibleProjects,
            'scanned_projects' => $result->scannedProjects,
            'importable_projects' => $result->importableProjects,
            'imported_projects' => $result->importedProjects,
            'visible_projects' => $result->visibleProjects,
            'hidden_projects' => $result->hiddenProjects,
            'verified_media' => $result->verifiedMedia,
            'failed_media' => $result->failedMedia,
            'faculty_counts' => $result->facultyCounts,
            'skip_reason_counts' => $result->skipReasonCounts,
        ];
    }
}
