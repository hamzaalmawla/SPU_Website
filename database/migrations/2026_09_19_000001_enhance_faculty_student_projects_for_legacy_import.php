<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_student_projects', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_source_id')->nullable()->unique()->after('faculty_id');
            $table->unsignedSmallInteger('legacy_service_type')->nullable()->index()->after('legacy_source_id');
            $table->json('gallery_json')->nullable()->after('image');
            $table->json('documents_json')->nullable()->after('gallery_json');
        });

        Schema::table('faculty_student_project_translations', function (Blueprint $table): void {
            $table->json('body_json')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_student_project_translations', function (Blueprint $table): void {
            $table->dropColumn('body_json');
        });

        Schema::table('faculty_student_projects', function (Blueprint $table): void {
            $table->dropColumn(['legacy_source_id', 'legacy_service_type', 'gallery_json', 'documents_json']);
        });
    }
};
