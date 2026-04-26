<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Countries
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique(); // ISO 3166-1 alpha-2
            $table->string('code3', 3)->unique(); // ISO 3166-1 alpha-3
            $table->string('phone_code')->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('country_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->string('nationality')->nullable();
            $table->timestamps();
            
            $table->unique(['country_id', 'locale']);
        });

        // Cities
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->index('country_id');
        });

        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->timestamps();
            
            $table->unique(['city_id', 'locale']);
        });

        // Languages
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique(); // ISO 639-1
            $table->string('direction', 3)->default('ltr'); // ltr or rtl
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('language_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->timestamps();
            
            $table->unique(['language_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('city_translations');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('country_translations');
        Schema::dropIfExists('countries');
    }
};
