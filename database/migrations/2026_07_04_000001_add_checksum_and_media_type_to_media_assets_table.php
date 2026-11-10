<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('checksum', 64)->nullable()->unique()->after('size_bytes');
            $table->string('media_type', 40)->default('other')->index()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropUnique(['checksum']);
            $table->dropColumn(['checksum', 'media_type']);
        });
    }
};
