<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The directorate manager's photo: an image path picked from the media library. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directorates', function (Blueprint $table): void {
            $table->string('manager_photo', 2048)->nullable()->after('manager_phone');
        });
    }

    public function down(): void
    {
        Schema::table('directorates', function (Blueprint $table): void {
            $table->dropColumn('manager_photo');
        });
    }
};
