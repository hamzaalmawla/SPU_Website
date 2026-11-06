<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff intake links for centers (config/centers.php). A link, and the
 * requests sent through it, belongs to a faculty or to a center
 * (`center_slug`), so faculty_id becomes optional.
 *
 * Schema only: no rows are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_intake_links', function (Blueprint $table): void {
            $table->foreignId('faculty_id')->nullable()->change();
            $table->string('center_slug', 120)->nullable()->after('faculty_id')->index();
        });

        Schema::table('staff_intake_requests', function (Blueprint $table): void {
            $table->foreignId('faculty_id')->nullable()->change();
            $table->string('center_slug', 120)->nullable()->after('faculty_id');
            $table->index(['center_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_intake_requests', function (Blueprint $table): void {
            $table->dropIndex(['center_slug', 'status']);
            $table->dropColumn('center_slug');
        });

        Schema::table('staff_intake_links', function (Blueprint $table): void {
            $table->dropIndex(['center_slug']);
            $table->dropColumn('center_slug');
        });
    }
};
