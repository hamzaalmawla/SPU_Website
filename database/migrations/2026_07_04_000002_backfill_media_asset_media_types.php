<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media_assets')
            ->where('mime_type', 'like', 'image/%')
            ->update(['media_type' => 'image']);

        DB::table('media_assets')
            ->where('mime_type', 'application/pdf')
            ->update(['media_type' => 'pdf']);

        DB::table('media_assets')
            ->where('mime_type', 'like', 'application/%')
            ->where('mime_type', '<>', 'application/pdf')
            ->update(['media_type' => 'document']);

        DB::table('media_assets')
            ->where('mime_type', 'like', 'video/%')
            ->update(['media_type' => 'video']);
    }

    public function down(): void
    {
        DB::table('media_assets')->update(['media_type' => 'other']);
    }
};
