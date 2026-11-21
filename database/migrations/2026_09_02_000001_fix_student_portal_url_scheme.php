<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Production stored the student portal link as http://, and the portal policy
 * accepts https:// only.
 *
 * UrlSanitizer::sanitize($value, ['https'], true) rejects an http:// URL on
 * scheme even when the host is trusted, so getStudentPortalUrl() returned null
 * and /{locale}/campus-life/transport/registration answered a hard 503 whose
 * branded view reads "Under maintenance". The host was always correct and the
 * trusted-host config was always correct; only the scheme was wrong.
 *
 * 2026_08_14_000001_update_student_portal_url set the https:// form, so this
 * regressed afterwards - most plausibly through ManageSettings, which validated
 * with ->url() and therefore accepted http:// without complaint. That hole is
 * closed separately by App\Rules\TrustedPortalUrlRule.
 *
 * The WHERE clause matches the broken VALUE, not just the row, so if the setting
 * has already been corrected through the admin panel this migration is a no-op
 * rather than an overwrite of someone's edit.
 */
return new class extends Migration
{
    private const BROKEN = 'http://my.spu.edu.sy/ar/login';

    private const FIXED = 'https://my.spu.edu.sy/ar/login';

    public function up(): void
    {
        DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->where('value_text', self::BROKEN)
            ->update(['value_text' => self::FIXED]);
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group_key', 'navigation')
            ->where('key', 'student_portal_url')
            ->where('locale', '')
            ->where('value_text', self::FIXED)
            ->update(['value_text' => self::BROKEN]);
    }
};
