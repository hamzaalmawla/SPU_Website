<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type')->index();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('council_id')->nullable()->constrained('councils')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('person_appointment_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_appointment_id')->constrained('person_appointments')->cascadeOnDelete();
            $table->string('locale', 5)->index();
            $table->string('role_override')->nullable();
            $table->timestamps();

            $table->unique(['person_appointment_id', 'locale'], 'pa_trans_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_appointment_translations');
        Schema::dropIfExists('person_appointments');
    }
};
