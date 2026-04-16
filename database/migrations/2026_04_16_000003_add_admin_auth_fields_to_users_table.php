<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role_slug')->nullable()->after('password')->index();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('role_slug');
            $table->timestamp('locked_at')->nullable()->after('failed_login_attempts');
            $table->string('faculty_scope_slug')->nullable()->after('locked_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role_slug']);
            $table->dropIndex(['faculty_scope_slug']);
            $table->dropColumn([
                'role_slug',
                'failed_login_attempts',
                'locked_at',
                'faculty_scope_slug',
            ]);
        });
    }
};
