<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A staff member belongs to an academic department (department_id) and may
 * also teach in one specialization: a program of the faculty's study plan,
 * stored by its id (plan.departments[].id). Optional; most faculties have one
 * program and leave it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->string('specialization_id', 64)->nullable()->after('department_id');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->dropColumn('specialization_id');
        });
    }
};
