<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_file_inventory', function (Blueprint $table): void {
            if (! Schema::hasColumn('legacy_file_inventory', 'source_table')) {
                $table->string('source_table')->nullable()->after('legacy_path')->index();
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'source_column')) {
                $table->string('source_column')->nullable()->after('source_table')->index();
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_column')->index();
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'extension')) {
                $table->string('extension', 32)->nullable()->after('mime_type')->index();
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'checksum_sha256')) {
                $table->string('checksum_sha256', 64)->nullable()->after('file_size_bytes')->index();
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'reference_count')) {
                $table->unsignedInteger('reference_count')->default(1)->after('checksum_sha256');
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'source_references')) {
                $table->json('source_references')->nullable()->after('reference_count');
            }

            if (! Schema::hasColumn('legacy_file_inventory', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('source_references')->index();
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('CREATE INDEX idx_legacy_file_checksum_status ON legacy_file_inventory (checksum_sha256, status)');
        }
    }

    public function down(): void
    {
        Schema::table('legacy_file_inventory', function (Blueprint $table): void {
            foreach (['last_seen_at', 'source_references', 'reference_count', 'checksum_sha256', 'extension', 'source_id', 'source_column', 'source_table'] as $column) {
                if (Schema::hasColumn('legacy_file_inventory', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
