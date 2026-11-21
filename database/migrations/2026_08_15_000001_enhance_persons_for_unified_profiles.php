<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table): void {
            $table->foreignId('photo_media_id')->nullable()->after('profile_url')->constrained('media_assets')->nullOnDelete();
            $table->foreignId('cv_media_id')->nullable()->after('photo_media_id')->constrained('media_assets')->nullOnDelete();
            $table->string('orcid_url')->nullable()->after('cv_media_id');
            $table->string('scholar_url')->nullable()->after('orcid_url');
            $table->string('legacy_photo_path')->nullable()->after('image');
            $table->string('legacy_cv_path')->nullable()->after('legacy_photo_path');
            $table->string('legacy_ar_cv_path')->nullable()->after('legacy_cv_path');
        });

        Schema::table('person_translations', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('role');
            $table->string('position')->nullable()->after('title');
            $table->json('specializations')->nullable()->after('education');
        });
    }

    public function down(): void
    {
        Schema::table('person_translations', function (Blueprint $table): void {
            $table->dropColumn(['title', 'position', 'specializations']);
        });

        Schema::table('persons', function (Blueprint $table): void {
            $table->dropColumn(['photo_media_id', 'cv_media_id', 'orcid_url', 'scholar_url', 'legacy_photo_path', 'legacy_cv_path', 'legacy_ar_cv_path']);
        });
    }
};
