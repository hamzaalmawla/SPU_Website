<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shared\MigrationLog;
use App\Models\Shared\MigrationRejection;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class LegacyImportAuditCommand extends Command
{
    protected $signature = 'legacy-import:audit {module?} {--details}';

    protected $description = 'Audit legacy import source totals, target totals, logs, rejections, and snapshots.';

    public function handle(): int
    {
        $module = $this->argument('module');
        $module = is_string($module) && $module !== '' ? $module : null;
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

        if ($module !== null) {
            $logs->where('module', $module);
            $rejections->where('module', $module);
            $snapshots->where('module', $module);
        }

        $this->info('Legacy Import Audit'.($module !== null ? ' ['.$module.']' : ''));
        $this->newLine();
        $this->table(['Module', 'Source Table', 'Rows'], $this->sourceSummary($module, $moduleSourceTables));

        $this->newLine();
        $this->info('Log Summary');
        $this->table(['Module', 'Status', 'Total'], (clone $logs)
            ->select('module', 'status', DB::raw('count(*) as total'))
            ->groupBy('module', 'status')
            ->orderBy('module')
            ->orderBy('status')
            ->get()
            ->map(fn (MigrationLog $log): array => [$log->module, $log->status, $log->total])
            ->all());

        $this->newLine();
        $this->info('Rejections');
        $rejectionSummary = (clone $rejections)
            ->select('module', 'reason_code', DB::raw('count(*) as total'))
            ->groupBy('module', 'reason_code')
            ->orderBy('module')
            ->orderBy('reason_code')
            ->get()
            ->map(fn (MigrationRejection $rejection): array => [$rejection->module, $rejection->reason_code, $rejection->total])
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
            ->map(fn (object $snapshot): array => [$snapshot->module, $snapshot->classification, $snapshot->total])
            ->all();

        if ($snapshotSummary === []) {
            $this->warn('No legacy snapshots recorded.');
        } else {
            $this->table(['Module', 'Classification', 'Total'], $snapshotSummary);
        }

        $this->newLine();
        $this->info('Target Tables');
        $this->table(['Module', 'Target Table', 'Rows'], $this->targetSummary($module, $moduleTargetTables));

        if ((bool) $this->option('details')) {
            $this->renderDetails($logs, $rejections, $snapshots);
        }

        return self::SUCCESS;
    }

    /** @param array<string, list<string>> $moduleSourceTables */
    private function sourceSummary(?string $module, array $moduleSourceTables): array
    {
        $sourceSummary = [];

        try {
            $legacy = app(OldDatabaseConnection::class);
            $legacy->connection();
            $sourceModules = $module !== null ? [$module => ($moduleSourceTables[$module] ?? [])] : $moduleSourceTables;

            foreach ($sourceModules as $sourceModule => $tables) {
                foreach ($tables as $table) {
                    $sourceSummary[] = [
                        'module' => $sourceModule,
                        'source_table' => $table,
                        'source_rows' => $table === 'legacy_council_taxonomy' ? 7 : $legacy->table($table)->count(),
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

        return $sourceSummary;
    }

    /** @param array<string, list<string>> $moduleTargetTables */
    private function targetSummary(?string $module, array $moduleTargetTables): array
    {
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

        return $targetSummary;
    }

    private function renderDetails(mixed $logs, mixed $rejections, mixed $snapshots): void
    {
        $this->newLine();
        $this->info('Recent Skips');
        $recentSkips = (clone $logs)->where('status', 'skipped')->latest('id')->limit(20)->get(['module', 'source_table', 'source_id', 'target_table', 'message']);
        $this->table(['Module', 'Source', 'Source ID', 'Target', 'Message'], $recentSkips->map(fn (MigrationLog $log): array => [
            $log->module,
            $log->source_table,
            (string) $log->source_id,
            $log->target_table,
            $log->message,
        ])->all());

        $this->newLine();
        $this->info('Recent Rejections');
        $recentRejections = (clone $rejections)->latest('id')->limit(20)->get(['module', 'source_table', 'source_id', 'reason_code', 'reason_message']);
        $this->table(['Module', 'Source', 'Source ID', 'Reason', 'Message'], $recentRejections->map(fn (MigrationRejection $rejection): array => [
            $rejection->module,
            $rejection->source_table,
            (string) $rejection->source_id,
            $rejection->reason_code,
            $rejection->reason_message,
        ])->all());

        $this->newLine();
        $this->info('Recent Snapshots');
        $recentSnapshots = (clone $snapshots)->latest('id')->limit(20)->get(['module', 'source_table', 'source_id', 'classification', 'locale']);
        $this->table(['Module', 'Source', 'Source ID', 'Classification', 'Locale'], $recentSnapshots->map(fn (object $snapshot): array => [
            $snapshot->module,
            $snapshot->source_table,
            (string) $snapshot->source_id,
            (string) $snapshot->classification,
            (string) $snapshot->locale,
        ])->all());
    }
}
