<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema only. What the public researchers directory shows for a researcher
 * who has no staff profile: their scholarly profile links and expertise. A
 * researcher can be hidden from the directory without touching their papers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researchers', function (Blueprint $table): void {
            $table->string('orcid_url')->nullable()->after('affiliation');
            $table->string('scholar_url')->nullable()->after('orcid_url');
            $table->string('scopus_url')->nullable()->after('scholar_url');
            $table->json('expertise')->nullable()->after('scopus_url');
            $table->boolean('is_enabled')->default(true)->after('registration_status');
        });
    }

    public function down(): void
    {
        Schema::table('researchers', function (Blueprint $table): void {
            $table->dropColumn(['orcid_url', 'scholar_url', 'scopus_url', 'expertise', 'is_enabled']);
        });
    }
};
