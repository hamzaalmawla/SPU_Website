<?php

use App\Models\MigrationLog;
use App\Models\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy-import:report {module?} {--details}', function (?string $module = null) {
    $logs = MigrationLog::query();
    $rejections = MigrationRejection::query();

    if (is_string($module) && $module !== '') {
        $logs->where('module', $module);
        $rejections->where('module', $module);
    }

    $this->info('Legacy Import Report'.($module !== null ? ' ['.$module.']' : ''));
    $this->newLine();

    $summary = (clone $logs)
        ->select('module', 'status', DB::raw('count(*) as total'))
        ->groupBy('module', 'status')
        ->orderBy('module')
        ->orderBy('status')
        ->get()
        ->map(fn (MigrationLog $log): array => [
            'module' => $log->module,
            'status' => $log->status,
            'total' => $log->total,
        ])
        ->all();

    if ($summary === []) {
        $this->warn('No migration logs found.');
    } else {
        $this->table(['Module', 'Status', 'Total'], $summary);
    }

    $rejectionSummary = (clone $rejections)
        ->select('module', 'reason_code', DB::raw('count(*) as total'))
        ->groupBy('module', 'reason_code')
        ->orderBy('module')
        ->orderBy('reason_code')
        ->get()
        ->map(fn (MigrationRejection $rejection): array => [
            'module' => $rejection->module,
            'reason_code' => $rejection->reason_code,
            'total' => $rejection->total,
        ])
        ->all();

    $this->newLine();
    $this->info('Rejections');

    if ($rejectionSummary === []) {
        $this->warn('No rejections recorded.');
    } else {
        $this->table(['Module', 'Reason', 'Total'], $rejectionSummary);
    }

    if ($this->option('details')) {
        $recentLogs = (clone $logs)->latest('id')->limit(10)->get(['module', 'source_table', 'source_id', 'target_table', 'target_id', 'status', 'message']);
        $recentRejections = (clone $rejections)->latest('id')->limit(10)->get(['module', 'source_table', 'source_id', 'reason_code', 'reason_message']);

        $this->newLine();
        $this->info('Recent Logs');
        $this->table(['Module', 'Source', 'Source ID', 'Target', 'Target ID', 'Status', 'Message'], $recentLogs->map(fn (MigrationLog $log): array => [
            $log->module,
            $log->source_table,
            (string) $log->source_id,
            $log->target_table,
            (string) $log->target_id,
            $log->status,
            $log->message,
        ])->all());

        $this->newLine();
        $this->info('Recent Rejections');
        $this->table(['Module', 'Source', 'Source ID', 'Reason', 'Message'], $recentRejections->map(fn (MigrationRejection $rejection): array => [
            $rejection->module,
            $rejection->source_table,
            (string) $rejection->source_id,
            $rejection->reason_code,
            $rejection->reason_message,
        ])->all());
    }
})->purpose('Summarize legacy import logs and rejections');

Artisan::command('legacy-import:verify {module?}', function (?string $module = null) {
    $logs = MigrationLog::query()->where('status', 'success');

    if (is_string($module) && $module !== '') {
        $logs->where('module', $module);
    }

    $summary = $logs
        ->select('module', 'target_table', DB::raw('count(distinct source_table, source_id) as total'))
        ->groupBy('module', 'target_table')
        ->orderBy('module')
        ->orderBy('target_table')
        ->get()
        ->map(fn (MigrationLog $log): array => [
            'module' => $log->module,
            'target_table' => $log->target_table,
            'total' => $log->total,
        ])
        ->all();

    $this->info('Legacy Import Verification'.($module !== null ? ' ['.$module.']' : ''));

    if ($summary === []) {
        $this->warn('No successful imports found to verify.');

        return;
    }

    $this->table(['Module', 'Target Table', 'Imported Rows'], $summary);
})->purpose('Verify successful legacy imports by module and target table');

Artisan::command('legacy-import:audit {module?} {--details}', function (?string $module = null) {
    $moduleSourceTables = [
        'countries' => ['jx_countries'],
        'cities' => ['jx_cities'],
        'faculties' => ['jx_member_categories'],
        'faculty_members' => ['jx_members', 'jx_councils1'],
        'councils' => ['legacy_council_taxonomy', 'jx_councils1'],
        'research' => ['jx_member_categories', 'jx_member_items'],
        'faqs' => ['jx_faqs'],
        'complaints' => ['jx_complaint_cats', 'jx_complaints'],
        'career_links' => ['jx_job_sites'],
        'links' => ['jx_docs', 'jx_sites'],
        'alumni' => ['jx_graduated_students'],
        'honor_students' => ['jx_good_students'],
    ];

    $moduleTargetTables = [
        'countries' => ['countries', 'country_translations'],
        'cities' => ['cities', 'city_translations'],
        'faculties' => ['faculties', 'faculty_translations'],
        'faculty_members' => ['faculty_members', 'faculty_member_translations'],
        'councils' => ['councils', 'council_translations', 'council_members', 'council_member_translations'],
        'research' => ['research_publications', 'research_publication_translations', 'research_files'],
        'faqs' => ['faq_categories', 'faq_category_translations', 'faqs', 'faq_translations'],
        'complaints' => ['complaint_categories', 'complaint_category_translations', 'complaints'],
        'career_links' => ['career_links', 'career_link_translations'],
        'links' => ['menu_items', 'media_assets'],
        'alumni' => ['alumni', 'alumni_translations'],
        'honor_students' => ['honor_students', 'honor_student_translations'],
    ];

    $logs = MigrationLog::query();
    $rejections = MigrationRejection::query();
    $snapshots = DB::table('legacy_record_snapshots');

    if (is_string($module) && $module !== '') {
        $logs->where('module', $module);
        $rejections->where('module', $module);
        $snapshots->where('module', $module);
    }

    $this->info('Legacy Import Audit'.($module !== null ? ' ['.$module.']' : ''));
    $this->newLine();

    $sourceSummary = [];

    try {
        /** @var OldDatabaseConnection $legacy */
        $legacy = app(OldDatabaseConnection::class);
        $legacy->connection();

        $sourceModules = $module !== null ? [$module => ($moduleSourceTables[$module] ?? [])] : $moduleSourceTables;

        foreach ($sourceModules as $sourceModule => $tables) {
            foreach ($tables as $table) {
                if ($table === 'legacy_council_taxonomy') {
                    $sourceSummary[] = [
                        'module' => $sourceModule,
                        'source_table' => $table,
                        'source_rows' => 7,
                    ];

                    continue;
                }

                $sourceSummary[] = [
                    'module' => $sourceModule,
                    'source_table' => $table,
                    'source_rows' => $legacy->table($table)->count(),
                ];
            }
        }
    } catch (Throwable $e) {
        $sourceSummary[] = [
            'module' => $module ?? '*',
            'source_table' => 'legacy-connection',
            'source_rows' => 'unavailable: '.$e->getMessage(),
        ];
    }

    $this->info('Source Rows');
    $this->table(['Module', 'Source Table', 'Rows'], $sourceSummary);

    $this->newLine();
    $this->info('Log Summary');

    $logSummary = (clone $logs)
        ->select('module', 'status', DB::raw('count(*) as total'))
        ->groupBy('module', 'status')
        ->orderBy('module')
        ->orderBy('status')
        ->get()
        ->map(fn (MigrationLog $log): array => [
            'module' => $log->module,
            'status' => $log->status,
            'total' => $log->total,
        ])
        ->all();

    $this->table(['Module', 'Status', 'Total'], $logSummary);

    $this->newLine();
    $this->info('Rejections');

    $rejectionSummary = (clone $rejections)
        ->select('module', 'reason_code', DB::raw('count(*) as total'))
        ->groupBy('module', 'reason_code')
        ->orderBy('module')
        ->orderBy('reason_code')
        ->get()
        ->map(fn (MigrationRejection $rejection): array => [
            'module' => $rejection->module,
            'reason_code' => $rejection->reason_code,
            'total' => $rejection->total,
        ])
        ->all();

    if ($rejectionSummary === []) {
        $this->warn('No rejections recorded.');
    } else {
        $this->table(['Module', 'Reason', 'Total'], $rejectionSummary);
    }

    $this->newLine();
    $this->info('Snapshots');

    $snapshotSummary = (clone $snapshots)
        ->select('module', 'classification', DB::raw('count(*) as total'))
        ->groupBy('module', 'classification')
        ->orderBy('module')
        ->orderBy('classification')
        ->get()
        ->map(fn (object $snapshot): array => [
            'module' => $snapshot->module,
            'classification' => $snapshot->classification,
            'total' => $snapshot->total,
        ])
        ->all();

    if ($snapshotSummary === []) {
        $this->warn('No legacy snapshots recorded.');
    } else {
        $this->table(['Module', 'Classification', 'Total'], $snapshotSummary);
    }

    $this->newLine();
    $this->info('Target Tables');

    $targetSummary = [];
    $targetModules = $module !== null ? [$module => ($moduleTargetTables[$module] ?? [])] : $moduleTargetTables;

    foreach ($targetModules as $targetModule => $tables) {
        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $targetSummary[] = [
                'module' => $targetModule,
                'target_table' => $table,
                'rows' => DB::table($table)->count(),
            ];
        }
    }

    $this->table(['Module', 'Target Table', 'Rows'], $targetSummary);

    if ($this->option('details')) {
        $this->newLine();
        $this->info('Recent Skips');

        $recentSkips = (clone $logs)
            ->where('status', 'skipped')
            ->latest('id')
            ->limit(20)
            ->get(['module', 'source_table', 'source_id', 'target_table', 'message']);

        $this->table(['Module', 'Source', 'Source ID', 'Target', 'Message'], $recentSkips->map(fn (MigrationLog $log): array => [
            $log->module,
            $log->source_table,
            (string) $log->source_id,
            $log->target_table,
            $log->message,
        ])->all());

        $this->newLine();
        $this->info('Recent Rejections');

        $recentRejections = (clone $rejections)
            ->latest('id')
            ->limit(20)
            ->get(['module', 'source_table', 'source_id', 'reason_code', 'reason_message']);

        $this->table(['Module', 'Source', 'Source ID', 'Reason', 'Message'], $recentRejections->map(fn (MigrationRejection $rejection): array => [
            $rejection->module,
            $rejection->source_table,
            (string) $rejection->source_id,
            $rejection->reason_code,
            $rejection->reason_message,
        ])->all());

        $this->newLine();
        $this->info('Recent Snapshots');

        $recentSnapshots = (clone $snapshots)
            ->latest('id')
            ->limit(20)
            ->get(['module', 'source_table', 'source_id', 'classification', 'locale']);

        $this->table(['Module', 'Source', 'Source ID', 'Classification', 'Locale'], $recentSnapshots->map(fn (object $snapshot): array => [
            $snapshot->module,
            $snapshot->source_table,
            (string) $snapshot->source_id,
            (string) $snapshot->classification,
            (string) $snapshot->locale,
        ])->all());
    }
})->purpose('Audit legacy import source totals, target totals, logs, rejections, and snapshots');

Artisan::command('legacy-import:export-missing {module?} {--disk=local} {--dir=legacy-import-exports}', function (?string $module = null) {
    $disk = (string) $this->option('disk');
    $baseDir = trim((string) $this->option('dir'), '/');
    $timestamp = now()->format('Ymd_His');
    $exportDir = $baseDir.'/'.$timestamp.($module !== null ? '_'.$module : '');

    $skippedLogs = MigrationLog::query()
        ->when(is_string($module) && $module !== '', fn ($query) => $query->where('module', $module))
        ->where('status', 'skipped')
        ->orderBy('module')
        ->orderBy('source_table')
        ->orderBy('source_id')
        ->get(['module', 'batch_name', 'source_table', 'source_id', 'target_table', 'target_id', 'message', 'metadata']);

    $rejections = MigrationRejection::query()
        ->when(is_string($module) && $module !== '', fn ($query) => $query->where('module', $module))
        ->orderBy('module')
        ->orderBy('source_table')
        ->orderBy('source_id')
        ->get(['module', 'source_table', 'source_id', 'reason_code', 'reason_message', 'raw_summary']);

    $snapshots = DB::table('legacy_record_snapshots')
        ->when(is_string($module) && $module !== '', fn ($query) => $query->where('module', $module))
        ->orderBy('module')
        ->orderBy('source_table')
        ->orderBy('source_id')
        ->get(['module', 'batch_name', 'source_table', 'source_id', 'legacy_key', 'classification', 'locale', 'payload_json', 'payload_text']);

    $translationGaps = collect();

    if ($module === null || $module === 'alumni') {
        $translationGaps = $translationGaps->concat(
            DB::table('alumni as entity')
                ->leftJoin('alumni_translations as ar', function ($join): void {
                    $join->on('ar.alumni_id', '=', 'entity.id')->where('ar.locale', '=', 'ar');
                })
                ->leftJoin('alumni_translations as en', function ($join): void {
                    $join->on('en.alumni_id', '=', 'entity.id')->where('en.locale', '=', 'en');
                })
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
                ])
        );
    }

    if ($module === null || $module === 'honor_students') {
        $translationGaps = $translationGaps->concat(
            DB::table('honor_students as entity')
                ->leftJoin('honor_student_translations as ar', function ($join): void {
                    $join->on('ar.honor_student_id', '=', 'entity.id')->where('ar.locale', '=', 'ar');
                })
                ->leftJoin('honor_student_translations as en', function ($join): void {
                    $join->on('en.honor_student_id', '=', 'entity.id')->where('en.locale', '=', 'en');
                })
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
                ])
        );
    }

    $unresolvedFields = collect();

    if ($module === null || $module === 'faculty_members') {
        $unresolvedFields = $unresolvedFields->concat(
            DB::table('faculty_members as entity')
                ->leftJoin('faculty_member_translations as ar', function ($join): void {
                    $join->on('ar.faculty_member_id', '=', 'entity.id')->where('ar.locale', '=', 'ar');
                })
                ->whereNull('entity.faculty_id')
                ->orderBy('entity.id')
                ->get([
                    DB::raw("'faculty_members' as module"),
                    DB::raw("'null_faculty_id' as issue_type"),
                    DB::raw("'faculty_members' as target_table"),
                    'entity.id as target_id',
                    'ar.full_name as label',
                    DB::raw('NULL as details'),
                ])
        );
    }

    if ($module === null || $module === 'research') {
        $unresolvedFields = $unresolvedFields->concat(
            DB::table('research_publications as entity')
                ->leftJoin('research_publication_translations as ar', function ($join): void {
                    $join->on('ar.research_publication_id', '=', 'entity.id')->where('ar.locale', '=', 'ar');
                })
                ->whereNull('entity.faculty_member_id')
                ->orderBy('entity.id')
                ->get([
                    DB::raw("'research' as module"),
                    DB::raw("'null_faculty_member_id' as issue_type"),
                    DB::raw("'research_publications' as target_table"),
                    'entity.id as target_id',
                    'ar.title as label',
                    DB::raw('NULL as details'),
                ])
        );

        $unresolvedFields = $unresolvedFields->concat(
            DB::table('research_publications as entity')
                ->leftJoin('research_publication_translations as ar', function ($join): void {
                    $join->on('ar.research_publication_id', '=', 'entity.id')->where('ar.locale', '=', 'ar');
                })
                ->whereNull('entity.category_key')
                ->orderBy('entity.id')
                ->get([
                    DB::raw("'research' as module"),
                    DB::raw("'null_category_key' as issue_type"),
                    DB::raw("'research_publications' as target_table"),
                    'entity.id as target_id',
                    'ar.title as label',
                    DB::raw('NULL as details'),
                ])
        );
    }

    $summary = [
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
    ];

    $payload = [
        'summary' => $summary,
        'skipped_logs' => $skippedLogs->map(fn (MigrationLog $row): array => [
            'module' => $row->module,
            'batch_name' => $row->batch_name,
            'source_table' => $row->source_table,
            'source_id' => $row->source_id,
            'target_table' => $row->target_table,
            'target_id' => $row->target_id,
            'message' => $row->message,
            'metadata' => $row->metadata,
        ])->all(),
        'rejections' => $rejections->map(fn (MigrationRejection $row): array => [
            'module' => $row->module,
            'source_table' => $row->source_table,
            'source_id' => $row->source_id,
            'reason_code' => $row->reason_code,
            'reason_message' => $row->reason_message,
            'raw_summary' => $row->raw_summary,
        ])->all(),
        'snapshots' => $snapshots->map(fn (object $row): array => [
            'module' => $row->module,
            'batch_name' => $row->batch_name,
            'source_table' => $row->source_table,
            'source_id' => $row->source_id,
            'legacy_key' => $row->legacy_key,
            'classification' => $row->classification,
            'locale' => $row->locale,
            'payload_json' => $row->payload_json,
            'payload_text' => $row->payload_text,
        ])->all(),
        'translation_gaps' => $translationGaps->map(fn (object $row): array => [
            'module' => $row->module,
            'gap_type' => $row->gap_type,
            'target_table' => $row->target_table,
            'target_id' => $row->target_id,
            'faculty_id' => $row->faculty_id,
            'reference_value' => $row->reference_value,
            'ar_label' => $row->ar_label,
        ])->values()->all(),
        'unresolved_fields' => $unresolvedFields->map(fn (object $row): array => [
            'module' => $row->module,
            'issue_type' => $row->issue_type,
            'target_table' => $row->target_table,
            'target_id' => $row->target_id,
            'label' => $row->label,
            'details' => $row->details,
        ])->values()->all(),
    ];

    Storage::disk($disk)->put(
        $exportDir.'/missing_inventory.json',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );

    $writeCsv = function (string $path, array $rows) use ($disk): void {
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
    };

    $writeCsv($exportDir.'/skipped_logs.csv', $payload['skipped_logs']);
    $writeCsv($exportDir.'/rejections.csv', $payload['rejections']);
    $writeCsv($exportDir.'/snapshots.csv', $payload['snapshots']);
    $writeCsv($exportDir.'/translation_gaps.csv', $payload['translation_gaps']);
    $writeCsv($exportDir.'/unresolved_fields.csv', $payload['unresolved_fields']);

    $this->info('Missing data inventory exported.');
    $this->line('Disk: '.$disk);
    $this->line('Directory: storage/app/'.$exportDir);
    $this->line('Files:');
    $this->line('- missing_inventory.json');
    $this->line('- skipped_logs.csv');
    $this->line('- rejections.csv');
    $this->line('- snapshots.csv');
    $this->line('- translation_gaps.csv');
    $this->line('- unresolved_fields.csv');
})->purpose('Export missing legacy import inventory as JSON and CSV files');
