<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateNames([
            359 => 'ياسمين نبيل المولا',
            390 => 'ياسمين نبيل المولا',
        ]);

        $targetId = $this->targetId(898);

        if ($targetId !== null) {
            DB::table('honor_students')->where('id', $targetId)->update(['gpa' => 89.60]);
        }
    }

    public function down(): void
    {
        $this->updateNames([
            359 => 'ياسمسن نبيل المولا',
            390 => 'ياسين نبيل المولا',
        ]);
    }

    /** @param array<int, string> $namesBySourceId */
    private function updateNames(array $namesBySourceId): void
    {
        foreach ($namesBySourceId as $sourceId => $name) {
            $targetId = $this->targetId($sourceId);

            if ($targetId === null) {
                continue;
            }

            DB::table('honor_student_translations')
                ->where('honor_student_id', $targetId)
                ->whereIn('locale', ['ar', 'en'])
                ->update(['full_name' => $name]);
        }
    }

    private function targetId(int $sourceId): ?int
    {
        $targetId = DB::table('migration_logs')
            ->where('module', 'honor_students')
            ->where('source_table', 'jx_good_students')
            ->where('source_id', $sourceId)
            ->where('target_table', 'honor_students')
            ->where('status', 'success')
            ->value('target_id');

        return is_numeric($targetId) ? (int) $targetId : null;
    }
};
