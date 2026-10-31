<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_file_inventory', function (Blueprint $table): void {
            if (! Schema::hasColumn('legacy_file_inventory', 'checksum_status')) {
                $table->string('checksum_status', 32)->default('pending')->after('checksum_sha256')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('legacy_file_inventory', function (Blueprint $table): void {
            if (Schema::hasColumn('legacy_file_inventory', 'checksum_status')) {
                $table->dropColumn('checksum_status');
            }
        });
    }
};
