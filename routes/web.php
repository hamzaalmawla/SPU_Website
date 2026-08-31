<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DynamicFormSubmissionAttachmentController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\AdmissionsController;
use App\Http\Controllers\Public\AlumniController;
use App\Http\Controllers\Public\BrowserLocaleRedirectController;
use App\Http\Controllers\Public\CampusLifeController;
use App\Http\Controllers\Public\DynamicFormSubmissionController;
use App\Http\Controllers\Public\EServicesController;
use App\Http\Controllers\Public\FacultyController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PreviewController;
use App\Http\Controllers\Public\PublicContactController;
use App\Http\Controllers\Public\ResearchController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\VirtualTourController;
use App\Http\Middleware\AdminLocaleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', BrowserLocaleRedirectController::class)->name('root');

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Unprefixed reference paths negotiate a locale and redirect to /{locale}/...
// The leading lookahead keeps legacy URLs out of this route: paths such as
// /research/index.php?dir=items&page=show are old-site router URLs, and if they
// matched here they would 302 to /ar/research/index.php — a redirect that lands
// on a 404. They must fall through to a real 404 so RedirectContinuityMiddleware
// logs them into unresolved_legacy_requests for triage instead.
Route::get('/{referencePath}', BrowserLocaleRedirectController::class)
    ->where('referencePath', '(?!.*\.php$)(?:about|admissions|alumni|research|campus-life|e-services|news|contact|facilities|projects|virtual-tour)(?:/.*)?')
    ->name('reference.locale');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->middleware(['locale', 'cache.public'])
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('public.home');
        Route::get('/e-services', EServicesController::class)->name('public.e-services');
        Route::get('/e-services/{detail}', [EServicesController::class, 'detail'])
            ->where(['detail' => 'library|staff-email|it-support'])
            ->name('public.e-services.detail');
        Route::get('/e-services/suggestions-complaints', [EServicesController::class, 'suggestionsComplaints'])->name('public.e-services.suggestions-complaints');
        Route::post('/e-services/suggestions-complaints', [EServicesController::class, 'storeSuggestionsComplaints'])
            ->defaults('form', 'suggestions-complaints')
            ->middleware('throttle:public-form')
            ->name('public.e-services.suggestions-complaints.submit');
        Route::get('/virtual-tour', VirtualTourController::class)->name('public.virtual-tour');
        Route::get('/alumni', [AlumniController::class, 'index'])->name('public.alumni.index');

        Route::controller(FacultyController::class)
            ->prefix('faculties')
            ->name('public.faculties.legacy.')
            ->group(function (): void {
                Route::get('/{legacyPath?}', 'redirectLegacy')
                    ->where('legacyPath', '.*')
                    ->name('redirect');
            });

        Route::controller(FacultyController::class)
            ->prefix('facilities')
            ->name('public.facilities.')
            ->group(function (): void {
                Route::get('/', 'hub')->name('hub');
                Route::get('/{faculty}', 'faculty')
                    ->where(['faculty' => 'medicine|dentistry|pharmacy|artificial-intelligence|building-construction-engineering|petroleum|business-administration'])
                    ->name('show');
                Route::get('/{faculty}/study-plan/course', 'courseLessons')
                    ->where(['faculty' => 'medicine|dentistry|pharmacy|artificial-intelligence|building-construction-engineering|petroleum|business-administration'])
                    ->name('study-plan.course');
                Route::get('/{faculty}/study-plan', 'studyPlan')
                    ->where(['faculty' => 'medicine|dentistry|pharmacy|artificial-intelligence|building-construction-engineering|petroleum|business-administration'])
                    ->name('study-plan');
                Route::get('/{faculty}/projects/{project}', 'project')
                    ->where([
                        'faculty' => 'medicine|dentistry|pharmacy|artificial-intelligence|building-construction-engineering|petroleum|business-administration',
                        'project' => '[A-Za-z0-9\-]+',
                    ])
                    ->name('projects.show');
                Route::get('/{faculty}/{subpage}', 'subpage')
                    ->where([
                        'faculty' => 'medicine|dentistry|pharmacy|artificial-intelligence|building-construction-engineering|petroleum|business-administration',
                        'subpage' => 'overview|departments|labs|projects|alumni|valedictorians|training|research|members',
                    ])
                    ->name('subpage');
            });

        Route::controller(AdmissionsController::class)
            ->prefix('admissions')
            ->name('public.admissions.')
            ->group(function (): void {
                Route::get('/', 'landing')->name('landing');
                Route::get('/study-system', 'redirectToDocuments')
                    ->defaults('tab', 'study-system')
                    ->name('study-system.redirect');
                Route::get('/academic-warnings', 'redirectToDocuments')
                    ->defaults('tab', 'academic-warnings')
                    ->name('academic-warnings.redirect');
                Route::get('/{section}', 'section')
                    ->where(['section' => 'requirements|tuition|how-to-apply|faq|calendar|documents|transfer|filling-vacancies|graduation-exams'])
                    ->name('section');
            });

        Route::controller(CampusLifeController::class)
            ->prefix('campus-life')
            ->name('public.campus-life.')
            ->group(function (): void {
                Route::get('/', 'landing')->name('landing');
                Route::get('/transport/registration', 'transportRegistration')->name('transport.registration');
                Route::get('/career-development/jobs', 'careerJobBoard')->name('career-development.jobs');
                Route::get('/career-development/jobs/apply', 'careerJobApplication')->name('career-development.jobs.apply');
                Route::get('/career-development/jobs/{job}', 'careerJobDetail')
                    ->where(['job' => '[A-Za-z0-9\-]+'])
                    ->name('career-development.jobs.show');
                Route::get('/{section}', 'section')
                    ->where(['section' => 'services|transport|clubs-activities|career-development|dental|hospital|health-insurance|damascus-research-pub|rules-regulations|general-rules|exam-instructions|exam-penalties'])
                    ->name('section');
            });

        Route::controller(AboutController::class)
            ->prefix('about')
            ->name('public.about.')
            ->group(function (): void {
                Route::get('/', 'landing')->name('landing');
                Route::get('/university-council', 'redirectUniversityCouncil')->name('university-council.redirect');
                Route::get('/vision-mission', 'visionMission')->name('vision-mission');
                Route::get('/history', 'history')->name('history');
                Route::get('/leadership', 'leadership')->name('leadership');
                Route::get('/central-directorates', 'directorates')->name('central-directorates');
                Route::get('/directorates', 'directorates')->name('directorates');
                Route::get('/directorates/staff', 'staffDirectory')->name('directorates.staff');
                Route::get('/directorates/{directorate}', 'directorateDetail')->name('directorates.show');
                Route::get('/partnership', 'redirectPartnershipAlias')->name('partnership.redirect');
                Route::get('/partnerships', 'partnerships')->name('partnerships');
                Route::get('/profile', 'redirectLegacyProfile')->name('profile.legacy');
                Route::get('/{section}', 'content')
                    ->where(['section' => 'quality-policy|ethical-charter|organizational-structure|accreditation|why-spu'])
                    ->name('content');
                Route::get('/profile/{slug}', 'profile')
                    ->where(['slug' => '[A-Za-z0-9\-]+'])
                    ->name('profile');
                Route::get('/profile/{source}/{slug}', 'profileLegacy')
                    ->where([
                        'source' => 'person|faculty-member',
                        'slug' => '[A-Za-z0-9\-]+',
                    ])
                    ->name('profile.legacy.source');
            });

        Route::get('/preview', PreviewController::class)->name('preview.show');

        Route::get('/contact', [PublicContactController::class, 'show'])->name('public.contact');
        Route::post('/contact', [PublicContactController::class, 'store'])
            ->middleware('throttle:public-form')
            ->name('public.contact.submit');

        Route::post('/forms/{form}/submissions', DynamicFormSubmissionController::class)
            ->where(['form' => 'conference-registration|symposium-registration|activity-registration|job-application|admissions-application|suggestions-complaints'])
            ->middleware('throttle:public-form')
            ->name('public.forms.submit');

        Route::controller(NewsController::class)
            ->prefix('news')
            ->name('public.news.')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/articles', 'articles')->name('articles');
                Route::get('/announcements', 'announcements')->name('announcements');
                Route::get('/events', 'events')->name('events');
                Route::get('/events-list', 'eventsList')->name('events-list');
                Route::get('/events-list/register', 'eventRegistration')->name('events-list.register');
                Route::get('/events-list/past', 'pastEvent')->name('events-list.past');
                Route::get('/events-list/{event}', 'eventDetail')
                    ->where(['event' => '[A-Za-z0-9\-]+'])
                    ->name('events-list.show');
                Route::get('/gallery', 'gallery')->name('gallery');
                Route::get('/article', 'redirectLegacyArticle')->name('article.legacy');
                Route::get('/{article}', 'show')->name('show');
            });

        Route::controller(ResearchController::class)
            ->prefix('research')
            ->name('public.research.')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/repository', 'repository')->name('repository');
                Route::get('/detail', 'legacyDetail')->name('detail');
                Route::get('/publications', 'publications')->name('publications.index');
                Route::get('/publications/detail', 'legacyDetail')->name('publications.detail');
                Route::get('/publications/{slug}', 'publication')
                    ->where(['slug' => '[A-Za-z0-9\-]+'])
                    ->name('publications.show');
                Route::get('/centers', 'centers')->name('centers.index');
                Route::get('/centers/detail', 'centers')->name('centers.detail');
                Route::get('/centers/{slug}', 'center')
                    ->where(['slug' => '[A-Za-z0-9\-]+'])
                    ->name('centers.show');
                Route::get('/projects', 'projects')->name('projects.index');
                Route::get('/projects/detail', 'projects')->name('projects.detail');
                Route::get('/projects/{slug}', 'project')
                    ->where(['slug' => '[A-Za-z0-9\-]+'])
                    ->name('projects.show');
                Route::get('/themes', 'themes')->name('themes.index');
                Route::get('/themes/detail', 'themes')->name('themes.detail');
                Route::get('/themes/{slug}', 'theme')
                    ->where(['slug' => '[A-Za-z0-9\-]+'])
                    ->name('themes.show');
                Route::get('/researchers', 'researchers')->name('researchers.index');
                Route::get('/researchers/detail', 'researchers')->name('researchers.detail');
                Route::get('/researchers/{slug}', 'researcher')
                    ->where(['slug' => '[A-Za-z0-9\-]+'])
                    ->name('researchers.show');
                Route::get('/expert-finder', 'expertFinder')->name('expert-finder');
                Route::get('/conferences/register', 'conferenceRegistration')->name('conferences.register');
                Route::get('/conferences', 'conferences')->name('conferences');
                Route::get('/library', 'library')->name('library');
                Route::get('/office', 'office')->name('office');
                Route::get('/policies', 'policies')->name('policies');
            });

        Route::get('/projects/detail', [FacultyController::class, 'redirectLegacyProject'])
            ->name('public.projects.detail.legacy');

        Route::get('/{slugPath}', PageController::class)
            ->where('slugPath', '.+')
            ->name('public.page');
    });

Route::prefix('admin')
    ->name('admin.')
    ->middleware(AdminLocaleMiddleware::class)
    ->group(function (): void {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/locale/{locale}', [AuthController::class, 'switchLocale'])
            ->where(['locale' => 'ar|en'])
            ->name('locale');
        Route::post('/login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.attempt');

        Route::middleware(['admin.auth', 'two.factor'])
            ->group(function (): void {
                Route::post('/auth/logout', [AuthController::class, 'destroy'])->name('logout');

                Route::get('/form-submissions/{submission}/attachments/{field}', DynamicFormSubmissionAttachmentController::class)
                    ->whereNumber('submission')
                    ->whereIn('field', ['cvFile', 'attachment'])
                    ->name('form-submissions.attachments.download');

                Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])
                    ->name('two-factor.challenge');
                Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
                    ->middleware('throttle:two-factor')
                    ->name('two-factor.verify');
            });
    });

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Webhook routes are excluded from CSRF verification in bootstrap/app.php
| and instead protected by HMAC-SHA256 signature verification middleware.
| Add new webhook consumer routes inside this group.
|
*/
Route::prefix('webhook')
    ->name('webhook.')
    ->middleware('verify.webhook')
    ->group(function (): void {
        Route::post('/incoming', function (Request $request) {
            return response()->json(['status' => 'received'], 200);
        })->name('incoming');
    });
