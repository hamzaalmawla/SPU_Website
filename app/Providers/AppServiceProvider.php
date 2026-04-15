<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AuditServiceInterface;
use App\Contracts\AuthServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\MediaServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\Contracts\SlugServiceInterface;
use App\Services\Placeholders\AuditServicePlaceholder;
use App\Services\Placeholders\AuthServicePlaceholder;
use App\Services\Placeholders\CacheServicePlaceholder;
use App\Services\Placeholders\HomepagePublishingServicePlaceholder;
use App\Services\Placeholders\HomepageSectionServicePlaceholder;
use App\Services\Placeholders\MediaServicePlaceholder;
use App\Services\Placeholders\MenuServicePlaceholder;
use App\Services\Placeholders\NavigationServicePlaceholder;
use App\Services\Placeholders\PageServicePlaceholder;
use App\Services\Placeholders\PreviewServicePlaceholder;
use App\Services\Placeholders\SeoMetadataServicePlaceholder;
use App\Services\Placeholders\SettingsServicePlaceholder;
use App\Services\Placeholders\SlugServicePlaceholder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaServiceInterface::class, MediaServicePlaceholder::class);
        $this->app->bind(CacheServiceInterface::class, CacheServicePlaceholder::class);
        $this->app->bind(AuditServiceInterface::class, AuditServicePlaceholder::class);
        $this->app->bind(AuthServiceInterface::class, AuthServicePlaceholder::class);
        $this->app->bind(MenuServiceInterface::class, MenuServicePlaceholder::class);
        $this->app->bind(SlugServiceInterface::class, SlugServicePlaceholder::class);
        $this->app->bind(SeoMetadataServiceInterface::class, SeoMetadataServicePlaceholder::class);
        $this->app->bind(HomepageSectionServiceInterface::class, HomepageSectionServicePlaceholder::class);
        $this->app->bind(HomepagePublishingServiceInterface::class, HomepagePublishingServicePlaceholder::class);
        $this->app->bind(PreviewServiceInterface::class, PreviewServicePlaceholder::class);
        $this->app->bind(PageServiceInterface::class, PageServicePlaceholder::class);
        $this->app->bind(SettingsServiceInterface::class, SettingsServicePlaceholder::class);
        $this->app->bind(NavigationServiceInterface::class, NavigationServicePlaceholder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
