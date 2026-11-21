<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The form fields a staff link makes required, picked when the link is made
 * (StaffIntakeFields::REQUIRABLE). Null keeps the defaults for the link's
 * kind (StaffIntakeFields::defaultRequired), which is what older links use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_intake_links', function (Blueprint $table): void {
            $table->json('required_fields')->nullable()->after('max_submissions');
        });
    }

    public function down(): void
    {
        Schema::table('staff_intake_links', function (Blueprint $table): void {
            $table->dropColumn('required_fields');
        });
    }
};
