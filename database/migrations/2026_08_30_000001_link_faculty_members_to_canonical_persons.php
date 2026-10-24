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
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->foreignId('person_id')
                ->nullable()
                ->after('id')
                ->constrained('persons')
                ->nullOnDelete();
        });

        DB::table('persons')
            ->select(['id', 'slug'])
            ->whereNotNull('slug')
            ->orderBy('id')
            ->each(function (object $person): void {
                DB::table('faculty_members')
                    ->whereNull('person_id')
                    ->where('slug', $person->slug)
                    ->update(['person_id' => $person->id]);
            });

        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')
                ->select(['id', 'url'])
                ->where('url', 'like', '%/research/researchers/%')
                ->orderBy('id')
                ->each(function (object $item): void {
                    DB::table('menu_items')
                        ->where('id', $item->id)
                        ->update(['url' => str_replace('/research/researchers/', '/about/profile/', (string) $item->url)]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
