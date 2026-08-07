<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('directorate_translations', function (Blueprint $table) {
            $table->json('links_json')->nullable()->after('services_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('directorate_translations', function (Blueprint $table) {
            $table->dropColumn('links_json');
        });
    }
};
