<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculties', function (Blueprint $table): void {
            $table->json('subpages_json')->nullable()->after('gallery_json');
        });

        $faculties = DB::table('faculties')->get();

        foreach ($faculties as $faculty) {
            $pages = DB::table('faculty_pages')
                ->where('faculty_id', $faculty->id)
                ->where('is_enabled', true)
                ->pluck('slug')
                ->all();

            DB::table('faculties')
                ->where('id', $faculty->id)
                ->update(['subpages_json' => json_encode(array_values($pages), JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        Schema::table('faculties', function (Blueprint $table): void {
            $table->dropColumn('subpages_json');
        });
    }
};
