<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AuditServiceInterface;
use App\Contracts\AuthServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\ContinuityServiceInterface;
use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\MediaServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\Contracts\SitemapServiceInterface;
use App\Contracts\SlugServiceInterface;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Policies\HomepagePolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\PagePolicy;
use App\Policies\UserPolicy;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CacheService;
use App\Services\ContinuityService;
use App\Services\HomepagePublishingService;
use App\Services\HomepageSectionService;
use App\Services\MenuService;
use App\Services\NavigationService;
use App\Services\PageService;
use App\Services\Placeholders\MediaServicePlaceholder;
use App\Services\Placeholders\SlugServicePlaceholder;
use App\Services\PreviewService;
use App\Services\SeoMetadataService;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $this->registerFoundationBindings();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();
        $this->configureRateLimiting();
    }

    private function registerAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(MenuItem::class, MenuItemPolicy::class);

        Gate::define('manage-users', [UserPolicy::class, 'manageUsers']);
        Gate::define('manage-settings', [UserPolicy::class, 'manageSettings']);
        Gate::define('manage-homepage', [HomepagePolicy::class, 'manage']);
        Gate::define('manage-pages', [PagePolicy::class, 'manage']);
        Gate::define('manage-menu', [MenuItemPolicy::class, 'manage']);
        Gate::define('manage-media', [UserPolicy::class, 'manageMedia']);
        Gate::define('publish-content', [UserPolicy::class, 'publishContent']);
        Gate::define('preview-content', [UserPolicy::class, 'previewContent']);
        Gate::define('view-audit-log', [UserPolicy::class, 'viewAuditLog']);
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

    private function registerFoundationBindings(): void
    {
        foreach ($this->resolvedBindings() as $contract => $implementation) {
            $this->app->singleton($contract, $implementation);
        }

        foreach ($this->intentionalPlaceholderBindings() as $contract => $implementation) {
            $this->app->singleton($contract, $implementation);
        }
    }

    /**
     * @return array<class-string, class-string>
     */
    private function resolvedBindings(): array
    {
        return [
            CacheServiceInterface::class => CacheService::class,
            AuditServiceInterface::class => AuditService::class,
            AuthServiceInterface::class => AuthService::class,
            ContinuityServiceInterface::class => ContinuityService::class,
            SitemapServiceInterface::class => SitemapService::class,
        ];
    }

    /**
     * @return array<class-string, class-string>
     */
    private function intentionalPlaceholderBindings(): array
    {
        return [
            MediaServiceInterface::class => MediaServicePlaceholder::class,
            MenuServiceInterface::class => MenuService::class,
            SlugServiceInterface::class => SlugServicePlaceholder::class,
            SeoMetadataServiceInterface::class => SeoMetadataService::class,
            HomepageSectionServiceInterface::class => HomepageSectionService::class,
            HomepagePublishingServiceInterface::class => HomepagePublishingService::class,
            PreviewServiceInterface::class => PreviewService::class,
            PageServiceInterface::class => PageService::class,
            SettingsServiceInterface::class => SettingsService::class,
            NavigationServiceInterface::class => NavigationService::class,
        ];
    }
}
