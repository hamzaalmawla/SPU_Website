<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unresolved_legacy_requests', function (Blueprint $table): void {
            $table->json('normalized_json')->nullable()->after('request_type');
            $table->string('handler', 120)->nullable()->index()->after('normalized_json');
            $table->string('outcome', 60)->nullable()->index()->after('handler');
            $table->string('subsite', 40)->nullable()->index()->after('outcome');
            $table->unsignedSmallInteger('old_site_id')->nullable()->index()->after('subsite');
            $table->unsignedSmallInteger('old_language_id')->nullable()->index()->after('old_site_id');
            $table->string('old_language_symbol', 10)->nullable()->after('old_language_id');
        });
    }

    public function down(): void
    {
        Schema::table('unresolved_legacy_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'normalized_json',
                'handler',
                'outcome',
                'subsite',
                'old_site_id',
                'old_language_id',
                'old_language_symbol',
            ]);
        });
    }
};
