<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A profile can belong to a university center (config/centers.php) instead of
 * a faculty: the Requirements Unit's staff are listed on its staff page from
 * this column, and their profile names the center.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('faculty_members', 'center_slug')) {
            return;
        }

        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->string('center_slug', 80)->nullable()->after('specialization_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('faculty_members', 'center_slug')) {
            return;
        }

        Schema::table('faculty_members', function (Blueprint $table): void {
            $table->dropIndex(['center_slug']);
            $table->dropColumn('center_slug');
        });
    }
};
