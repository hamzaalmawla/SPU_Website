<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> */
    private array $columns = [
        'news_articles' => ['legacy_cover_path' => ['legacy_photo']],
        'research_publications' => ['legacy_image_path' => ['legacy_photo']],
        'alumni' => ['legacy_photo_path' => ['legacy_photo']],
        'honor_students' => ['legacy_photo_path' => ['legacy_photo']],
        'faculty_members' => [
            'legacy_photo_path' => ['legacy_photo'],
            'legacy_cv_path' => ['legacy_cv'],
            'legacy_ar_cv_path' => ['legacy_ar_cv'],
        ],
        'council_members' => [
            'legacy_photo_path' => ['legacy_photo'],
            'legacy_cv_path' => ['legacy_cv'],
            'legacy_ar_cv_path' => ['legacy_ar_cv'],
        ],
        'career_links' => ['legacy_photo_path' => ['legacy_photo_path']],
    ];

    public function up(): void
    {
        foreach ($this->columns as $tableName => $tableColumns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableColumns): void {
                foreach (array_keys($tableColumns) as $column) {
                    $table->string($column)->nullable();
                }
            });
        }

        $this->backfillFromMigrationLogs();
    }

    public function down(): void
    {
        foreach ($this->columns as $tableName => $tableColumns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableColumns): void {
                $table->dropColumn(array_keys($tableColumns));
            });
        }
    }

    private function backfillFromMigrationLogs(): void
    {
        if (! Schema::hasTable('migration_logs')) {
            return;
        }

        $logs = DB::table('migration_logs')
            ->where('status', 'success')
            ->whereNotNull('target_id')
            ->orderBy('id')
            ->get(['target_table', 'target_id', 'metadata']);

        foreach ($logs as $log) {
            $targetTable = (string) $log->target_table;
            if (! isset($this->columns[$targetTable])) {
                continue;
            }

            $metadata = is_array($log->metadata)
                ? $log->metadata
                : json_decode((string) $log->metadata, true);
            if (! is_array($metadata)) {
                continue;
            }

            $updates = [];
            foreach ($this->columns[$targetTable] as $column => $keys) {
                foreach ($keys as $key) {
                    $value = $metadata[$key] ?? null;
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        $updates[$column] = trim((string) $value);
                        break;
                    }
                }
            }

            if ($updates !== []) {
                DB::table($targetTable)
                    ->where('id', (int) $log->target_id)
                    ->update($updates);
            }
        }
    }
};
