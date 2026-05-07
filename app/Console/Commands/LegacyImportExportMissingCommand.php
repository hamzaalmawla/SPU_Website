<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MigrationLog;
use App\Models\MigrationRejection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LegacyImportExportMissingCommand extends Command
{
    protected $signature = 'legacy-import:export-missing {module?} {--disk=local} {--dir=legacy-import-exports}';

    protected $description = 'Export missing legacy import inventory as JSON and CSV files.';

    public function handle(): int
    {
        $module = $this->argument('module');
        $module = is_string($module) && $module !== '' ? $module : null;
        $disk = (string) $this->option('disk');
        $baseDir = trim((string) $this->option('dir'), '/');
        $exportDir = $baseDir.'/'.now()->format('Ymd_His').($module !== null ? '_'.$module : '');

        $skippedLogs = MigrationLog::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->where('status', 'skipped')
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get(['module', 'batch_name', 'source_table', 'source_id', 'target_table', 'target_id', 'message', 'metadata']);
        $rejections = MigrationRejection::query()
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get(['module', 'source_table', 'source_id', 'reason_code', 'reason_message', 'raw_summary']);
        $snapshots = DB::table('legacy_record_snapshots')
            ->when($module !== null, fn ($query) => $query->where('module', $module))
            ->orderBy('module')
            ->orderBy('source_table')
            ->orderBy('source_id')
            ->get(['module', 'batch_name', 'source_table', 'source_id', 'legacy_key', 'classification', 'locale', 'payload_json', 'payload_text']);
        $translationGaps = $this->translationGaps($module);
        $unresolvedFields = $this->unresolvedFields($module);

        $payload = [
            'summary' => [
                'generated_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'module' => $module,
                'counts' => [
                    'skipped_logs' => $skippedLogs->count(),
                    'rejections' => $rejections->count(),
                    'snapshots' => $snapshots->count(),
                    'translation_gaps' => $translationGaps->count(),
                    'unresolved_fields' => $unresolvedFields->count(),
                ],
                'grouped' => [
                    'skipped_logs' => $skippedLogs->groupBy('module')->map->count()->all(),
                    'rejections' => $rejections->groupBy(fn (MigrationRejection $row): string => $row->module.':'.$row->reason_code)->map->count()->all(),
                    'snapshots' => $snapshots->groupBy(fn (object $row): string => $row->module.':'.$row->classification)->map->count()->all(),
                    'translation_gaps' => $translationGaps->groupBy(fn (object $row): string => $row->module.':'.$row->gap_type)->map->count()->all(),
                    'unresolved_fields' => $unresolvedFields->groupBy(fn (object $row): string => $row->module.':'.$row->issue_type)->map->count()->all(),
                ],
            ],
            'skipped_logs' => $skippedLogs->map(fn (MigrationLog $row): array => $row->only(['module', 'batch_name', 'source_table', 'source_id', 'target_table', 'target_id', 'message', 'metadata']))->all(),
            'rejections' => $rejections->map(fn (MigrationRejection $row): array => $row->only(['module', 'source_table', 'source_id', 'reason_code', 'reason_message', 'raw_summary']))->all(),
            'snapshots' => $snapshots->map(fn (object $row): array => (array) $row)->all(),
            'translation_gaps' => $translationGaps->map(fn (object $row): array => (array) $row)->values()->all(),
            'unresolved_fields' => $unresolvedFields->map(fn (object $row): array => (array) $row)->values()->all(),
        ];

        Storage::disk($disk)->put(
            $exportDir.'/missing_inventory.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        foreach (['skipped_logs', 'rejections', 'snapshots', 'translation_gaps', 'unresolved_fields'] as $key) {
            $this->writeCsv($disk, $exportDir.'/'.$key.'.csv', $payload[$key]);
        }

        $this->info('Missing data inventory exported.');
        $this->line('Disk: '.$disk);
        $this->line('Directory: storage/app/'.$exportDir);

        return self::SUCCESS;
    }

    private function translationGaps(?string $module): mixed
    {
        $translationGaps = collect();

        if ($module === null || $module === 'alumni') {
            $translationGaps = $translationGaps->concat(DB::table('alumni as entity')
                ->leftJoin('alumni_translations as ar', fn ($join) => $join->on('ar.alumni_id', '=', 'entity.id')->where('ar.locale', '=', 'ar'))
                ->leftJoin('alumni_translations as en', fn ($join) => $join->on('en.alumni_id', '=', 'entity.id')->where('en.locale', '=', 'en'))
                ->whereNull('en.id')
                ->orderBy('entity.id')
                ->get([
                    DB::raw("'alumni' as module"),
                    DB::raw("'missing_en_translation' as gap_type"),
                    DB::raw("'alumni' as target_table"),
                    'entity.id as target_id',
                    'entity.faculty_id',
                    'entity.graduation_year as reference_value',
                    'ar.full_name as ar_label',
                ]));
        }

        if ($module === null || $module === 'honor_students') {
            $translationGaps = $translationGaps->concat(DB::table('honor_students as entity')
                ->leftJoin('honor_student_translations as ar', fn ($join) => $join->on('ar.honor_student_id', '=', 'entity.id')->where('ar.locale', '=', 'ar'))
                ->leftJoin('honor_student_translations as en', fn ($join) => $join->on('en.honor_student_id', '=', 'entity.id')->where('en.locale', '=', 'en'))
                ->whereNull('en.id')
                ->orderBy('entity.id')
                ->get([
                    DB::raw("'honor_students' as module"),
                    DB::raw("'missing_en_translation' as gap_type"),
                    DB::raw("'honor_students' as target_table"),
                    'entity.id as target_id',
                    'entity.faculty_id',
                    'entity.academic_year as reference_value',
                    'ar.full_name as ar_label',
                ]));
        }

        return $translationGaps;
    }

    private function unresolvedFields(?string $module): mixed
    {
        $unresolvedFields = collect();

        if ($module === null || $module === 'faculty_members') {
            $unresolvedFields = $unresolvedFields->concat(DB::table('faculty_members as entity')
                ->leftJoin('faculty_member_translations as ar', fn ($join) => $join->on('ar.faculty_member_id', '=', 'entity.id')->where('ar.locale', '=', 'ar'))
                ->whereNull('entity.faculty_id')
                ->orderBy('entity.id')
                ->get([
                    DB::raw("'faculty_members' as module"),
                    DB::raw("'null_faculty_id' as issue_type"),
                    DB::raw("'faculty_members' as target_table"),
                    'entity.id as target_id',
                    'ar.full_name as label',
                    DB::raw('NULL as details'),
                ]));
        }

        if ($module === null || $module === 'research') {
            foreach (['faculty_member_id' => 'null_faculty_member_id', 'category_key' => 'null_category_key'] as $column => $issueType) {
                $unresolvedFields = $unresolvedFields->concat(DB::table('research_publications as entity')
                    ->leftJoin('research_publication_translations as ar', fn ($join) => $join->on('ar.research_publication_id', '=', 'entity.id')->where('ar.locale', '=', 'ar'))
                    ->whereNull('entity.'.$column)
                    ->orderBy('entity.id')
                    ->get([
                        DB::raw("'research' as module"),
                        DB::raw("'{$issueType}' as issue_type"),
                        DB::raw("'research_publications' as target_table"),
                        'entity.id as target_id',
                        'ar.title as label',
                        DB::raw('NULL as details'),
                    ]));
            }
        }

        return $unresolvedFields;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeCsv(string $disk, string $path, array $rows): void
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to create CSV stream.');
        }

        $headers = $rows !== [] ? array_keys($rows[0]) : [];

        if ($headers !== []) {
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (mixed $value): string => is_array($value) || is_object($value)
                        ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : (string) ($value ?? ''),
                    $row,
                ));
            }
        }

        rewind($handle);
        Storage::disk($disk)->put($path, stream_get_contents($handle) ?: '');
        fclose($handle);
    }
}
