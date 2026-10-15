<?php

declare(strict_types=1);

use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Homepage\HomepageSection;
use Illuminate\Database\Migrations\Migration;

/**
 * Switches the homepage bottom_stats section back off. It duplicates the
 * Medical Facilities statistics, has no admin editor, and was enabled only
 * because publishing used to enable every section (fixed in
 * HomepagePublishingService). Its content is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setEnabled(false);
    }

    public function down(): void
    {
        $this->setEnabled(true);
    }

    private function setEnabled(bool $enabled): void
    {
        HomepageSection::query()->where('key', 'bottom_stats')->update(['is_enabled' => $enabled]);

        $cache = app(CacheServiceInterface::class);

        foreach (['ar', 'en'] as $locale) {
            $cache->forget('public_pages:'.sha1($locale.'|'.$locale.'|'));
            $cache->flushTags(['public-pages', 'public-shell', 'public-shell:'.$locale]);
        }
    }
};
