# Production Readiness Audit

Audit date: 2026-06-25
Branch: `admissions`
Latest commit observed: `a3cf6d4 feat(public): add facilities news and campus foundations`
Requested output path: `docs/PRODUCTION_READINESS_AUDIT.md`
Actual workspace directory: `Docs/` exists; root lowercase `docs/` maps to this directory on Windows.

## Executive Verdict

The project is not production-ready today.

The homepage/admin foundation has strong architectural pieces in place: contract-based public controllers, service-layer homepage draft/preview/publish workflow, tokenized preview, cache invalidation, audit logging, explicit AR/EN translation tables, role policies, admin auth lockout, and Laravel 11+ middleware registration in `bootstrap/app.php`.

Production readiness is blocked by missing dependencies and unverified runtime behavior. Several expanded public modules also go beyond the stated foundation scope and are not production-grade CMS workflows yet, especially news, facilities/faculties, admissions, campus life, and virtual tour.

## Verification Status

Commands run:

| Command | Result |
| --- | --- |
| `git status --short --branch` | `## admissions...origin/admissions` |
| `git log --oneline -5` | `a3cf6d4 feat(public): add facilities news and campus foundations` |
| `php artisan route:list` | Failed: `vendor/autoload.php` missing |
| `php artisan test --filter=ArchitectureGuardTest` | Failed: `vendor/autoload.php` missing |
| `npm run build` | Failed: `vite` not recognized |

Dependency evidence:

| Check | Result |
| --- | --- |
| `vendor/autoload.php` | Missing |
| `composer.lock` | Present |
| `node_modules/.bin/vite*` | Missing |
| `package-lock.json` | Present |

Exact Laravel failure observed:

```text
Warning: require(C:\Users\ASUS\Desktop\diaa\SPU_Website/vendor/autoload.php): Failed to open stream: No such file or directory in C:\Users\ASUS\Desktop\diaa\SPU_Website\artisan on line 10
PHP Fatal error:  Uncaught Error: Failed opening required 'C:\Users\ASUS\Desktop\diaa\SPU_Website/vendor/autoload.php'
```

Exact frontend build failure observed:

```text
> build
> vite build

'vite' is not recognized as an internal or external command,
operable program or batch file.
```

Result: code could not be compiled or runtime-verified in this environment.

## Source Documents

Root-level required docs were not present:

| Expected root file | Status |
| --- | --- |
| `ARCHITECTURE.md` | Missing at root; present as `Docs/ARCHITECTURE.md` |
| `BACKEND_RULES.md` | Missing at root; present as `Docs/BACKEND_RULES.md` |
| `STYLEGUIDE.md` | Missing at root; present as `Docs/STYLEGUIDE.md` |
| `WORKFLOW.md` | Missing at root; present as `Docs/WORKFLOW.md` |

Effective documents used:

| Document | Relevant rules checked |
| --- | --- |
| `AGENTS.md` | Foundation scope, service layer, DTO rules, multilingual rules, homepage section keys, publish/cache/audit rules |
| `Docs/ARCHITECTURE.md` | Controllers depend on interfaces, services contain business logic, contracts do not expose models, DTO return boundaries |
| `Docs/BACKEND_RULES.md` | Laravel 11+ structure, no `app/Http/Kernel.php`, FormRequest usage, policy/gate access |
| `Docs/STYLEGUIDE.md` | Final readonly DTOs, typed returns, DTO mapping conventions |
| `Docs/WORKFLOW.md` | Required Laravel commands and testing workflow |

## Scope Drift

`AGENTS.md` says the current build scope is homepage plus admin panel foundation. The current codebase includes broader public modules:

| Module | Evidence |
| --- | --- |
| Facilities/faculties | `routes/web.php:45-59`, `app/Services/Page/FacultyPageService.php` |
| Admissions | `routes/web.php:61-69`, `app/Services/Page/AdmissionsPageService.php` |
| Campus life | `routes/web.php:71-79`, `app/Services/Page/CampusLifePageService.php` |
| About | `routes/web.php:81-93`, `app/Services/Page/AboutPageService.php` |
| News | `routes/web.php:102-109`, `app/Services/News/NewsService.php` |
| Contact | `routes/web.php:97-100`, `app/Services/Page/ContactPageService.php` |
| Virtual tour | `routes/web.php:34`, `app/Services/Page/VirtualTourPageService.php` |

These modules should not be treated as production-ready simply because routes exist.

## Readiness Matrix

| Area | Status | Notes |
| --- | --- | --- |
| Dependency/runtime verification | Blocked | Composer vendor and npm dependencies are missing. |
| Laravel 11+ structure | Looks compliant | Middleware registered in `bootstrap/app.php`; `app/Http/Kernel.php` not found. Runtime unverified. |
| Public controller architecture | Looks compliant | Public controllers import contracts and do not import Eloquent models. Runtime unverified. |
| Service contract boundaries | Mostly compliant | Contracts return DTOs, bool, scalar values, arrays for composites, or DTO collections. Runtime unverified. |
| Homepage CMS workflow | Conditionally ready foundation | Fixed 11 keys, AR/EN validation, draft/preview/publish, cache invalidation, audit logging are present. Needs runtime verification and production content. |
| Generic pages CMS | Conditionally ready foundation | Page service handles draft/publish/schedule/preview and AR/EN translations. Some Filament UI helpers query models directly. |
| Admin auth/RBAC | Conditionally ready foundation | Lockout, role checks, 2FA, gates, and policies exist. Needs runtime verification. |
| Settings | Foundation present | Settings service and management page exist; content must be provisioned. Runtime unverified. |
| Navigation/menu | Foundation present | Menu and navigation services exist; cache-aware navigation is present. Runtime unverified. |
| Media library | Foundation present | Upload validation, scoped access, audit logging, and service-driven create/edit/delete exist. Runtime unverified. |
| Audit logs | Partial | Core services log important events; several newer Filament resources bypass audit service. |
| Contact lead capture | Foundation present | FormRequest validation and throttled route exist. No full CRM system expected in this phase. |
| E-services page | Foundation present | Settings-backed content and admin page exist. Requires content verification. |
| About pages | Partial | Translation-backed public service and admin resources exist; seeded/static content remains. |
| Facilities/faculties | Not production-ready | Public service and admin resources exist, but fixed allowlists, seeded/demo content, direct Filament Eloquent writes, and cache invalidation gaps remain. |
| News | Not production-ready | Public DTO read service exists, but admin create/edit bypass service-layer publish/audit/cache workflow. |
| Admissions/campus-life/virtual-tour | Not production CMS-ready | Bilingual content is hardcoded in services, not CMS-managed. |
| Legacy redirects/import | Foundation present | Tables, seeders, and legacy redirect route exist. Import/runtime validation not verified. |
| Frontend build | Blocked | `npm run build` fails because Vite is unavailable. |

## Critical Blockers

### P0: Dependencies are missing, so production behavior cannot be verified

Evidence:

| Evidence | Path |
| --- | --- |
| `vendor/autoload.php` missing | `artisan` failed at line 10 |
| `node_modules/.bin/vite*` missing | glob returned no files |
| `php artisan route:list` failed | command output above |
| `php artisan test --filter=ArchitectureGuardTest` failed | command output above |
| `npm run build` failed | command output above |

Impact: route registration, provider bindings, migrations, tests, Filament boot, and compiled assets are all unverified.

### P1: News admin can bypass intended publish workflow

Evidence:

| Evidence | Path |
| --- | --- |
| News form exposes `status` with `draft`, `published`, `scheduled`, `archived` | `app/Filament/Resources/NewsArticleResource.php:58-61` |
| Faculty editors can create/update scoped news articles | `app/Policies/NewsArticlePolicy.php:17-20`, `app/Policies/NewsArticlePolicy.php:37-48` |
| `publish` policy is editor-only | `app/Policies/NewsArticlePolicy.php:32-35` |
| Create/edit pages mutate form data only, then rely on Filament record persistence | `app/Filament/Resources/NewsArticleResource/Pages/CreateNewsArticle.php`, `app/Filament/Resources/NewsArticleResource/Pages/EditNewsArticle.php` |

Risk: a scoped `faculty_editor` appears able to set a news article to `published` through normal create/update access, bypassing the `publish` ability and any service-layer publish validation.

Needs verification after dependencies are installed, but this is a production-blocking authorization risk based on code structure.

### P1: News admin writes bypass audit logging and cache invalidation

Evidence:

| Evidence | Path |
| --- | --- |
| Public news service caches listings/articles/categories | `app/Services/News/NewsService.php:30-80`, `app/Services/News/NewsService.php:111-198` |
| News create/edit pages do not inject a service or audit/cache service | `app/Filament/Resources/NewsArticleResource/Pages/CreateNewsArticle.php`, `app/Filament/Resources/NewsArticleResource/Pages/EditNewsArticle.php` |
| News resource uses Eloquent relationship forms directly | `app/Filament/Resources/NewsArticleResource.php:72-95` |

Impact: public news pages can serve stale cached data after admin edits, and production audit trails may miss article create/update/publish actions.

### P1: Facilities/faculties admin writes bypass service-layer cache invalidation

Evidence:

| Evidence | Path |
| --- | --- |
| Public faculty service uses `Cache::remember` for facilities pages | `app/Services/Page/FacultyPageService.php:46-130`, `app/Services/Page/FacultyPageService.php:133-183` |
| Faculty resources use Filament model relationship forms and direct `Faculty::query()` option builders | `app/Filament/Resources/FacultyResource.php`, `app/Filament/Resources/FacultyPageResource.php:56`, `app/Filament/Resources/FacultyHighlightResource.php:56` |
| Faculty resource create/edit pages are default Filament record pages | `app/Filament/Resources/FacultyPageResource/Pages/CreateFacultyPage.php`, `app/Filament/Resources/FacultyPageResource/Pages/EditFacultyPage.php` |

Impact: facilities/faculty content changes can remain stale for up to the cache TTL and may lack audit entries. This module is also outside the original foundation scope.

### P1: Multiple public pages are hardcoded in services, not CMS-managed

Evidence:

| Module | Evidence |
| --- | --- |
| Admissions | `app/Services/Page/AdmissionsPageService.php:55-592` contains large AR/EN payload arrays and SEO strings. |
| Campus life | `app/Services/Page/CampusLifePageService.php:54-414` contains large AR/EN payload arrays and static links/dates. |
| Virtual tour | `app/Services/Page/VirtualTourPageService.php:28-172` contains AR/EN payload arrays and static media paths. |
| News landing labels | `app/Http/Controllers/Public/NewsController.php:27-52` contains controller-local AR/EN title/description strings. |
| Header/search placeholders | `resources/views/public/layout/header.blade.php:119` contains hardcoded AR/EN search placeholder. |
| About partnerships search | `resources/views/public/about/partnerships.blade.php:41` contains hardcoded AR/EN placeholder. |

Impact: these areas are bilingual and localized, but they are not CMS-managed. They should not be represented as production CMS modules.

### P1: Production seeding does not create complete public content

Evidence:

| Evidence | Path |
| --- | --- |
| `DatabaseSeeder` calls `ProductionFoundationSeeder`, then local-only seeders only in `local` or `testing` | `database/seeders/DatabaseSeeder.php:17-23` |
| Production seed includes roles, homepage section shells, about sections, faculty module data | `database/seeders/ProductionFoundationSeeder.php:16-21` |
| Homepage translations, settings, landing pages, and navigation are local/testing only | `database/seeders/LocalDevelopmentSeeder.php:16-22` |
| Homepage public renderer filters out sections without renderable localized payloads | `app/Services/Homepage/HomepageSectionService.php:64-75` |

Impact: a production install seeded only with production seeders will not automatically have complete homepage translations, settings, or navigation content. This may be intentional, but launch requires an explicit content import/publish plan.

## Architecture Findings

### Positive Evidence

| Rule | Evidence |
| --- | --- |
| Public controllers depend on interfaces | All public controllers import `App\Contracts`, e.g. `app/Http/Controllers/Public/HomeController.php`, `NewsController.php`, `PageController.php`. |
| Public controllers do not import models | Grep over `app/Http/Controllers/Public` found no `use App\Models` imports. |
| Service interfaces are bound | `app/Providers/AppServiceProvider.php:231-270` registers contract to implementation bindings. Runtime unverified due missing vendor. |
| Middleware uses Laravel 11+ bootstrap registration | `bootstrap/app.php:21-35`. |
| No legacy Kernel | `app/Http/Kernel.php` not found. |
| FormRequest validation exists where needed | `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Requests/Auth/TwoFactorChallengeRequest.php`, `app/Http/Requests/PublicContactRequest.php`. |
| Explicit translation tables exist | `page_translations`, `homepage_section_translations`, `about_page_translations`, `news_article_translations`, `faculty_page_translations`, etc. |
| No old global polymorphic translation model found | Search for `translatable_type`, `translatable_id`, `morphs`, and `morphTo` found no matches. |

### Architecture Risks

| Risk | Evidence |
| --- | --- |
| Filament resources contain direct Eloquent query/UI logic | `PageResource.php:90-95`, `PageResource.php:439-443`, `FacultyPageResource.php:56`, `FacultyHighlightResource.php:56`. |
| Several admin resources are not service-layer workflows | `NewsArticleResource.php`, `FacultyResource.php`, `FacultyPageResource.php`, `FacultyHighlightResource.php`. |
| Placeholder service implementations remain in source | `app/Services/Placeholders/*.php`; not bound in `AppServiceProvider`, but should be reviewed if production standards disallow placeholder classes. |
| Public service/helper methods sometimes expose model type-hints internally | Example: `PagePublicReadService::mapPageToDto(Page $page)`. Public contracts do not expose these, but strict interpretation of service method rules may need review. |

## Homepage CMS Assessment

Status: conditionally ready foundation, blocked by runtime verification and production content provisioning.

Strengths:

| Capability | Evidence |
| --- | --- |
| Fixed 11 section keys | `app/Contracts/Homepage/HomepageSectionServiceInterface.php:18-30` |
| Admin management page | `app/Filament/Pages/ManageHomepage.php` |
| Draft save with optimistic locking | `app/Services/Homepage/HomepagePublishingService.php:45-127` |
| Publish validates all 11 sections and both locales | `app/Services/Homepage/HomepagePublishingService.php:348-376` |
| Publish writes AR/EN translations | `app/Services/Homepage/HomepagePublishingService.php:145-184` |
| Preview tokens are created and hashed | `app/Services/Preview/PreviewTokenStore.php:40-53`, `app/Services/Preview/PreviewTokenStore.php:114-120` |
| Preview route is token-protected | `app/Http/Controllers/Public/PreviewController.php:30-39` |
| Cache invalidation on publish/unpublish | `app/Services/Homepage/HomepagePublishingService.php:197-209`, `app/Services/Homepage/HomepagePublishingService.php:455-464` |
| Audit logging on draft/publish/unpublish | `app/Services/Homepage/HomepagePublishingService.php:115-125`, `app/Services/Homepage/HomepagePublishingService.php:200-209`, `app/Services/Homepage/HomepagePublishingService.php:229-236` |

Risks:

| Risk | Evidence |
| --- | --- |
| Production seed only creates section shells, not localized homepage content | `database/seeders/ProductionFoundationSeeder.php`, `database/seeders/LocalDevelopmentSeeder.php` |
| Homepage translation seeder is local/testing only | `database/seeders/HomepageSectionTranslationSeeder.php`, called by `LocalDevelopmentSeeder` only |
| Runtime behavior unverified | `php artisan test --filter=ArchitectureGuardTest` could not run |

## Generic Page CMS Assessment

Status: conditionally ready foundation, blocked by runtime verification.

Strengths:

| Capability | Evidence |
| --- | --- |
| Page shell creation through service | `app/Services/Page/PageService.php:79-107` |
| Metadata, AR/EN translations, and AR/EN SEO through service | `app/Services/Page/PageService.php:109-163`, `app/Services/Page/PageService.php:412-483` |
| Draft save with optimistic locking | `app/Services/Page/PageService.php:165-233` |
| Publish/schedule/unpublish workflow | `app/Services/Page/PageService.php:235-333` |
| Public reads block draft/unpublished/future pages | `app/Services/Page/PagePublicReadService.php:98-123` |
| Publishability requires both AR and EN titles | `app/Services/Page/PagePublishabilityValidator.php:15-38` |
| Page admin status field is disabled; publish/schedule actions are used | `app/Filament/Resources/PageResource.php:230-241`, `app/Filament/Resources/PageResource/Pages/EditPage.php:173-229` |

Risks:

| Risk | Evidence |
| --- | --- |
| PageResource contains direct query helpers for listing/sorting/parent cycle detection | `app/Filament/Resources/PageResource.php:90-95`, `app/Filament/Resources/PageResource.php:439-443` |
| Runtime behavior unverified | Laravel commands fail due missing `vendor/autoload.php` |

## Security And Access Control Assessment

Status: conditionally ready foundation, with news publish authorization risk.

Strengths:

| Capability | Evidence |
| --- | --- |
| Admin auth middleware checks lock status and admin roles | `app/Http/Middleware/AdminAuthMiddleware.php:27-58` |
| Auth service locks accounts after 5 failed attempts | `app/Services/Auth/AuthService.php:25`, `app/Services/Auth/AuthService.php:299-333` |
| Admin login throttling is configured | `app/Providers/AppServiceProvider.php:212-218`, `routes/web.php:124-126` |
| Public contact throttling is configured | `app/Providers/AppServiceProvider.php:220-222`, `routes/web.php:98-100` |
| 2FA challenge middleware exists | `app/Http/Middleware/TwoFactorChallengeMiddleware.php` |
| TOTP secrets and recovery codes use encrypted model casts and hashed recovery code storage | `app/Models/User/User.php:70-73`, `app/Services/Auth/TotpAuthenticator.php` |
| Production security guard checks debug/app key/session encryption/secure cookies | `app/Providers/AppServiceProvider.php:145-183` |
| Public page cache bypasses non-GET, admin, authenticated, contact, and preview traffic | `app/Http/Middleware/CachePublicPages.php:63-102` |

Risks:

| Risk | Evidence |
| --- | --- |
| News publish authorization bypass risk | See P1 finding above. |
| Production security enforcement depends on correct env/config cache state | `app/Providers/AppServiceProvider.php:176-183`; needs deployment verification. |
| Session defaults are not production-secure unless env is set | `config/session.php:50`, `config/session.php:172`; provider enforces in explicit production. |

## Multilingual And RTL/LTR Assessment

Status: foundation mostly aligned, content-source gaps remain.

Positive evidence:

| Rule | Evidence |
| --- | --- |
| Default AR and secondary EN routing | `routes/web.php:28-30` restricts locale to `ar|en`; root redirects to `/ar`. |
| DTOs carry `direction` | Homepage, page, admissions, campus life, virtual tour, about, faculty, contact DTO creation all set `rtl` for AR and `ltr` for EN. |
| Explicit translation tables | Migrations for `page_translations`, `homepage_section_translations`, `news_article_translations`, `about_page_translations`, `faculty_page_translations`, etc. |
| Filament form tabs include AR/EN sections | `PageResource.php`, `ManageHomepage.php`, `NewsArticleResource.php`, `MediaAssetResource.php`, faculty resources. |

Risks:

| Risk | Evidence |
| --- | --- |
| Some translatable public labels are hardcoded in controllers/views/services | See P1 hardcoded content finding. |
| Fallback-to-Arabic behavior may hide missing English content | Examples: `AboutPageService` translation fallback methods, `NewsService` translation fallback methods, `FacultyPageService` translation fallback methods. This may be acceptable, but launch should verify intended behavior. |

## Public Routes And SEO/Continuity

Status: foundation present, runtime unverified.

Evidence:

| Capability | Evidence |
| --- | --- |
| Locale public route group | `routes/web.php:28-114` |
| Sitemap route | `routes/web.php:25`, `app/Services/Seo/SitemapService.php` |
| Robots route | `routes/web.php:26`, `app/Http/Controllers/Public/SitemapController.php:33-63` |
| Legacy `faculties` redirect to `facilities` | `routes/web.php:36-43`, `app/Http/Controllers/Public/FacultyController.php:52-66` |
| Generic page catch-all after specific routes | `routes/web.php:111-113` |

Risks:

| Risk | Evidence |
| --- | --- |
| Route list could not be generated | `php artisan route:list` failed due missing vendor. |
| Sitemap service queries pages directly | `app/Services/Seo/SitemapService.php:30-40`; service-layer acceptable, but runtime unverified. |
| Legacy import readiness is structural only | Legacy tables/seeders exist, but import execution was not verified. |

## Data And Seeder Assessment

Status: mixed.

Positive evidence:

| Capability | Evidence |
| --- | --- |
| Explicit translation schema | Numerous `*_translations` migrations with locale indexes and uniqueness. |
| Preview tokens hashed | `database/migrations/2026_04_30_000001_hash_preview_tokens.php`, `app/Services/Preview/PreviewTokenStore.php` |
| Draft tables have versioning | `database/migrations/2026_04_30_000004_add_version_to_draft_tables.php` |
| Performance indexes exist | `database/migrations/2026_04_30_000002_add_composite_performance_indexes.php`, `2026_06_21_000002_add_news_performance_indexes.php` |
| Faculty scope exists for pages/media/users | `database/migrations/2026_05_06_000001_add_faculty_scope_slug_to_pages_table.php`, `2026_05_06_000002_add_faculty_scope_slug_to_media_assets_table.php` |

Seeder risks:

| Risk | Evidence |
| --- | --- |
| Local/demo content is not production-seeded | `database/seeders/LocalDevelopmentSeeder.php` |
| Faculty seed contains placeholder/model/demo details | `database/seeders/FacultyModuleSeeder.php` includes `student name`, `Approved Training Location`, `Prof. Mays Hassan`, `Dr. Ahmad Nassar`. |
| About seed contains model cards | `database/seeders/AboutSectionSeeder.php` contains `A model card...` content. |
| Landing page seeder is explicitly placeholder/demo | `database/seeders/LandingPageSeeder.php:14` |

## Module Notes

### News

Not production-ready.

The public read side maps Eloquent to DTOs and caches results through `NewsServiceInterface`, but the admin write side is not a service-layer publish workflow.

Required before production:

| Required work | Reason |
| --- | --- |
| Move news create/update/publish/schedule/archive into a news write service contract | Prevents direct Eloquent workflow drift. |
| Enforce `publish-content`/`publish` gate when status becomes public | Current form exposes `published` to users who may only have update access. |
| Invalidate `news:*`, `public-pages`, and relevant shell caches on write/publish | Public service caches news responses. |
| Audit article create/update/publish/unpublish/archive actions | Current Filament write path has no audit service evidence. |
| Validate public status timestamps consistently | `NewsArticle::scopePublic()` allows `published_at` null and ignores `scheduled_at`. |

### Facilities/Faculties

Not production-ready.

The public pages are more complete than foundation scaffolding, but the module still has fixed route allowlists, static service shell copy, seeded/demo details, direct admin Eloquent writes, and cache invalidation gaps.

Evidence:

| Evidence | Path |
| --- | --- |
| Fixed public faculty slugs/subpage slugs | `routes/web.php:50-57`, `app/Services/Page/FacultyPageService.php:35-45` |
| Service-level static shell copy and facts | `app/Services/Page/FacultyPageService.php:51-78` |
| Cache facade usage | `app/Services/Page/FacultyPageService.php:48`, `app/Services/Page/FacultyPageService.php:96`, `app/Services/Page/FacultyPageService.php:141` |
| Direct Filament model resources | `FacultyResource.php`, `FacultyPageResource.php`, `FacultyHighlightResource.php` |

### Admissions, Campus Life, Virtual Tour

Not production CMS-ready.

These pages are bilingual and localized, but their content is implemented as service-local arrays instead of CMS-managed data.

Evidence:

| Module | Path |
| --- | --- |
| Admissions | `app/Services/Page/AdmissionsPageService.php` |
| Campus life | `app/Services/Page/CampusLifePageService.php` |
| Virtual tour | `app/Services/Page/VirtualTourPageService.php` |

### Contact

Foundation-ready, runtime unverified.

Evidence:

| Capability | Evidence |
| --- | --- |
| FormRequest validation | `app/Http/Requests/PublicContactRequest.php` |
| Throttled POST route | `routes/web.php:98-100` |
| Service stores contact messages | `app/Services/Page/ContactPageService.php:58-72` |
| Admin resource exists | `app/Filament/Resources/ContactMessageResource.php` |

Limit: this is lead capture only, not a full CRM, which matches the foundation scope.

## Recommended Production Gate

Before considering production deployment, complete these gates:

1. Install dependencies and rerun verification: `composer install`, `npm ci`, `php artisan route:list`, `php artisan test`, `npm run build`.
2. Fix or explicitly remove production access to modules that are outside foundation scope.
3. Fix the news publish authorization path so `faculty_editor` cannot publish by changing a form status field.
4. Add service-layer write workflows, audit logging, and cache invalidation for news and facilities/faculty admin changes, or hide those admin resources from production.
5. Decide production content strategy: import/publish real homepage, settings, navigation, pages, media, and SEO data; do not rely on local/demo seeders.
6. Verify production environment hardening: `APP_ENV=production`, `APP_DEBUG=false`, valid `APP_KEY`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, correct `APP_URL`, queue/cache/session drivers.
7. Run migrations against a staging database and verify rollback/import runbooks.
8. Review all public hardcoded content and either migrate it to CMS/settings or mark the page explicitly out-of-scope for launch.
9. Confirm cache purge/warm behavior after publish for homepage, pages, news, facilities, settings, navigation, sitemap, and SEO.
10. Run a role-based manual QA pass for `super_admin`, `editor`, and `faculty_editor`.

## Final Assessment

Production readiness: no.

Foundation readiness: partially yes, pending dependency installation and runtime verification.

The safest launch posture is to treat the homepage/admin/page/menu/settings/media foundation as the target system, then hide or hard-disable expanded modules until their write workflows, authorization, cache invalidation, audit logging, and CMS content model are finished.
