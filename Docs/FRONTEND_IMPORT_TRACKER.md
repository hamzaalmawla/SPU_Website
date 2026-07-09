# Frontend Import Tracker

This tracker records frontend additions from `C:\Users\hamza\Spu-Website\Spu-Website` that must be implemented in the Laravel backend without losing scope.

## Reported Content Pages

| Status | Area | Frontend route | Backend target |
|---|---|---|---|
| Done | About | `/about/quality-policy/` | `about.quality-policy` |
| Done | About | `/about/ethical-charter/` | `about.ethical-charter` |
| Done | About | `/about/organizational-structure/` | `about.organizational-structure` |
| Done | Admissions | `/admissions/filling-vacancies/` | `admissions.filling-vacancies` |
| Done | Admissions | `/admissions/graduation-exams/` | `admissions.graduation-exams` |
| Done | Campus Life | `/campus-life/damascus-research-pub/` | `campus_life.damascus-research-pub` |
| Done | Campus Life | `/campus-life/rules-regulations/` | `campus_life.rules-regulations` |
| Done | Campus Life | `/campus-life/general-rules/` | `campus_life.general-rules` |
| Done | Campus Life | `/campus-life/exam-instructions/` | `campus_life.exam-instructions` |
| Done | Campus Life | `/campus-life/exam-penalties/` | `campus_life.exam-penalties` |

## Reported Redirects

| Status | From | To |
|---|---|---|
| Done | `/about/university-council/` | `/about/leadership/` |
| Done | `/admissions/study-system/` | `/admissions/documents/` |
| Done | `/admissions/academic-warnings/` | `/admissions/documents/` |

## Extra Frontend Pages Found

| Status | Area | Frontend route | Notes |
|---|---|---|---|
| Done | About | `/about/accreditation/` | Backend route, fallback payload, CMS target, admin labels, and copied hero image added. |
| Done | About | `/about/why-spu/` | Backend route, fallback payload, CMS target, and admin labels added. |
| Done | Campus Life | `/campus-life/career-development/jobs/` | Backend-rendered Job Board page added with job detail routes and application links. |
| Done | Campus Life | `/campus-life/career-development/jobs/apply/` | Backend-rendered frontend-compatible page using `job-application` dynamic form. |
| Done | Research | `/research/conferences/register?event=conf-001` | Backend-rendered frontend-compatible page using `conference-registration` dynamic form. |
| Done | Research | `/research/conferences/register?event=conf-002` | Backend-rendered frontend-compatible page using `symposium-registration` dynamic form. |
| Done | E-Services | `/e-services/suggestions-complaints/` | Backend-rendered form page added; submissions store through contact message service. |
| Blocked | News | `/news/events-list/register/` | Frontend dynamic-form page exists, but Laravel does not yet have the matching events-list/register route and backend event dataset. Do not wire to unrelated data. |

## URL Compatibility

| Status | Frontend URL | Backend URL | Decision |
|---|---|---|---|
| Done | `/about/partnership/` | `/about/partnerships/` | Redirect alias added. |

## Header / Navigation

| Status | Item | Notes |
|---|---|---|
| Done | Frontend header menu tree | `NavigationSeeder` now matches frontend top-level order: About, Facilities, Admissions, Research, Campus Life, E-Services, News, Contact. |
| Done | About dropdown additions | Added Quality Policy, Ethical Charter, Organizational Structure, Accreditation, and Why SPU to seeded AR/EN header menu. |
| Done | Admissions dropdown additions | Added Filling Vacancies and Graduation & National Exams, and aligned labels/order with frontend. |
| Done | Research grouped dropdown | Added frontend-style grouped Research dropdown with Publications, Researchers, Research Themes, Research Projects, Research Centers, featured links, and flat utility links. |
| Done | Campus Life dropdown additions | Added Job Board plus imported Campus Life pages including Damascus Research Center, Rules & Regulations, General Rules, Exam Instructions, and Exam Penalties. |
| Done | E-Services dropdown | Aligned labels/links with frontend E-Services dropdown, including external Student Portal and Staff Email. |
| Done | News dropdown | Added News, Announcements, Events Calendar, and Media Gallery dropdown items. |
| Done | Header Blade rendering | Desktop and mobile header now render nested menu groups with `site-nav-dropdown-group*` and `site-nav-mobile-group*` classes. |
| Done | Header CSS | Added missing grouped/featured dropdown styles and scroll handling from frontend navigation CSS. |
| Done | Local DB navigation | Ran `php artisan db:seed --class=NavigationSeeder` so the workspace database uses the updated header immediately. |
| Done | Missing page follow-up | Added real backend targets/routes for Job Board, Accreditation, Why SPU, and Suggestions & Complaints after header-only links were found. |
| Done | Missing image follow-up | Copied `/images/about/hero-img.jpg` from frontend source into Laravel `public/images/about/hero-img.jpg`. |

## Dynamic Forms

Frontend files:
- `src/alpine/dynamic-form-store.js`
- `src/data/dynamic-forms.js`
- `src/fragments/components/dynamic-form.html`

| Status | Form ID | Used by | Backend requirement |
|---|---|---|---|
| Done | `conference-registration` | Research conference registration | Stores submissions, validates server-side, supports admin review. |
| Done | `symposium-registration` | Event/symposium registration | Stores submissions, validates server-side, supports admin review. |
| Done | `activity-registration` | Activity registration | Stores submissions, validates server-side, supports admin review. |
| Done | `job-application` | Job application page | Stores multi-step data, CV upload, secure local storage, supports admin review. |

## Backend Implementation Checklist

| Status | Item |
|---|---|
| Done | Public routes and redirects added. |
| Done | CMS target registry updated. |
| Done | Admin labels added for AR/EN. |
| Done | Service fallback payloads imported. |
| Done | Filament edit forms support imported pages. |
| Done | Public Blade views render imported page shapes. |
| Done | Navigation seeder matches frontend additions. |
| Done | SEO, sitemap, preview, publish, and cache behavior verified. Route registration, Blade compilation, existing publish/preview workflow tests, launch validation, redirect validation, and SEO metadata validation pass. |
| Done | Dynamic form backend implemented. |
| Done | Frontend dynamic form store/view registered in Vite and submits to Laravel with CSRF, validation errors, uploads, and success state. |
| Done | Reusable dynamic-form Blade component added for backend-rendered frontend-compatible form pages. |
| Done | Tests cover routes, redirects, CMS targets, public rendering, CMS editor saves for imported content pages, dynamic form submissions/uploads, and rendered form-page shells. |

## Latest Verification

| Status | Check |
|---|---|
| Passed | `php -l app\Services\Research\ResearchPageService.php` |
| Passed | `php -l app\Services\Page\CampusLifePageService.php` |
| Passed | `php -l app\Http\Controllers\Public\ResearchController.php` |
| Passed | `php -l app\Http\Controllers\Public\CampusLifeController.php` |
| Passed | `php -l routes\web.php` |
| Passed | `php artisan route:list --path=campus-life/career-development/jobs/apply` |
| Passed | `php artisan route:list --path=research/conferences/register` |
| Passed | `php artisan route:list --path=forms` |
| Passed | `php artisan view:cache` |
| Passed | `npm run build` |
| Passed | `php artisan test "tests\Feature\DynamicFormSubmissionTest.php"` |
| Passed | `php artisan test "tests\Feature\DynamicFormPageRenderingTest.php"` |
| Passed | `php artisan test "tests\Feature\CampusLifeWorkflowTest.php"` with extended timeout; 21 tests / 149 assertions. |
| Passed | `php artisan test "tests\Feature\HeaderNavigationRenderingTest.php"` |
| Passed | `php artisan test "tests\Feature\HeaderNavigationRenderingTest.php" "tests\Feature\DynamicFormPageRenderingTest.php"` |
| Passed | `php artisan db:seed --class=NavigationSeeder` |
| Passed | `php artisan migrate` created `dynamic_form_submissions`. |
| Passed | `php artisan migrate:status --path="database\migrations\2026_07_09_000001_create_dynamic_form_submissions_table.php"` shows migration as ran. |
| Passed | `php artisan test "tests\Feature\MissingFrontendPagesTest.php"` |
| Passed | `php artisan test "tests\Feature\MissingFrontendPagesTest.php" "tests\Feature\HeaderNavigationRenderingTest.php" "tests\Feature\DynamicFormPageRenderingTest.php" "tests\Feature\DynamicFormSubmissionTest.php"` |
| Passed | `php artisan continuity:validate-redirects` |
| Passed | `php artisan continuity:validate-seo` after reseeding `LandingPageSeeder`; 8 published pages / 2 locales / 0 issues. |
| Passed | `php artisan launch:validate`; warnings remain for unmapped file-continuity inventory and local cache tag support with `CACHE_STORE=database`. |

`npm run build` emitted a non-fatal unresolved runtime asset warning for `/images/admissions-hero-campus.webp`.
