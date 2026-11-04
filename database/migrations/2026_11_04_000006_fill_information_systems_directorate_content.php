<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives the IT directorate (slug it-services) its approved content: the
 * Information Systems Development Directorate description and its two task
 * groups, in database/seeders/Data/directorates/it-services.json.
 *
 * Runs only while the directorate has no task groups in either language, so
 * an editor's later work on the page is never replaced. Its manager is picked
 * by an editor in the admin (a faculty profile), not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $directorateId = DB::table('directorates')->where('slug', 'it-services')->value('id');
        if ($directorateId === null) {
            return;
        }

        $edited = DB::table('directorate_translations')
            ->where('directorate_id', $directorateId)
            ->whereNotNull('task_groups_json')
            ->exists();
        if ($edited) {
            return;
        }

        $content = json_decode((string) file_get_contents(database_path('seeders/Data/directorates/it-services.json')), true, 512, JSON_THROW_ON_ERROR);
        $now = now();

        foreach (['ar', 'en'] as $locale) {
            $translation = $content['translations'][$locale];
            $values = [
                'title' => $translation['title'],
                'summary' => $translation['summary'],
                'description' => $translation['description'],
                'services_json' => json_encode($translation['services_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'links_json' => json_encode($translation['links_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'task_groups_json' => json_encode($translation['task_groups_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $now,
            ];

            $updated = DB::table('directorate_translations')
                ->where('directorate_id', $directorateId)
                ->where('locale', $locale)
                ->update($values);

            if ($updated === 0) {
                DB::table('directorate_translations')->insert([
                    'directorate_id' => $directorateId,
                    'locale' => $locale,
                    'created_at' => $now,
                    ...$values,
                ]);
            }
        }

        DB::table('directorates')->where('id', $directorateId)->update(['updated_at' => $now]);
    }

    public function down(): void
    {
        // Content only: the previous copy is not kept, and the schema
        // migration before this one drops the task groups column.
    }
};
