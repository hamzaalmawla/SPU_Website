<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forms editors build in the admin. The public forms used to be fixed in
 * code (DynamicFormSubmissionService and resources/js/data/dynamicForms.js);
 * those six are inserted here with the same keys, fields and rules, so pages
 * and stored submissions that name them keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('inbox', 40);
            $table->json('schema_json');
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $forms = json_decode((string) file_get_contents(database_path('seeders/Data/forms/built-in-forms.json')), true, flags: JSON_THROW_ON_ERROR);
        $now = now();

        foreach ($forms as $form) {
            DB::table('form_definitions')->insert([
                'key' => $form['key'],
                'name_ar' => $form['nameAr'],
                'name_en' => $form['nameEn'],
                'inbox' => $form['inbox'],
                'schema_json' => json_encode([
                    'multiStep' => $form['multiStep'],
                    'submitLabelAr' => $form['submitLabelAr'],
                    'submitLabelEn' => $form['submitLabelEn'],
                    'steps' => $form['steps'],
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'is_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_definitions');
    }
};
