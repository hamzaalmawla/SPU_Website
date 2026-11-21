<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shared\MigrationLog;
use App\Models\Shared\MigrationRejection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class LegacyImportReportCommand extends Command
{
    protected $signature = 'legacy-import:report {module?} {--details}';

    protected $description = 'Summarize legacy import logs and rejections.';

    public function handle(): int
    {
        $module = $this->argument('module');
        $module = is_string($module) && $module !== '' ? $module : null;
        $logs = MigrationLog::query();
        $rejections = MigrationRejection::query();

        if ($module !== null) {
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

        if ((bool) $this->option('details')) {
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

        return self::SUCCESS;
    }
}
