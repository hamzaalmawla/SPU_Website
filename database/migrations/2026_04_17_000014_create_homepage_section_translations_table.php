<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained('homepage_sections')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->json('payload_json');
            $table->timestamps();

            $table->unique(['section_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_section_translations');
    }
};
