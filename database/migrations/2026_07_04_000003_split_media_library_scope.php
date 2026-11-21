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
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropUnique(['checksum']);
            $table->index('checksum');

            $table->string('library_scope', 40)->default('main')->index()->after('media_type');
            $table->string('metadata_status', 40)->default('missing')->index()->after('library_scope');
            $table->foreignId('promoted_from_media_id')->nullable()->after('metadata_status')->constrained('media_assets')->nullOnDelete();
            $table->string('source_path')->nullable()->index()->after('path');
            $table->timestamp('reviewed_at')->nullable()->after('source_path');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });

        $this->backfillMediaTypes();
        $this->backfillLibraryScopes();
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['promoted_from_media_id']);
            $table->dropColumn([
                'library_scope',
                'metadata_status',
                'promoted_from_media_id',
                'source_path',
                'reviewed_at',
                'reviewed_by',
            ]);

            $table->dropIndex(['checksum']);
            $table->unique('checksum');
        });
    }

    private function backfillMediaTypes(): void
    {
        DB::table('media_assets')
            ->where(function ($query): void {
                $query->whereNull('media_type')->orWhere('media_type', 'other');
            })
            ->where('mime_type', 'like', 'image/%')
            ->update(['media_type' => 'image']);

        DB::table('media_assets')
            ->where(function ($query): void {
                $query->whereNull('media_type')->orWhere('media_type', 'other');
            })
            ->where('mime_type', 'application/pdf')
            ->update(['media_type' => 'pdf']);

        DB::table('media_assets')
            ->where(function ($query): void {
                $query->whereNull('media_type')->orWhere('media_type', 'other');
            })
            ->where('mime_type', 'like', 'video/%')
            ->update(['media_type' => 'video']);

        DB::table('media_assets')
            ->where(function ($query): void {
                $query->whereNull('media_type')->orWhere('media_type', 'other');
            })
            ->where('mime_type', 'like', 'application/%')
            ->where('mime_type', '<>', 'application/pdf')
            ->update(['media_type' => 'document']);
    }

    private function backfillLibraryScopes(): void
    {
        DB::table('media_assets')
            ->where(function ($query): void {
                $query->where('disk', 'legacy')
                    ->orWhereIn('directory', ['news/images', 'news/files'])
                    ->orWhere('path', 'like', 'news/images/%')
                    ->orWhere('path', 'like', 'news/files/%')
                    ->orWhere('path', 'like', '/news/images/%')
                    ->orWhere('path', 'like', '/news/files/%');
            })
            ->update([
                'library_scope' => 'legacy',
                'source_path' => DB::raw('path'),
            ]);

        DB::table('media_assets')
            ->whereNull('library_scope')
            ->update(['library_scope' => 'main']);
    }
};
