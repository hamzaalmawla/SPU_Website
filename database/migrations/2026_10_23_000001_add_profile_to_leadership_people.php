<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A leadership row (persons) points at the faculty profile of the person who
 * holds the role. The profile is the one record of their name, photo and bio;
 * people:unify fills this column for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table): void {
            $table->foreignId('faculty_member_id')->nullable()->after('id')->constrained('faculty_members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('faculty_member_id');
        });
    }
};
