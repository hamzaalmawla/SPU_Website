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
            $table->string('title')->nullable()->after('category');
            $table->string('position')->nullable()->after('title');
            $table->string('office_location')->nullable()->after('phone');
            $table->json('social_links')->nullable()->after('profile_url');
        });

        Schema::table('person_translations', function (Blueprint $table): void {
            $table->text('education')->nullable()->after('bio');
        });

        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('id');
            $table->string('office_location')->nullable()->after('phone');
            $table->json('social_links')->nullable()->after('office_location');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->dropColumn(['slug', 'office_location', 'social_links']);
        });

        Schema::table('person_translations', function (Blueprint $table): void {
            $table->dropColumn('education');
        });

        Schema::table('persons', function (Blueprint $table): void {
            $table->dropColumn(['title', 'position', 'office_location', 'social_links']);
        });
    }
};
