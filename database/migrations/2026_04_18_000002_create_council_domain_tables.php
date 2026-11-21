<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('councils', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('council_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('council_id')->constrained('councils')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['council_id', 'locale']);
        });

        Schema::create('council_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('council_id')->constrained('councils')->cascadeOnDelete();
            $table->foreignId('faculty_member_id')->nullable()->constrained('faculty_members')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('council_member_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('council_member_id')->constrained('council_members')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique(['council_member_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_member_translations');
        Schema::dropIfExists('council_members');
        Schema::dropIfExists('council_translations');
        Schema::dropIfExists('councils');
    }
};
