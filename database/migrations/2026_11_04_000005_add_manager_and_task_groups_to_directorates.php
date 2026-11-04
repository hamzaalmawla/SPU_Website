<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A directorate's manager is a faculty profile (one profile per person: the
 * profile holds the name, photo, contact and education). Task groups are the
 * directorate's titled lists of tasks, each task a title and a short body.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directorates', function (Blueprint $table): void {
            $table->foreignId('manager_member_id')->nullable()->after('location')->constrained('faculty_members')->nullOnDelete();
        });

        Schema::table('directorate_translations', function (Blueprint $table): void {
            $table->json('task_groups_json')->nullable()->after('links_json');
        });
    }

    public function down(): void
    {
        Schema::table('directorate_translations', function (Blueprint $table): void {
            $table->dropColumn('task_groups_json');
        });

        Schema::table('directorates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_member_id');
        });
    }
};
