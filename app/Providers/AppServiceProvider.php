<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\Contracts\Analytics\AnalyticsServiceInterface;
use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Auth\PermissionCatalogInterface;
use App\Contracts\Auth\RoleAdminServiceInterface;
use App\Contracts\Auth\TotpAuthenticatorInterface;
use App\Contracts\Career\AlumniDirectoryServiceInterface;
use App\Contracts\Career\StudentListImportServiceInterface;
use App\Contracts\Cms\AboutEntityCmsServiceInterface;
use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\FacultyMemberActivityReportServiceInterface;
use App\Contracts\Content\PeopleDuplicateReportServiceInterface;
use App\Contracts\Content\PeopleMergeServiceInterface;
use App\Contracts\Content\PersonServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\Contracts\ErrorPage\ErrorPageRendererInterface;
use App\Contracts\ErrorPage\ErrorPageServiceInterface;
use App\Contracts\Faculty\FacultyStructureImportServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanBoardServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanImportServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Faculty\LegacyCourseSpecificationImportServiceInterface;
use App\Contracts\Faculty\ProjectFieldBlockParserInterface;
use App\Contracts\Form\ContactMessageReviewServiceInterface;
use App\Contracts\Form\DynamicFormSubmissionReviewServiceInterface;
use App\Contracts\Form\DynamicFormSubmissionServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\Form\FormSubmissionNotificationServiceInterface;
use App\Contracts\Homepage\HomepageContentSelectionServiceInterface;
use App\Contracts\Homepage\HomepagePreviewAssemblerInterface;
use App\Contracts\Homepage\HomepagePublishingServiceInterface;
use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\Contracts\Legacy\LegacyCareerLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyCareerLinkReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyCategoryMatrixExporterInterface;
use App\Contracts\Legacy\LegacyCategoryReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyCentralCouncilImportServiceInterface;
use App\Contracts\Legacy\LegacyClassificationReportServiceInterface;
use App\Contracts\Legacy\LegacyCleanedRowServiceInterface;
use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\Contracts\Legacy\LegacyDecisionPlanServiceInterface;
use App\Contracts\Legacy\LegacyFacultyProfileImportServiceInterface;
use App\Contracts\Legacy\LegacyFacultyProjectImportServiceInterface;
use App\Contracts\Legacy\LegacyFacultyResearchImportServiceInterface;
use App\Contracts\Legacy\LegacyFaqApprovalPacketServiceInterface;
use App\Contracts\Legacy\LegacyFaqImportServiceInterface;
use App\Contracts\Legacy\LegacyFaqReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyFileContinuityProbeServiceInterface;
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
use App\Contracts\Legacy\LegacyMembersReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyNewsApprovalPacketServiceInterface;
use App\Contracts\Legacy\LegacyNewsImportReviewServiceInterface;
use App\Contracts\Legacy\LegacyNewsImportServiceInterface;
use App\Contracts\Legacy\LegacyNewsPublicationServiceInterface;
use App\Contracts\Legacy\LegacyNewsSlugCleanupApplyServiceInterface;
use App\Contracts\Legacy\LegacyNewsSlugCleanupPlannerServiceInterface;
use App\Contracts\Legacy\LegacyPageContentServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixApprovalServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixCandidateServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixMenuLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixPageImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixRestoreServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixSettingsImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixSettingsMappingServiceInterface;
use App\Contracts\Legacy\LegacyPublicStaffApprovalPacketServiceInterface;
use App\Contracts\Legacy\LegacyPublicStaffImportServiceInterface;
use App\Contracts\Legacy\LegacyPublicStaffReviewPacketServiceInterface;
use App\Contracts\Legacy\LegacyQuarantineExportServiceInterface;
use App\Contracts\Legacy\LegacyQuarantineSummaryServiceInterface;
use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\Contracts\Legacy\LegacyQueryRedirectResolverInterface;
use App\Contracts\Legacy\LegacyQueryResolverRegistryInterface;
use App\Contracts\Legacy\LegacyRedirectDecisionServiceInterface;
use App\Contracts\Legacy\LegacyRedirectEvidenceServiceInterface;
use App\Contracts\Legacy\LegacyResearchMetadataExtractorInterface;
use App\Contracts\Legacy\LegacyResearchPublicationImportServiceInterface;
use App\Contracts\Legacy\LegacyResearchPublicationPublishingServiceInterface;
use App\Contracts\Legacy\LegacyReviewCandidateReportServiceInterface;
use App\Contracts\Legacy\LegacyStagingReviewServiceInterface;
use App\Contracts\Legacy\LegacyStagingSummaryServiceInterface;
use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use App\Contracts\Legacy\LegacyUrlContinuityInventoryServiceInterface;
use App\Contracts\Legacy\LegacyUrlContinuityTriageServiceInterface;
use App\Contracts\Legacy\LegacyUrlNormalizerInterface;
use App\Contracts\Media\ImageConversionServiceInterface;
use App\Contracts\Media\LegacyImageDerivativeServiceInterface;
use App\Contracts\Media\LegacyPersonPhotoLibraryServiceInterface;
use App\Contracts\Media\MediaDisplaySettingsServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Contracts\Navigation\MenuServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Navigation\PageLinkOptionServiceInterface;
use App\Contracts\News\LegacyCalendarEventImportServiceInterface;
use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Contracts\News\NewsArticleEventCmsServiceInterface;
use App\Contracts\News\NewsArticleEventServiceInterface;
use App\Contracts\News\NewsCalendarEventMoveServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Page\AboutNavigationCardServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\AdmissionsTuitionFeeImportServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Page\FacultySubpageCardServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Page\ProfilePageServiceInterface;
use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Contracts\Research\ResearchPublicationCmsServiceInterface;
use App\Contracts\Research\ResearchRegisterImportServiceInterface;
use App\Contracts\Search\SearchIndexServiceInterface;
use App\Contracts\Search\SiteSearchServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\SitemapServiceInterface;
use App\Contracts\Seo\StructuredDataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Contracts\Shared\ContinuityServiceInterface;
use App\Contracts\Shared\PlainTextFormatterInterface;
use App\Contracts\Shared\PreviewServiceInterface;
use App\Contracts\Shared\SlugServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\Contracts\Staff\StaffIntakeServiceInterface;
use App\DTOs\ErrorPage\ErrorPageContentDTO;
use App\DTOs\Homepage\HomepageSectionDTO;
use App\Http\Responses\LogoutResponse;
use App\Models\Achievement\Achievement;
use App\Models\Achievement\AchievementCategory;
use App\Models\Achievement\AchievementCategoryTranslation;
use App\Models\Achievement\AchievementTranslation;
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
use App\Models\Faculty\FacultySubpageCard;
use App\Models\Form\DynamicFormSubmission;
use App\Models\Form\FormDefinition;
use App\Models\Media\MediaAsset;
use App\Models\Navigation\MenuItem;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\Page\AboutPage;
use App\Models\Page\Page;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\ResearchPublication;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use App\Observers\AboutDomainAuditObserver;
use App\Observers\AchievementDomainObserver;
use App\Observers\SearchIndexObserver;
use App\Policies\AchievementCategoryPolicy;
use App\Policies\AchievementPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\DynamicFormSubmissionPolicy;
use App\Policies\FacultyDomainPolicy;
use App\Policies\FacultyMemberPolicy;
use App\Policies\FacultySubpageCardPolicy;
use App\Policies\FormDefinitionPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\MenuItemPolicy;
use App\Policies\NewsArticlePolicy;
use App\Policies\NewsCategoryPolicy;
use App\Policies\PagePolicy;
use App\Policies\ResearchPublicationPolicy;
use App\Policies\UserPolicy;
use App\Services\Achievement\AchievementService;
use App\Services\Analytics\AnalyticsService;
use App\Services\Auth\AdminAccessService;
use App\Services\Auth\AuthService;
use App\Services\Auth\PermissionCatalog;
use App\Services\Auth\RoleAdminService;
use App\Services\Auth\TotpAuthenticator;
use App\Services\Career\AlumniDirectoryService;
use App\Services\Career\StudentListImportService;
use App\Services\Cms\AboutEntityCmsService;
use App\Services\Cms\CmsPageTextImportService;
use App\Services\Cms\CmsTargetRegistry;
use App\Services\Cms\CmsWorkflowService;
use App\Services\Content\FacultyMemberActivityReportService;
use App\Services\Content\PeopleDuplicateReportService;
use App\Services\Content\PeopleMergeService;
use App\Services\Content\PersonService;
use App\Services\Content\ProfileAdminService;
use App\Services\ErrorPage\ErrorPageRenderer;
use App\Services\ErrorPage\ErrorPageService;
use App\Services\Faculty\FacultyStructureImportService;
use App\Services\Faculty\FacultyStudyPlanBoardService;
use App\Services\Faculty\FacultyStudyPlanEditorService;
use App\Services\Faculty\FacultyStudyPlanImportService;
use App\Services\Faculty\FacultyStudyPlanLinkService;
use App\Services\Faculty\LegacyCourseSpecificationImportService;
use App\Services\Faculty\ProjectFieldBlockParser;
use App\Services\Form\ContactMessageReviewService;
use App\Services\Form\DynamicFormSubmissionReviewService;
use App\Services\Form\DynamicFormSubmissionService;
use App\Services\Form\FormDefinitionService;
use App\Services\Form\FormSubmissionNotificationService;
use App\Services\Homepage\HomepageContentSelectionService;
use App\Services\Homepage\HomepageDraftReader;
use App\Services\Homepage\HomepagePreviewAssembler;
use App\Services\Homepage\HomepagePublishingService;
use App\Services\Homepage\HomepageSectionService;
use App\Services\Homepage\HomepageSectionValidator;
use App\Services\Legacy\LegacyCareerLinkImportService;
use App\Services\Legacy\LegacyCareerLinkReviewPacketService;
use App\Services\Legacy\LegacyCategoryMatrixExporter;
use App\Services\Legacy\LegacyCategoryReviewPacketService;
use App\Services\Legacy\LegacyCentralCouncilImportService;
use App\Services\Legacy\LegacyClassificationReportService;
use App\Services\Legacy\LegacyCleanedRowService;
use App\Services\Legacy\LegacyCleaningInspectionService;
use App\Services\Legacy\LegacyContentCleaningService;
use App\Services\Legacy\LegacyDecisionPlanService;
use App\Services\Legacy\LegacyFacultyProfileImportService;
use App\Services\Legacy\LegacyFacultyProjectImportService;
use App\Services\Legacy\LegacyFacultyResearchImportService;
use App\Services\Legacy\LegacyFaqApprovalPacketService;
use App\Services\Legacy\LegacyFaqImportService;
use App\Services\Legacy\LegacyFaqReviewPacketService;
use App\Services\Legacy\LegacyFileContinuityProbeService;
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
use App\Services\Legacy\LegacyMembersReviewPacketService;
use App\Services\Legacy\LegacyNewsApprovalPacketService;
use App\Services\Legacy\LegacyNewsImportReviewService;
use App\Services\Legacy\LegacyNewsImportService;
use App\Services\Legacy\LegacyNewsPublicationService;
use App\Services\Legacy\LegacyNewsSlugCleanupApplyService;
use App\Services\Legacy\LegacyNewsSlugCleanupPlannerService;
use App\Services\Legacy\LegacyPageContentService;
use App\Services\Legacy\LegacyPhaseSixApprovalService;
use App\Services\Legacy\LegacyPhaseSixCandidateService;
use App\Services\Legacy\LegacyPhaseSixMenuLinkImportService;
use App\Services\Legacy\LegacyPhaseSixPageImportService;
use App\Services\Legacy\LegacyPhaseSixRestoreService;
use App\Services\Legacy\LegacyPhaseSixSettingsImportService;
use App\Services\Legacy\LegacyPhaseSixSettingsMappingService;
use App\Services\Legacy\LegacyPublicStaffApprovalPacketService;
use App\Services\Legacy\LegacyPublicStaffImportService;
use App\Services\Legacy\LegacyPublicStaffReviewPacketService;
use App\Services\Legacy\LegacyQuarantineExportService;
use App\Services\Legacy\LegacyQuarantineSummaryService;
use App\Services\Legacy\LegacyQueryRedirectResolver;
use App\Services\Legacy\LegacyQueryResolverRegistry;
use App\Services\Legacy\LegacyRedirectDecisionService;
use App\Services\Legacy\LegacyRedirectEvidenceService;
use App\Services\Legacy\LegacyResearchMetadataExtractor;
use App\Services\Legacy\LegacyResearchPublicationImportService;
use App\Services\Legacy\LegacyResearchPublicationPublishingService;
use App\Services\Legacy\LegacyReviewCandidateReportService;
use App\Services\Legacy\LegacyStagingReviewService;
use App\Services\Legacy\LegacyStagingSummaryService;
use App\Services\Legacy\LegacyStudentProfileImportService;
use App\Services\Legacy\LegacyUrlContinuityInventoryService;
use App\Services\Legacy\LegacyUrlContinuityTriageService;
use App\Services\Legacy\LegacyUrlNormalizer;
use App\Services\Legacy\ModuleRunners\LegacyLinksImportModuleRunner;
use App\Services\Legacy\QueryResolvers\LegacyNewsQueryResolver;
use App\Services\Media\ImageConversionService;
use App\Services\Media\LegacyImageDerivativeService;
use App\Services\Media\LegacyPersonPhotoLibraryService;
use App\Services\Media\MediaDisplaySettingsService;
use App\Services\Media\MediaFileValidator;
use App\Services\Media\MediaService;
use App\Services\Navigation\MenuService;
use App\Services\Navigation\NavigationService;
use App\Services\Navigation\PageLinkOptionService;
use App\Services\News\LegacyCalendarEventImportService;
use App\Services\News\NewsAdminWorkflowService;
use App\Services\News\NewsArticleCmsService;
use App\Services\News\NewsArticleEventCmsService;
use App\Services\News\NewsArticleEventService;
use App\Services\News\NewsCalendarEventMoveService;
use App\Services\News\NewsService;
use App\Services\Page\AboutNavigationCardService;
use App\Services\Page\AboutPageService;
use App\Services\Page\AdmissionsPageService;
use App\Services\Page\AdmissionsTuitionFeeImportService;
use App\Services\Page\CampusLifePageService;
use App\Services\Page\ContactPageService;
use App\Services\Page\EServicesPageService;
use App\Services\Page\FacultyPageService;
use App\Services\Page\FacultySubpageCardService;
use App\Services\Page\PageDraftService;
use App\Services\Page\PagePublicReadService;
use App\Services\Page\PagePublishabilityValidator;
use App\Services\Page\PageService;
use App\Services\Page\PageUrlResolver;
use App\Services\Page\ProfilePageService;
use App\Services\Page\VirtualTourPageService;
use App\Services\Preview\PreviewService;
use App\Services\Preview\PreviewTokenStore;
use App\Services\Research\ResearcherAdminService;
use App\Services\Research\ResearchPageService;
use App\Services\Research\ResearchPublicationCmsService;
use App\Services\Research\ResearchRegisterImportService;
use App\Services\Search\SearchIndexService;
use App\Services\Search\SiteSearchService;
use App\Services\Seo\SeoMetadataService;
use App\Services\Seo\SitemapService;
use App\Services\Seo\StructuredDataService;
use App\Services\Settings\SettingsService;
use App\Services\Shared\AuditService;
use App\Services\Shared\CacheService;
use App\Services\Shared\ContinuityService;
use App\Services\Shared\PlainTextFormatter;
use App\Services\Shared\SlugService;
use App\Services\Staff\StaffIntakeLinkService;
use App\Services\Staff\StaffIntakeReviewService;
use App\Services\Staff\StaffIntakeService;
use App\Support\HtmlSanitizer;
use App\Support\PageExpiredDiagnostics;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;

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
        // Scoped: one admin request builds the link picker list once, however
        // many pickers its form renders.
        $this->app->scoped(PageLinkOptionServiceInterface::class, PageLinkOptionService::class);
        // Scoped: the researchers list matches every row against the staff
        // list, which is loaded once per request.
        $this->app->scoped(ResearcherAdminServiceInterface::class, ResearcherAdminService::class);
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
        \Livewire\on('exception', static function (object $component, \Throwable $exception): void {
            PageExpiredDiagnostics::livewireTypeError($component, $exception);
        });
        $this->registerPublicHomepageFooterComposer();
        $this->registerPublicAnalyticsComposer();
        $this->registerErrorPageComposer();
    }

    /**
     * Supply the analytics tag to the public layout.
     *
     * Config-only, so this adds no query and no measurable work per request.
     * The snippet is null (and the layout emits nothing) unless analytics is
     * configured, and always null inside the tokenized preview shell.
     */
    private function registerPublicAnalyticsComposer(): void
    {
        View::composer('layouts.public', function (ViewContract $view): void {
            $isPreview = ($view->getData()['isPreview'] ?? false) === true;

            $view->with('analytics', app(AnalyticsServiceInterface::class)->snippet($isPreview));
        });
    }

    /**
     * Supply $error to every error view.
     *
     * Registered for both the dotted names used by ErrorPageRenderer and the
     * `errors::` namespace Laravel resolves internally, so the views render
     * identically whether the branded renderer or the framework's own default
     * handling reaches them.
     *
     * ErrorPageService is deliberately free of database, cache and translator
     * access, so this composer is safe to run while those are unavailable —
     * which is exactly when a 500 or 503 renders.
     */
    private function registerErrorPageComposer(): void
    {
        $statuses = [403, 404, 419, 429, 500, 503];
        $views = [];

        foreach ($statuses as $status) {
            $views[] = 'errors.'.$status;
            $views[] = 'errors::'.$status;
        }

        View::composer($views, function (ViewContract $view): void {
            // ErrorPageRenderer already supplies a fully built DTO.
            if (($view->getData()['error'] ?? null) instanceof ErrorPageContentDTO) {
                return;
            }

            $status = (int) preg_replace('/\\D/', '', $view->name());
            $request = request();

            $view->with('error', app(ErrorPageServiceInterface::class)->content(
                $status > 0 ? $status : 500,
                $request->path(),
                $request->headers->get('Accept-Language'),
            ));
        });
    }

    private function registerPublicHomepageFooterComposer(): void
    {
        View::composer('layouts.public', function (ViewContract $view): void {
            $data = $view->getData();

            if (($data['isPreview'] ?? false) === true) {
                return;
            }

            if (! Schema::hasTable('homepage_sections')) {
                return;
            }

            $locale = is_string($data['locale'] ?? null) && in_array($data['locale'], ['ar', 'en'], true)
                ? $data['locale']
                : 'ar';
            $footer = $data['homepageFooterSection'] ?? null;
            if (! $footer instanceof HomepageSectionDTO) {
                $footer = app(HomepageSectionServiceInterface::class)->getPublicSectionByKey('footer', $locale);
            }
            $footerColumns = $footer?->payload !== null
                ? app(ResearchPageServiceInterface::class)->filterFooterColumns($locale, $footer->payload->footerColumns)
                : [];

            $view->with('homepageFooterSection', $footer);
            $view->with('homepageFooterColumns', $footerColumns);
        });
    }

    private function registerModelObservers(): void
    {
        Achievement::observe(AchievementDomainObserver::class);
        AchievementTranslation::observe(AchievementDomainObserver::class);
        AchievementCategory::observe(AchievementDomainObserver::class);
        AchievementCategoryTranslation::observe(AchievementDomainObserver::class);
        AboutPage::observe(AboutDomainAuditObserver::class);
        Directorate::observe(AboutDomainAuditObserver::class);
        Partnership::observe(AboutDomainAuditObserver::class);
        Person::observe(AboutDomainAuditObserver::class);

        // Publishing, unpublishing and editing all end in a model write, so
        // observing the content models keeps the derived search index in step
        // with every editorial path at once. Seeders run under
        // WithoutModelEvents, so bulk seeding does not pay for this.
        foreach (SearchIndexObserver::observedModels() as $model) {
            $model::observe(SearchIndexObserver::class);
        }
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
        Gate::policy(FormDefinition::class, FormDefinitionPolicy::class);
        Gate::policy(NewsArticle::class, NewsArticlePolicy::class);
        Gate::policy(ResearchPublication::class, ResearchPublicationPolicy::class);
        Gate::policy(NewsCategory::class, NewsCategoryPolicy::class);
        Gate::policy(Achievement::class, AchievementPolicy::class);
        Gate::policy(AchievementCategory::class, AchievementCategoryPolicy::class);
        Gate::policy(Faculty::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyMember::class, FacultyMemberPolicy::class);
        Gate::policy(FacultyPage::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyHighlight::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyLab::class, FacultyDomainPolicy::class);
        Gate::policy(FacultyStudentProject::class, FacultyDomainPolicy::class);
        Gate::policy(Alumni::class, FacultyDomainPolicy::class);
        Gate::policy(HonorStudent::class, FacultyDomainPolicy::class);
        Gate::policy(FacultySubpageCard::class, FacultySubpageCardPolicy::class);

        // One gate per catalog permission (`news_articles.update`, ...). Users,
        // roles and the permission system itself stay with the super admin.
        foreach ($this->app->make(PermissionCatalogInterface::class)->all() as $permission) {
            Gate::define($permission, fn (User $user): bool => $this->app->make(AdminAccessServiceInterface::class)->can($user, $permission));
        }

        Gate::define('manage-users', fn (User $user): bool => $user->isSuperAdmin());

        Event::listen(JobProcessing::class, function (): void {
            $this->app->make(AdminAccessServiceInterface::class)->flushPermissions();
        });
    }

    private function configureRateLimiting(): void
    {
        // Two limits apply together. The per-account one caps guesses at any
        // single email however many addresses they come from; the per-address
        // one stops a single client spraying one password across many staff
        // emails, which the per-account limit alone never sees.
        RateLimiter::for('admin-login', function (Request $request): array {
            $email = strtolower(trim((string) $request->string('email')));

            return [
                Limit::perMinute(5)->by('admin-login|account|'.($email !== '' ? $email : $request->ip())),
                Limit::perMinute(20)->by('admin-login|ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('public-form', function (Request $request): Limit {
            return Limit::perMinute(20)->by('public-form|'.$request->ip());
        });

        // Name suggestions on a staff intake link: enough for typing a name a
        // few times over, too few to walk the staff list.
        RateLimiter::for('staff-intake-lookup', function (Request $request): Limit {
            return Limit::perMinute(40)->by('staff-intake-lookup|'.$request->ip());
        });

        // Search runs an indexed scan, so it is cheaper than a form post but
        // far from free on a five-worker server. 30/min per IP is generous for
        // a person refining a query and hostile to a scraper walking the index.
        RateLimiter::for('public-search', function (Request $request): Limit {
            return Limit::perMinute(30)->by('public-search|'.$request->ip());
        });

        // Signed webhooks are rejected cheaply, but a flood of them still ties
        // up workers; no legitimate sender comes close to this.
        RateLimiter::for('webhook', function (Request $request): Limit {
            return Limit::perMinute(60)->by('webhook|'.$request->ip());
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
            AnalyticsServiceInterface::class => AnalyticsService::class,
            AchievementServiceInterface::class => AchievementService::class,
            ErrorPageServiceInterface::class => ErrorPageService::class,
            ErrorPageRendererInterface::class => ErrorPageRenderer::class,
            AlumniDirectoryServiceInterface::class => AlumniDirectoryService::class,
            StudentListImportServiceInterface::class => StudentListImportService::class,
            FacultyMemberActivityReportServiceInterface::class => FacultyMemberActivityReportService::class,
            AboutEntityCmsServiceInterface::class => AboutEntityCmsService::class,
            AdmissionsPageServiceInterface::class => AdmissionsPageService::class,
            AdmissionsTuitionFeeImportServiceInterface::class => AdmissionsTuitionFeeImportService::class,
            AboutNavigationCardServiceInterface::class => AboutNavigationCardService::class,
            FacultySubpageCardServiceInterface::class => FacultySubpageCardService::class,
            AboutPageServiceInterface::class => AboutPageService::class,
            CampusLifePageServiceInterface::class => CampusLifePageService::class,
            AuditServiceInterface::class => AuditService::class,
            AdminAccessServiceInterface::class => AdminAccessService::class,
            AuthServiceInterface::class => AuthService::class,
            PermissionCatalogInterface::class => PermissionCatalog::class,
            RoleAdminServiceInterface::class => RoleAdminService::class,
            CmsTargetRegistryInterface::class => CmsTargetRegistry::class,
            CmsWorkflowServiceInterface::class => CmsWorkflowService::class,
            ContinuityServiceInterface::class => ContinuityService::class,
            ContactPageServiceInterface::class => ContactPageService::class,
            DynamicFormSubmissionReviewServiceInterface::class => DynamicFormSubmissionReviewService::class,
            DynamicFormSubmissionServiceInterface::class => DynamicFormSubmissionService::class,
            FormDefinitionServiceInterface::class => FormDefinitionService::class,
            ContactMessageReviewServiceInterface::class => ContactMessageReviewService::class,
            FormSubmissionNotificationServiceInterface::class => FormSubmissionNotificationService::class,
            EServicesPageServiceInterface::class => EServicesPageService::class,
            FacultyStudyPlanEditorServiceInterface::class => FacultyStudyPlanEditorService::class,
            FacultyStudyPlanImportServiceInterface::class => FacultyStudyPlanImportService::class,
            LegacyCalendarEventImportServiceInterface::class => LegacyCalendarEventImportService::class,
            LegacyCourseSpecificationImportServiceInterface::class => LegacyCourseSpecificationImportService::class,
            FacultyStructureImportServiceInterface::class => FacultyStructureImportService::class,
            FacultyStudyPlanBoardServiceInterface::class => FacultyStudyPlanBoardService::class,
            FacultyStudyPlanLinkServiceInterface::class => FacultyStudyPlanLinkService::class,
            ProjectFieldBlockParserInterface::class => ProjectFieldBlockParser::class,
            FacultyPageServiceInterface::class => FacultyPageService::class,
            SitemapServiceInterface::class => SitemapService::class,
            MediaServiceInterface::class => MediaService::class,
            MediaDisplaySettingsServiceInterface::class => MediaDisplaySettingsService::class,
            ImageConversionServiceInterface::class => ImageConversionService::class,
            LegacyImageDerivativeServiceInterface::class => LegacyImageDerivativeService::class,
            LegacyPersonPhotoLibraryServiceInterface::class => LegacyPersonPhotoLibraryService::class,
            SlugServiceInterface::class => SlugService::class,
            PlainTextFormatterInterface::class => PlainTextFormatter::class,
            MenuServiceInterface::class => MenuService::class,
            NewsAdminWorkflowServiceInterface::class => NewsAdminWorkflowService::class,
            NewsArticleCmsServiceInterface::class => NewsArticleCmsService::class,
            NewsArticleEventCmsServiceInterface::class => NewsArticleEventCmsService::class,
            NewsArticleEventServiceInterface::class => NewsArticleEventService::class,
            NewsCalendarEventMoveServiceInterface::class => NewsCalendarEventMoveService::class,
            ResearchPublicationCmsServiceInterface::class => ResearchPublicationCmsService::class,
            ResearchRegisterImportServiceInterface::class => ResearchRegisterImportService::class,
            NewsServiceInterface::class => NewsService::class,
            SeoMetadataServiceInterface::class => SeoMetadataService::class,
            StructuredDataServiceInterface::class => StructuredDataService::class,
            HomepagePreviewAssemblerInterface::class => HomepagePreviewAssembler::class,
            HomepageContentSelectionServiceInterface::class => HomepageContentSelectionService::class,
            HomepageSectionServiceInterface::class => HomepageSectionService::class,
            LegacyCleanedRowServiceInterface::class => LegacyCleanedRowService::class,
            LegacyCategoryMatrixExporterInterface::class => LegacyCategoryMatrixExporter::class,
            LegacyCategoryReviewPacketServiceInterface::class => LegacyCategoryReviewPacketService::class,
            LegacyCareerLinkImportServiceInterface::class => LegacyCareerLinkImportService::class,
            LegacyCareerLinkReviewPacketServiceInterface::class => LegacyCareerLinkReviewPacketService::class,
            LegacyCentralCouncilImportServiceInterface::class => LegacyCentralCouncilImportService::class,
            LegacyClassificationReportServiceInterface::class => LegacyClassificationReportService::class,
            LegacyDecisionPlanServiceInterface::class => LegacyDecisionPlanService::class,
            LegacyFacultyProfileImportServiceInterface::class => LegacyFacultyProfileImportService::class,
            LegacyFacultyProjectImportServiceInterface::class => LegacyFacultyProjectImportService::class,
            LegacyFacultyResearchImportServiceInterface::class => LegacyFacultyResearchImportService::class,
            LegacyPageContentServiceInterface::class => LegacyPageContentService::class,
            CmsPageTextImportServiceInterface::class => CmsPageTextImportService::class,
            LegacyFaqImportServiceInterface::class => LegacyFaqImportService::class,
            LegacyFaqApprovalPacketServiceInterface::class => LegacyFaqApprovalPacketService::class,
            LegacyFaqReviewPacketServiceInterface::class => LegacyFaqReviewPacketService::class,
            LegacyCleaningInspectionServiceInterface::class => LegacyCleaningInspectionService::class,
            LegacyContentCleaningServiceInterface::class => LegacyContentCleaningService::class,
            LegacyFileInventoryServiceInterface::class => LegacyFileInventoryService::class,
            LegacyFileContinuityProbeServiceInterface::class => LegacyFileContinuityProbeService::class,
            LegacyGeneratedUrlInventoryServiceInterface::class => LegacyGeneratedUrlInventoryService::class,
            LegacyIntegrityInspectionServiceInterface::class => LegacyIntegrityInspectionService::class,
            LegacyInternalLinkExtractionServiceInterface::class => LegacyInternalLinkExtractionService::class,
            LegacyImportBatchServiceInterface::class => LegacyImportBatchService::class,
            LegacyImportInspectionServiceInterface::class => LegacyImportInspectionService::class,
            LegacyLocationImportServiceInterface::class => LegacyLocationImportService::class,
            LegacyMappingProposalServiceInterface::class => LegacyMappingProposalService::class,
            LegacyMembersReviewPacketServiceInterface::class => LegacyMembersReviewPacketService::class,
            LegacyNewsImportServiceInterface::class => LegacyNewsImportService::class,
            LegacyNewsPublicationServiceInterface::class => LegacyNewsPublicationService::class,
            LegacyNewsApprovalPacketServiceInterface::class => LegacyNewsApprovalPacketService::class,
            LegacyPhaseSixCandidateServiceInterface::class => LegacyPhaseSixCandidateService::class,
            LegacyPhaseSixApprovalServiceInterface::class => LegacyPhaseSixApprovalService::class,
            LegacyPhaseSixMenuLinkImportServiceInterface::class => LegacyPhaseSixMenuLinkImportService::class,
            LegacyPhaseSixPageImportServiceInterface::class => LegacyPhaseSixPageImportService::class,
            LegacyPhaseSixRestoreServiceInterface::class => LegacyPhaseSixRestoreService::class,
            LegacyPhaseSixSettingsImportServiceInterface::class => LegacyPhaseSixSettingsImportService::class,
            LegacyPhaseSixSettingsMappingServiceInterface::class => LegacyPhaseSixSettingsMappingService::class,
            LegacyPublicStaffImportServiceInterface::class => LegacyPublicStaffImportService::class,
            LegacyPublicStaffApprovalPacketServiceInterface::class => LegacyPublicStaffApprovalPacketService::class,
            LegacyPublicStaffReviewPacketServiceInterface::class => LegacyPublicStaffReviewPacketService::class,
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
            LegacyResearchMetadataExtractorInterface::class => LegacyResearchMetadataExtractor::class,
            LegacyResearchPublicationPublishingServiceInterface::class => LegacyResearchPublicationPublishingService::class,
            LegacyRedirectDecisionServiceInterface::class => LegacyRedirectDecisionService::class,
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
            StaffIntakeLinkServiceInterface::class => StaffIntakeLinkService::class,
            StaffIntakeServiceInterface::class => StaffIntakeService::class,
            StaffIntakeReviewServiceInterface::class => StaffIntakeReviewService::class,
            PeopleDuplicateReportServiceInterface::class => PeopleDuplicateReportService::class,
            PeopleMergeServiceInterface::class => PeopleMergeService::class,
            ResearchPageServiceInterface::class => ResearchPageService::class,
            SearchIndexServiceInterface::class => SearchIndexService::class,
            SiteSearchServiceInterface::class => SiteSearchService::class,
            SettingsServiceInterface::class => SettingsService::class,
            NavigationServiceInterface::class => NavigationService::class,
            TotpAuthenticatorInterface::class => TotpAuthenticator::class,
        ];
    }
}
