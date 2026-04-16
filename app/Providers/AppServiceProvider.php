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
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CacheService;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registers application service bindings and infrastructure configuration.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MediaServiceInterface::class, MediaServicePlaceholder::class);
        $this->app->bind(CacheServiceInterface::class, CacheService::class);
        $this->app->bind(AuditServiceInterface::class, AuditService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
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
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = strtolower((string) $request->string('email'));

            return Limit::perMinute(5)->by($email !== '' ? 'admin-login|'.$email : 'admin-login|'.$request->ip());
        });

        RateLimiter::for('public-form', function (Request $request): Limit {
            return Limit::perMinute(20)->by('public-form|'.$request->ip());
        });
    }
}
