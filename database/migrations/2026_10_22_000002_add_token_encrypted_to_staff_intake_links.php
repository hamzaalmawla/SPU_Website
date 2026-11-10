<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editors need a link's address again after the "copy it now" notice is
 * closed. The token stays looked up by its hash; this keeps an encrypted
 * copy (APP_KEY) so the admin can show the address to whoever manages the
 * link. Links made before this have none and must get a new address.
 *
 * Schema only: no rows are written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_intake_links', function (Blueprint $table): void {
            $table->text('token_encrypted')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('staff_intake_links', function (Blueprint $table): void {
            $table->dropColumn('token_encrypted');
        });
    }
};
