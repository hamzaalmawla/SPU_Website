<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A directorate's manager is typed on the directorate itself (name and
 * education per language, email and phone), not picked from the staff
 * profiles: managers are not teaching staff. Replaces manager_member_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directorates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_member_id');
        });

        Schema::table('directorates', function (Blueprint $table): void {
            $table->string('manager_email')->nullable()->after('location');
            $table->string('manager_phone', 50)->nullable()->after('manager_email');
        });

        Schema::table('directorate_translations', function (Blueprint $table): void {
            $table->string('manager_name')->nullable()->after('task_groups_json');
            $table->json('manager_education_json')->nullable()->after('manager_name');
        });
    }

    public function down(): void
    {
        Schema::table('directorate_translations', function (Blueprint $table): void {
            $table->dropColumn(['manager_name', 'manager_education_json']);
        });

        Schema::table('directorates', function (Blueprint $table): void {
            $table->dropColumn(['manager_email', 'manager_phone']);
        });

        Schema::table('directorates', function (Blueprint $table): void {
            $table->foreignId('manager_member_id')->nullable()->after('location')->constrained('faculty_members')->nullOnDelete();
        });
    }
};
