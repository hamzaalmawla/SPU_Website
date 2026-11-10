<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->string('category_key')->nullable();
            $table->date('published_at')->nullable();
            $table->string('external_url')->nullable();
            $table->foreignId('file_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('research_publication_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_publication_id')->constrained(table: 'research_publications', indexName: 'rpt_pub_fk')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->text('abstract')->nullable();
            $table->string('publisher')->nullable();
            $table->timestamps();

            $table->unique(['research_publication_id', 'locale'], 'rpt_pub_locale_unique');
        });

        Schema::create('research_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_publication_id')->constrained('research_publications')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_files');
        Schema::dropIfExists('research_publication_translations');
        Schema::dropIfExists('research_publications');
    }
};
