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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->after('role_slug')->constrained('roles')->nullOnDelete();
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('failed_login_attempts');
            $table->boolean('is_locked')->default(false)->after('failed_attempts')->index();
            $table->timestamp('last_login_at')->nullable()->after('locked_at');
            $table->softDeletes();
        });

        $roles = DB::table('roles')->pluck('id', 'slug');

        foreach ($roles as $slug => $roleId) {
            DB::table('users')
                ->where('role_slug', $slug)
                ->update(['role_id' => $roleId]);
        }

        DB::table('users')->update([
            'failed_attempts' => DB::raw('failed_login_attempts'),
            'is_locked' => DB::raw('CASE WHEN locked_at IS NULL THEN 0 ELSE 1 END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['last_login_at', 'is_locked', 'failed_attempts']);
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
