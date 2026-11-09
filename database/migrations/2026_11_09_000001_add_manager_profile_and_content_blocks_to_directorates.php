<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The directorate page template. A manager may now be linked to a staff
 * profile (optional: managers are often not teaching staff, and the typed
 * fields stay for them), and the page body is a list of blocks the editor
 * builds freely: headings, text boxes, images and buttons.
 *
 * Schema only: no rows are inserted, updated or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directorates', function (Blueprint $table): void {
            $table->foreignId('manager_member_id')->nullable()->after('location')->constrained('faculty_members')->nullOnDelete();
            $table->json('content_blocks_json')->nullable()->after('manager_photo');
        });
    }

    public function down(): void
    {
        Schema::table('directorates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_member_id');
            $table->dropColumn('content_blocks_json');
        });
    }
};
