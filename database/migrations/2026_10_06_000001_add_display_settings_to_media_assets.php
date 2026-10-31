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
            $table->decimal('focal_x', 5, 2)->default(50)->after('height');
            $table->decimal('focal_y', 5, 2)->default(50)->after('focal_x');
            $table->string('display_fit', 20)->default('cover')->after('focal_y');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn(['focal_x', 'focal_y', 'display_fit']);
        });
    }
};
