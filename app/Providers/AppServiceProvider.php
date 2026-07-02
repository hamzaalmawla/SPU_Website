<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Auth\TotpAuthenticatorInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\PersonServiceInterface;
use App\Contracts\Homepage\HomepagePreviewAssemblerInterface;
use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\ContinuityServiceInterface;
use App\Contracts\Shared\PreviewServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Http\Responses\LogoutResponse;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Contact\ContactMessage;
use App\Models\Content\Directorate;
use App\Models\Content\Partnership;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\Media\MediaAsset;
use App\Models\Navigation\MenuItem;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\Page\AboutPage;
use App\Models\Page\Page;
use App\Models\Person\Person;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use App\Observers\AboutDomainAuditObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\FacultyDomainPolicy;
use App\Policies\HomepagePolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\NewsArticlePolicy;
use App\Policies\NewsCategoryPolicy;
use App\Policies\PagePolicy;
use App\Policies\UserPolicy;
use App\Services\Auth\AuthService;
use App\Services\Auth\TotpAuthenticator;
use App\Services\Cms\CmsTargetRegistry;
use App\Services\Cms\CmsWorkflowService;
use App\Services\Content\PersonService;
use App\Services\Homepage\HomepageDraftReader;
use App\Services\Homepage\HomepagePreviewAssembler;
use App\Services\Homepage\HomepagePublishingService;
use App\Services\Homepage\HomepageSectionService;
use App\Services\Homepage\HomepageSectionValidator;
use App\Services\Media\MediaFileValidator;
use App\Services\Media\MediaService;
use App\Services\Navigation\MenuService;
use App\Services\Navigation\NavigationService;
use App\Services\News\NewsAdminWorkflowService;
use App\Services\News\NewsService;
use App\Services\Page\AboutPageService;
use App\Services\Page\AdmissionsPageService;
use App\Services\Page\CampusLifePageService;
use App\Services\Page\ContactPageService;
use App\Services\Page\EServicesPageService;
use App\Services\Page\FacultyPageService;
use App\Services\Page\PageDraftService;
use App\Services\Page\PagePublicReadService;
use App\Services\Page\PagePublishabilityValidator;
use App\Services\Page\PageService;
use App\Services\Page\PageUrlResolver;
use App\Services\Page\VirtualTourPageService;
use App\Services\Preview\PreviewService;
use App\Services\Preview\PreviewTokenStore;
use App\Services\Seo\SeoMetadataService;
use App\Services\Seo\SitemapService;
use App\Services\Settings\SettingsService;
use App\Services\Shared\AuditService;
use App\Services\Shared\CacheService;
use App\Services\Shared\ContinuityService;
use App\Services\Shared\SlugService;
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
        $this->app->singleton(MediaFileValidator::class);
        $this->app->singleton(PagePublicReadService::class);
        $this->app->singleton(PageDraftService::class);
        $this->app->singleton(PageUrlResolver::class);
        $this->app->singleton(PagePublishabilityValidator::class);
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
        Gate::policy(NewsArticle::class, NewsArticlePolicy::class);
        Gate::policy(NewsCategory::class, NewsCategoryPolicy::class);
        Gate::policy(Faculty::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyPage::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyHighlight::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyLab::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyStudentProject::class, FacultyDomainPolicy::class);
        Gate::policy(Alumni::class, FacultyDomainPolicy::class);
        Gate::policy(HonorStudent::class, FacultyDomainPolicy::class);

        Gate::define('manage-users', [UserPolicy::class, 'manageUsers']);
        Gate::define('manage-settings', [UserPolicy::class, 'manageSettings']);
        Gate::define('manage-homepage', [HomepagePolicy::class, 'manage']);
        Gate::define('manage-pages', [PagePolicy::class, 'manage']);
        Gate::define('manage-menu', [MenuItemPolicy::class, 'manage']);
        Gate::define('manage-news', [NewsArticlePolicy::class, 'manage']);
        Gate::define('manage-faculties', [FacultyDomainPolicy::class, 'manage']);
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
            AdmissionsPageServiceInterface::class => AdmissionsPageService::class,
            AboutPageServiceInterface::class => AboutPageService::class,
            CampusLifePageServiceInterface::class => CampusLifePageService::class,
            AuditServiceInterface::class => AuditService::class,
            AuthServiceInterface::class => AuthService::class,
            CmsTargetRegistryInterface::class => CmsTargetRegistry::class,
            CmsWorkflowServiceInterface::class => CmsWorkflowService::class,
            ContinuityServiceInterface::class => ContinuityService::class,
            ContactPageServiceInterface::class => ContactPageService::class,
            EServicesPageServiceInterface::class => EServicesPageService::class,
            FacultyPageServiceInterface::class => FacultyPageService::class,
            SitemapServiceInterface::class => SitemapService::class,
            MediaServiceInterface::class => MediaService::class,
            SlugServiceInterface::class => SlugService::class,
            MenuServiceInterface::class => MenuService::class,
            NewsAdminWorkflowServiceInterface::class => NewsAdminWorkflowService::class,
            NewsServiceInterface::class => NewsService::class,
            SeoMetadataServiceInterface::class => SeoMetadataService::class,
            HomepagePreviewAssemblerInterface::class => HomepagePreviewAssembler::class,
            HomepageSectionServiceInterface::class => HomepageSectionService::class,
            HomepagePublishingServiceInterface::class => HomepagePublishingService::class,
            PreviewServiceInterface::class => PreviewService::class,
            PageServiceInterface::class => PageService::class,
            VirtualTourPageServiceInterface::class => VirtualTourPageService::class,
            PersonServiceInterface::class => PersonService::class,
            SettingsServiceInterface::class => SettingsService::class,
            NavigationServiceInterface::class => NavigationService::class,
            TotpAuthenticatorInterface::class => TotpAuthenticator::class,
        ];
    }
}
