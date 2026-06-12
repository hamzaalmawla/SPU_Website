<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AuditServiceInterface;
use App\Contracts\AuthServiceInterface;
use App\Contracts\AboutPageServiceInterface;
use App\Contracts\CacheServiceInterface;
use App\Contracts\ContinuityServiceInterface;
use App\Contracts\ContactPageServiceInterface;
use App\Contracts\EServicesPageServiceInterface;
use App\Contracts\HomepagePublishingServiceInterface;
use App\Contracts\HomepageSectionServiceInterface;
use App\Contracts\MediaServiceInterface;
use App\Contracts\MenuServiceInterface;
use App\Contracts\NavigationServiceInterface;
use App\Contracts\PageServiceInterface;
use App\Contracts\PersonServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\Contracts\SeoMetadataServiceInterface;
use App\Contracts\SettingsServiceInterface;
use App\Contracts\SitemapServiceInterface;
use App\Contracts\SlugServiceInterface;
use App\Contracts\TotpAuthenticatorInterface;
use App\Http\Responses\LogoutResponse;
use App\Models\AuditLog;
use App\Models\AboutPage;
use App\Models\ContactMessage;
use App\Models\Directorate;
use App\Models\MediaAsset;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partnership;
use App\Models\Person;
use App\Models\User;
use App\Observers\AboutDomainAuditObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\HomepagePolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\PagePolicy;
use App\Policies\UserPolicy;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AboutPageService;
use App\Services\CacheService;
use App\Services\ContinuityService;
use App\Services\ContactPageService;
use App\Services\EServicesPageService;
use App\Services\HomepageDraftReader;
use App\Services\HomepagePublishingService;
use App\Services\HomepageSectionService;
use App\Services\HomepageSectionValidator;
use App\Services\MediaService;
use App\Services\MenuService;
use App\Services\NavigationService;
use App\Services\PageDraftService;
use App\Services\PagePublicReadService;
use App\Services\PageService;
use App\Services\PageUrlResolver;
use App\Services\PersonService;
use App\Services\PreviewService;
use App\Services\PreviewTokenStore;
use App\Services\SeoMetadataService;
use App\Services\SettingsService;
use App\Services\SitemapService;
use App\Services\SlugService;
use App\Services\TotpAuthenticator;
use App\Support\HtmlSanitizer;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
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

        $this->app->singleton(HtmlSanitizer::class);
        $this->app->singleton(PreviewTokenStore::class);
        $this->app->singleton(HomepageDraftReader::class);
        $this->app->singleton(HomepageSectionValidator::class);
        $this->app->singleton(PagePublicReadService::class);
        $this->app->singleton(PageDraftService::class);
        $this->app->singleton(PageUrlResolver::class);
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->enforceProductionSecurityConfiguration();
        $this->registerModelObservers();
        $this->registerAuthorization();
        $this->configureRateLimiting();
    }

    private function registerModelObservers(): void
    {
        AboutPage::observe(AboutDomainAuditObserver::class);
        Directorate::observe(AboutDomainAuditObserver::class);
        Partnership::observe(AboutDomainAuditObserver::class);
        Person::observe(AboutDomainAuditObserver::class);
    }

    private function enforceProductionSecurityConfiguration(): void
    {
        if (! $this->isExplicitProductionEnvironment()) {
            return;
        }

        $failures = [];

        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false';
        }

        $appKey = (string) config('app.key');

        if ($appKey === '' || str_contains($appKey, 'REPLACE_WITH')) {
            $failures[] = 'APP_KEY must be a unique production key';
        }

        if ((bool) config('session.encrypt') !== true) {
            $failures[] = 'SESSION_ENCRYPT must be true';
        }

        if ((bool) config('session.secure') !== true) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true';
        }

        if ($failures !== []) {
            throw new \RuntimeException('Invalid production security configuration: '.implode('; ', $failures));
        }
    }

    private function isExplicitProductionEnvironment(): bool
    {
        if (! $this->app->environment('production')) {
            return false;
        }

        return (string) env('APP_ENV') === 'production' || $this->app->configurationIsCached();
    }

    private function registerAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(MenuItem::class, MenuItemPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);

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

        RateLimiter::for('two-factor', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(5)->by('two-factor|'.($userId !== null ? (string) $userId : $request->ip()));
        });
    }

    private function registerFoundationBindings(): void
    {
        foreach ($this->resolvedBindings() as $contract => $implementation) {
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
            AboutPageServiceInterface::class => AboutPageService::class,
            AuditServiceInterface::class => AuditService::class,
            AuthServiceInterface::class => AuthService::class,
            ContinuityServiceInterface::class => ContinuityService::class,
            ContactPageServiceInterface::class => ContactPageService::class,
            EServicesPageServiceInterface::class => EServicesPageService::class,
            SitemapServiceInterface::class => SitemapService::class,
            MediaServiceInterface::class => MediaService::class,
            SlugServiceInterface::class => SlugService::class,
            MenuServiceInterface::class => MenuService::class,
            SeoMetadataServiceInterface::class => SeoMetadataService::class,
            HomepageSectionServiceInterface::class => HomepageSectionService::class,
            HomepagePublishingServiceInterface::class => HomepagePublishingService::class,
            PreviewServiceInterface::class => PreviewService::class,
            PageServiceInterface::class => PageService::class,
            PersonServiceInterface::class => PersonService::class,
            SettingsServiceInterface::class => SettingsService::class,
            NavigationServiceInterface::class => NavigationService::class,
            TotpAuthenticatorInterface::class => TotpAuthenticator::class,
        ];
    }
}
