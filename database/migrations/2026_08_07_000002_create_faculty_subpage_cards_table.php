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
        Schema::create('faculty_subpage_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('faculty_slug');
            $table->string('subpage_slug');
            $table->string('title_override_ar')->nullable();
            $table->string('title_override_en')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('status')->default('published');
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['faculty_slug', 'subpage_slug']);
        });

        $this->seedFromFaculties();
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_subpage_cards');
    }

    private function seedFromFaculties(): void
    {
        $faculties = DB::table('faculties')->get();

        foreach ($faculties as $faculty) {
            $subpages = is_string($faculty->subpages_json)
                ? json_decode($faculty->subpages_json, true)
                : (is_array($faculty->subpages_json) ? $faculty->subpages_json : []);

            if (! is_array($subpages)) {
                $subpages = [];
            }

            $order = 0;

            foreach ($subpages as $slug) {
                $order++;

                DB::table('faculty_subpage_cards')->updateOrInsert(
                    ['faculty_slug' => $faculty->public_slug ?: $faculty->slug, 'subpage_slug' => $slug],
                    [
                        'sort_order' => $order,
                        'is_visible' => true,
                        'status' => 'published',
                        'published_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
};
