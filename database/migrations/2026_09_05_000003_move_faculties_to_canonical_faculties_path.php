<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The academic colleges move from /{locale}/facilities to /{locale}/faculties.
 *
 * In English "facilities" means buildings and amenities; the pages under this
 * path are the Faculties of Medicine, Dentistry, Pharmacy, Petroleum, Business
 * Administration, Artificial Intelligence and Building & Construction
 * Engineering. The same error had reached the navigation labels, which read
 * "Facilities" and "المرافق" (amenities) above a menu of colleges, while
 * ErrorPageService already called the same section "Faculties" / "الكليات".
 *
 * Done now because the cost only rises: nothing is indexed yet - the staging
 * host has been noindex throughout - so there is no ranking or inbound link to
 * preserve. After cutover this would mean retiring live URLs.
 *
 * The routes swap rather than move: /{locale}/facilities/* now 301s to the
 * canonical /{locale}/faculties/*, so any link already shared keeps working.
 *
 * This migration only touches rows a person could have edited. Everything else
 * is rebuilt from code on deploy:
 *
 *   - legacy_exact_redirects is re-seeded every deploy by
 *     LegacyEntryPointRedirectSeeder, which updateOrInserts on legacy_path.
 *   - cms_target_contents is keyed by target_key, and those keys are
 *     deliberately unchanged ('facilities.landing' and 'facilities.<slug>'),
 *     so no published content is orphaned. Only the display path moved.
 */
return new class extends Migration
{
    /** Locale-prefixed public paths, so a stray "/facilities" elsewhere is left alone. */
    private const PATHS = [
        '/ar/facilities' => '/ar/faculties',
        '/en/facilities' => '/en/faculties',
    ];

    /**
     * NavigationSeeder matches existing rows on (type, group_key, locale,
     * label). Renaming the label here as well is what keeps the next seed
     * updating this row instead of inserting a second copy of the menu.
     */
    private const LABELS = [
        'المرافق' => 'الكليات',
        'Facilities' => 'Faculties',
    ];

    public function up(): void
    {
        $this->rewriteMenuUrls(self::PATHS);

        foreach (self::LABELS as $from => $to) {
            DB::table('menu_items')
                ->where('group_key', 'header')
                ->where('label', $from)
                ->update(['label' => $to]);
        }
    }

    public function down(): void
    {
        $this->rewriteMenuUrls(array_flip(self::PATHS));

        foreach (self::LABELS as $from => $to) {
            DB::table('menu_items')
                ->where('group_key', 'header')
                ->where('label', $to)
                ->update(['label' => $from]);
        }
    }

    /**
     * @param  array<string, string>  $map
     */
    private function rewriteMenuUrls(array $map): void
    {
        if (! DB::getSchemaBuilder()->hasTable('menu_items')) {
            return;
        }

        foreach ($map as $from => $to) {
            // Anchored to the start so a URL that merely contains the old path
            // further along is not rewritten, and matched with a LIKE first so
            // this stays a small, indexable update rather than a table scan.
            DB::table('menu_items')
                ->where('url', 'like', $from.'%')
                ->update([
                    'url' => DB::raw(
                        'CONCAT('.DB::getPdo()->quote($to).', SUBSTRING(url, '.(strlen($from) + 1).'))'
                    ),
                ]);
        }
    }
};
