<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A department is now either a specialization (it teaches a study plan
 * program) or a section (it has no study plan). The type follows from the
 * department's study plan link, so the Basic Sciences / academic / clinical
 * `kind` it replaces is dropped. Published departments pages keep their rows;
 * an older `kind` in a payload is ignored and removed on the next save.
 */
return new class extends Migration
{
    private const BASIC_SCIENCES = ['petroleum-basic-sciences', 'construction-basic-sciences', 'life-sciences', 'ai-basic-sciences'];

    public function up(): void
    {
        if (! Schema::hasColumn('departments', 'kind')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('departments', 'kind')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table): void {
            $table->string('kind', 32)->default('academic')->after('slug');
        });

        DB::table('departments')->whereIn('slug', self::BASIC_SCIENCES)->update(['kind' => 'basic_sciences']);
    }
};
