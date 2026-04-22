<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Honor/Good Students
        Schema::create('honor_students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->unique(); // University student ID
            $table->string('full_name');
            $table->string('faculty')->nullable();
            $table->string('department')->nullable();
            $table->string('academic_year');
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('honor_type')->nullable(); // dean's list, president's list, etc.
            $table->string('photo_path')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['academic_year', 'is_published']);
            $table->index('gpa');
        });

        // Alumni/Graduated Students
        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->string('student_id')->nullable(); // University student ID
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('faculty');
            $table->string('department')->nullable();
            $table->string('degree'); // Bachelor, Master, PhD
            $table->year('graduation_year');
            $table->string('current_position')->nullable();
            $table->string('current_company')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('achievements')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['graduation_year', 'is_published']);
            $table->index('is_featured');
        });

        // Student achievements/awards
        Schema::create('student_achievements', function (Blueprint $table) {
            $table->id();
            $table->morphs('achievable'); // honor_students or alumni
            $table->string('type'); // award, competition, research, etc.
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('achievement_date')->nullable();
            $table->string('organization')->nullable();
            $table->string('certificate_path')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('honor_students');
    }
};
