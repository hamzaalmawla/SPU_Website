<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->update(['value_text' => 'https://my.spu.edu.sy/ar/login']);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->update(['value_text' => '/e-services/it-support']);
    }
};
