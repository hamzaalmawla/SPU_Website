<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Councils
        Schema::create('councils', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('type')->nullable(); // academic, administrative, etc.
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('council_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('responsibilities')->nullable();
            $table->timestamps();

            $table->unique(['council_id', 'locale']);
        });

        // Council members
        Schema::create('council_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position'); // chair, member, secretary, etc.
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['council_id', 'is_active']);
        });

        Schema::create('council_member_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_member_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('name'); // If not linked to faculty_member
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique(['council_member_id', 'locale']);
        });

        // Council meetings
        Schema::create('council_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_id')->constrained()->cascadeOnDelete();
            $table->dateTime('meeting_date');
            $table->string('location')->nullable();
            $table->string('agenda_file_path')->nullable();
            $table->string('minutes_file_path')->nullable();
            $table->string('status'); // scheduled, completed, cancelled
            $table->timestamps();
            $table->softDeletes();

            $table->index(['council_id', 'meeting_date']);
        });

        Schema::create('council_meeting_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('council_meeting_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->timestamps();

            $table->unique(['council_meeting_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('council_meeting_translations');
        Schema::dropIfExists('council_meetings');
        Schema::dropIfExists('council_member_translations');
        Schema::dropIfExists('council_members');
        Schema::dropIfExists('council_translations');
        Schema::dropIfExists('councils');
    }
};
