<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Legacy\LegacyNewsImportReviewService;
use ReflectionMethod;
use Tests\TestCase;

final class LegacyNewsReviewStatusTest extends TestCase
{
    /**
     * @param  array{int,int,int,int,int,int}  $args
     */
    private function reviewStatus(array $args): string
    {
        $method = new ReflectionMethod(LegacyNewsImportReviewService::class, 'status');
        $method->setAccessible(true);

        return $method->invokeArgs(app(LegacyNewsImportReviewService::class), $args);
    }

    public function test_attachments_without_a_media_asset_do_not_block_publication(): void
    {
        // The real shape of the archive: every attachment has a null
        // media_asset_id because the importer never created MediaAssets, and
        // they render from legacy_path regardless.
        //            longSlug, missingAr, missingEn, missingSeo, attachNoMedia, orphaned
        self::assertSame('review_ready', $this->reviewStatus([0, 0, 0, 0, 9881, 0]));
    }

    public function test_a_dangling_media_asset_id_still_blocks(): void
    {
        // A foreign key pointing at a row that is gone is broken in a way
        // legacy_path is not.
        self::assertSame('blocked', $this->reviewStatus([0, 0, 0, 0, 0, 1]));
    }

    public function test_missing_translations_still_block(): void
    {
        // /en/news/<id> falls back to the Arabic translation, so publishing an
        // article with no English serves Arabic under an English URL while
        // hreflang="en" points at it.
        self::assertSame('blocked', $this->reviewStatus([0, 0, 972, 0, 0, 0]));
        self::assertSame('blocked', $this->reviewStatus([0, 1, 0, 0, 0, 0]));
    }

    public function test_missing_seo_rows_require_cleanup_rather_than_blocking(): void
    {
        self::assertSame('cleanup_required', $this->reviewStatus([0, 0, 0, 1003, 9881, 0]));
    }
}
