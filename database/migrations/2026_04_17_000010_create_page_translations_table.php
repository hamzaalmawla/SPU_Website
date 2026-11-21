<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('title');
            $table->string('navigation_label')->nullable();
            $table->string('headline')->nullable();
            $table->string('subheadline')->nullable();
            $table->json('hero_payload')->nullable();
            $table->json('overview_cards_payload')->nullable();
            $table->json('stats_payload')->nullable();
            $table->json('body_payload')->nullable();
            $table->json('cta_payload')->nullable();
            $table->json('sidebar_payload')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->text('raw_excerpt')->nullable();
            $table->string('meta_title_fallback')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};
