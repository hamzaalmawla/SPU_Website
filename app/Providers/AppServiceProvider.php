<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Auth\TotpAuthenticatorInterface;
use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\PersonServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Contracts\Homepage\HomepagePreviewAssemblerInterface;
use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Legacy\LegacyClassificationReportServiceInterface;
use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\Contracts\Legacy\LegacyDecisionPlanServiceInterface;
use App\Contracts\Legacy\LegacyFacultyProfileImportServiceInterface;
use App\Contracts\Legacy\LegacyFileInventoryServiceInterface;
use App\Contracts\Legacy\LegacyGeneratedUrlInventoryServiceInterface;
use App\Contracts\Legacy\LegacyImportBatchServiceInterface;
use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use App\Contracts\Legacy\LegacyImportModuleRegistryInterface;
use App\Contracts\Legacy\LegacyImportModuleRunnerInterface;
use App\Contracts\Legacy\LegacyImportRunnerServiceInterface;
use App\Contracts\Legacy\LegacyIntegrityInspectionServiceInterface;
use App\Contracts\Legacy\LegacyInternalLinkExtractionServiceInterface;
use App\Contracts\Legacy\LegacyLocationImportServiceInterface;
use App\Contracts\Legacy\LegacyMappingProposalServiceInterface;
use App\Contracts\Legacy\LegacyNewsImportReviewServiceInterface;
use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\Contracts\Legacy\LegacyNewsSlugCleanupApplyServiceInterface;
use App\Contracts\Legacy\LegacyNewsSlugCleanupPlannerServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixApprovalServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixCandidateServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixMenuLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixPageImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixRestoreServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixSettingsImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixSettingsMappingServiceInterface;
use App\Contracts\Legacy\LegacyQuarantineExportServiceInterface;
use App\Contracts\Legacy\LegacyQuarantineSummaryServiceInterface;
use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\Contracts\Legacy\LegacyRedirectEvidenceServiceInterface;
use App\Contracts\Legacy\LegacyResearchPublicationImportServiceInterface;
use App\Contracts\Legacy\LegacyReviewCandidateReportServiceInterface;
use App\Contracts\Legacy\LegacyStagingReviewServiceInterface;
use App\Contracts\Legacy\LegacyStagingSummaryServiceInterface;
use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use App\Contracts\Legacy\LegacyUrlContinuityInventoryServiceInterface;
use App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
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
use App\Contracts\Page\ProfilePageServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
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
use App\Models\Form\DynamicFormSubmission;
use App\Models\Media\MediaAsset;
use App\Models\Navigation\MenuItem;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\Page\AboutPage;
use App\Models\Page\Page;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use App\Observers\AboutDomainAuditObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\DynamicFormSubmissionPolicy;
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
use App\Services\Cms\AboutEntityCmsService;
use App\Services\Cms\CmsTargetRegistry;
use App\Services\Cms\CmsWorkflowService;
use App\Services\Content\PersonService;
use App\Services\Content\ProfileAdminService;
use App\Services\Faculty\FacultyStudyPlanLinkService;
use App\Services\Form\DynamicFormSubmissionService;
use App\Services\Homepage\HomepageDraftReader;
use App\Services\Homepage\HomepagePreviewAssembler;
use App\Services\Homepage\HomepagePublishingService;
use App\Services\Homepage\HomepageSectionService;
use App\Services\Homepage\HomepageSectionValidator;
use App\Services\Legacy\LegacyClassificationReportService;
use App\Services\Legacy\LegacyCleanedRowService;
use App\Services\Legacy\LegacyCleaningInspectionService;
use App\Services\Legacy\LegacyContentCleaningService;
use App\Services\Legacy\LegacyDecisionPlanService;
use App\Services\Legacy\LegacyFacultyProfileImportService;
use App\Services\Legacy\LegacyFileInventoryService;
use App\Services\Legacy\LegacyGeneratedUrlInventoryService;
use App\Services\Legacy\LegacyImportBatchService;
use App\Services\Legacy\LegacyImportInspectionService;
use App\Services\Legacy\LegacyImportModuleRegistry;
use App\Services\Legacy\LegacyImportRunnerService;
use App\Services\Legacy\LegacyIntegrityInspectionService;
use App\Services\Legacy\LegacyInternalLinkExtractionService;
use App\Services\Legacy\LegacyLocationImportService;
use App\Services\Legacy\LegacyMappingProposalService;
use App\Services\Legacy\LegacyNewsImportReviewService;
use App\Services\Legacy\LegacyNewsImportService;
use App\Services\Legacy\LegacyNewsSlugCleanupApplyService;
use App\Services\Legacy\LegacyNewsSlugCleanupPlannerService;
use App\Services\Legacy\LegacyPhaseSixApprovalService;
use App\Services\Legacy\LegacyPhaseSixCandidateService;
use App\Services\Legacy\LegacyPhaseSixMenuLinkImportService;
use App\Services\Legacy\LegacyPhaseSixPageImportService;
use App\Services\Legacy\LegacyPhaseSixRestoreService;
use App\Services\Legacy\LegacyPhaseSixSettingsImportService;
use App\Services\Legacy\LegacyPhaseSixSettingsMappingService;
use App\Services\Legacy\LegacyQuarantineExportService;
use App\Services\Legacy\LegacyQuarantineSummaryService;
use App\Services\Legacy\LegacyQueryRedirectResolver;
use App\Services\Legacy\LegacyQueryResolverRegistry;
use App\Services\Legacy\LegacyRedirectEvidenceService;
use App\Services\Legacy\LegacyResearchPublicationImportService;
use App\Services\Legacy\LegacyReviewCandidateReportService;
use App\Services\Legacy\LegacyStagingReviewService;
use App\Services\Legacy\LegacyStagingSummaryService;
use App\Services\Legacy\LegacyStudentProfileImportService;
use App\Services\Legacy\LegacyUrlContinuityInventoryService;
use App\Services\Legacy\LegacyUrlContinuityTriageService;
use App\Services\Legacy\LegacyUrlNormalizer;
use App\Services\Legacy\ModuleRunners\LegacyLinksImportModuleRunner;
use App\Services\Legacy\QueryResolvers\LegacyNewsQueryResolver;
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
use App\Services\Page\ProfilePageService;
use App\Services\Page\VirtualTourPageService;
use App\Services\Preview\PreviewService;
use App\Services\Preview\PreviewTokenStore;
use App\Services\Research\ResearchPageService;
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
        Gate::policy(DynamicFormSubmission::class, DynamicFormSubmissionPolicy::class);
        Gate::policy(NewsArticle::class, NewsArticlePolicy::class);
        Gate::policy(NewsCategory::class, NewsCategoryPolicy::class);
        Gate::policy(Faculty::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyMember::class, FacultyDomainPolicy::class);
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
            AboutEntityCmsServiceInterface::class => AboutEntityCmsService::class,
            AdmissionsPageServiceInterface::class => AdmissionsPageService::class,
            AboutPageServiceInterface::class => AboutPageService::class,
            CampusLifePageServiceInterface::class => CampusLifePageService::class,
            AuditServiceInterface::class => AuditService::class,
            AuthServiceInterface::class => AuthService::class,
            CmsTargetRegistryInterface::class => CmsTargetRegistry::class,
            CmsWorkflowServiceInterface::class => CmsWorkflowService::class,
            ContinuityServiceInterface::class => ContinuityService::class,
            ContactPageServiceInterface::class => ContactPageService::class,
            DynamicFormSubmissionServiceInterface::class => DynamicFormSubmissionService::class,
            EServicesPageServiceInterface::class => EServicesPageService::class,
            FacultyStudyPlanLinkServiceInterface::class => FacultyStudyPlanLinkService::class,
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
            LegacyCleanedRowServiceInterface::class => LegacyCleanedRowService::class,
            LegacyClassificationReportServiceInterface::class => LegacyClassificationReportService::class,
            LegacyDecisionPlanServiceInterface::class => LegacyDecisionPlanService::class,
            LegacyFacultyProfileImportServiceInterface::class => LegacyFacultyProfileImportService::class,
            LegacyCleaningInspectionServiceInterface::class => LegacyCleaningInspectionService::class,
            LegacyContentCleaningServiceInterface::class => LegacyContentCleaningService::class,
            LegacyFileInventoryServiceInterface::class => LegacyFileInventoryService::class,
            LegacyGeneratedUrlInventoryServiceInterface::class => LegacyGeneratedUrlInventoryService::class,
            LegacyIntegrityInspectionServiceInterface::class => LegacyIntegrityInspectionService::class,
            LegacyInternalLinkExtractionServiceInterface::class => LegacyInternalLinkExtractionService::class,
            LegacyImportBatchServiceInterface::class => LegacyImportBatchService::class,
            LegacyImportInspectionServiceInterface::class => LegacyImportInspectionService::class,
            LegacyLocationImportServiceInterface::class => LegacyLocationImportService::class,
            LegacyMappingProposalServiceInterface::class => LegacyMappingProposalService::class,
            LegacyNewsImportServiceInterface::class => LegacyNewsImportService::class,
            LegacyPhaseSixCandidateServiceInterface::class => LegacyPhaseSixCandidateService::class,
            LegacyPhaseSixApprovalServiceInterface::class => LegacyPhaseSixApprovalService::class,
            LegacyPhaseSixMenuLinkImportServiceInterface::class => LegacyPhaseSixMenuLinkImportService::class,
            LegacyPhaseSixPageImportServiceInterface::class => LegacyPhaseSixPageImportService::class,
            LegacyPhaseSixRestoreServiceInterface::class => LegacyPhaseSixRestoreService::class,
            LegacyPhaseSixSettingsImportServiceInterface::class => LegacyPhaseSixSettingsImportService::class,
            LegacyPhaseSixSettingsMappingServiceInterface::class => LegacyPhaseSixSettingsMappingService::class,
            LegacyStudentProfileImportServiceInterface::class => LegacyStudentProfileImportService::class,
            LegacyImportModuleRegistryInterface::class => LegacyImportModuleRegistry::class,
            LegacyImportModuleRunnerInterface::class => LegacyLinksImportModuleRunner::class,
            LegacyImportRunnerServiceInterface::class => LegacyImportRunnerService::class,
            LegacyQueryModuleResolverInterface::class => LegacyNewsQueryResolver::class,
            LegacyQueryRedirectResolverInterface::class => LegacyQueryRedirectResolver::class,
            LegacyQueryResolverRegistryInterface::class => LegacyQueryResolverRegistry::class,
            LegacyQuarantineExportServiceInterface::class => LegacyQuarantineExportService::class,
            LegacyQuarantineSummaryServiceInterface::class => LegacyQuarantineSummaryService::class,
            LegacyRedirectEvidenceServiceInterface::class => LegacyRedirectEvidenceService::class,
            LegacyReviewCandidateReportServiceInterface::class => LegacyReviewCandidateReportService::class,
            LegacyResearchPublicationImportServiceInterface::class => LegacyResearchPublicationImportService::class,
            LegacyStagingReviewServiceInterface::class => LegacyStagingReviewService::class,
            LegacyStagingSummaryServiceInterface::class => LegacyStagingSummaryService::class,
            LegacyNewsSlugCleanupApplyServiceInterface::class => LegacyNewsSlugCleanupApplyService::class,
            LegacyNewsSlugCleanupPlannerServiceInterface::class => LegacyNewsSlugCleanupPlannerService::class,
            LegacyNewsImportReviewServiceInterface::class => LegacyNewsImportReviewService::class,
            LegacyUrlContinuityInventoryServiceInterface::class => LegacyUrlContinuityInventoryService::class,
            LegacyUrlContinuityTriageServiceInterface::class => LegacyUrlContinuityTriageService::class,
            LegacyUrlNormalizerInterface::class => LegacyUrlNormalizer::class,
            HomepagePublishingServiceInterface::class => HomepagePublishingService::class,
            PreviewServiceInterface::class => PreviewService::class,
            PageServiceInterface::class => PageService::class,
            ProfilePageServiceInterface::class => ProfilePageService::class,
            VirtualTourPageServiceInterface::class => VirtualTourPageService::class,
            PersonServiceInterface::class => PersonService::class,
            ProfileAdminServiceInterface::class => ProfileAdminService::class,
            ResearchPageServiceInterface::class => ResearchPageService::class,
            SettingsServiceInterface::class => SettingsService::class,
            NavigationServiceInterface::class => NavigationService::class,
            TotpAuthenticatorInterface::class => TotpAuthenticator::class,
        ];
    }
}
