<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_publications', function (Blueprint $table): void {
            $table->foreignId('person_id')->nullable()->after('faculty_member_id')->constrained('persons')->nullOnDelete();
        });

        Schema::table('council_members', function (Blueprint $table): void {
            $table->foreignId('person_id')->nullable()->after('faculty_member_id')->constrained('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('council_members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::table('research_publications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
