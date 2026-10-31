# Spec-Driven Refactoring & Quality Improvement — SPU Website Foundation

## Objective

Refactor, harden, and improve the code quality and performance of the SPU Website foundation codebase on branch `refactor/codebase-audit-remediation`. The codebase has 23 services, 15 contracts, 54 DTOs, 51 models, and a Filament v3 admin panel. There are currently **7 failing tests out of 366** that must be fixed, plus architectural violations, performance gaps, and code quality issues to address.

All work must strictly follow the rules in `#[[file:AGENTS.MD]]`, `#[[file:Docs/ARCHITECTURE.md]]`, and `#[[file:Docs/BACKEND_RULES.md]]`. Never drift beyond the homepage + admin panel foundation scope.

---

## Phase 1 — Fix All 7 Failing Tests (CRITICAL — Do This First)

Fix every failing test. Do not skip, weaken, or delete any test. The fix must be in the production code, not the test assertions.

### 1.1 Architecture Guard Violation — Filament imports forbidden models

**Test:** `ArchitectureGuardTest::test_filament_does_not_import_forbidden_models`

**Failures:**
- `ManageHomepage.php` uses `HomepageDraft::` directly (static call to Eloquent model)
- `PageResource.php` imports `App\Models\PageTranslation` and uses `PageTranslation::` directly

**Fix:** Remove all direct Eloquent model usage from Filament pages/resources. These files must only interact with services via injected interfaces. If `ManageHomepage` needs to check draft state, it should call a method on `HomepagePublishingServiceInterface` (which already has `hasEditableDraft()` and `latestHomepageState()`). If `PageResource` needs translation data, it should go through `PageServiceInterface`. Add any missing interface methods if truly needed, then implement them in the service.

### 1.2 Faculty Editor Access — PageResource and RoleBasedVisibility

**Tests:**
- `PX06\PageResourceTest::test_faculty_editor_can_access_page_resource`
- `PX06\RoleBasedVisibilityTest::test_faculty_editor_sees_only_scoped_pages_and_media`

**Root cause:** `PagePolicy::manage()` only returns `true` for `editor` role. The `faculty_editor` role is excluded. `PageResource::canAccess()` calls `Gate::allows('manage-pages')` which delegates to `PagePolicy::manage()`.

**Fix:** Update `PagePolicy::manage()` (and `viewAny()`) to also allow `faculty_editor`. The test expects `faculty_editor` can access `PageResource` and `MediaAssetResource` but not homepage, menu, settings, users, or audit. The policy docblock says "page management remains editor-only until a later phase" but the tests explicitly require `faculty_editor` access. The tests are the source of truth — fix the policy.

### 1.3 Settings Case Mismatch — "Apply Now" vs "Apply now"

**Tests:**
- `SettingsShellTest::test_apply_cta_student_portal_and_staff_access_resolve_through_services`
- `SettingsShellTest::test_settings_updates_invalidate_cached_settings_and_navigation_payloads`
- `NavigationShellTest::test_utility_navigation_payload_resolves_for_ar_and_en`

**Root cause:** `SettingsSeeder.php` seeds `'Apply Now'` (capital N) but the tests assert `'Apply now'` (lowercase n). The `NavigationSeeder.php` seeds `'Apply now'` (lowercase). The two seeders are inconsistent.

**Fix:** Normalize the `SettingsSeeder` to use `'Apply now'` (lowercase n) to match the tests and the `NavigationSeeder`. Do NOT change the test assertions.

### 1.4 LaunchValidation Environment Option

**Test:** `PX08\LaunchValidationTest::test_launch_validate_accepts_environment_option`

**Root cause:** The test seeds data but is missing the `choose_your_path` section (only 10 of 11 sections are seeded in `seedValidData()`). The `launch:validate` command likely fails because the homepage rendering check expects all 11 sections. The test's `seedValidData()` method creates sections for `hero, hero_stats, academic_faculties, achievements_highlights, university_news, research_studies, events_activities, medical_facilities_services, bottom_stats, footer` — missing `choose_your_path`.

**Fix:** Add `'choose_your_path'` to the `$sectionKeys` array in the test's `seedValidData()` method. If the `launch:validate` command has additional checks that fail with the `--environment=production` flag, investigate and fix those too. The command must exit 0 when valid data is present.

---

## Phase 2 — Architectural Compliance Sweep

After all tests pass, audit every file for architecture rule violations.

### 2.1 Eliminate all direct Eloquent usage in Filament layer

Scan every file in `app/Filament/Pages/` and `app/Filament/Resources/` (including subdirectories like `Resources/*/Pages/`). Any file that:
- Imports `App\Models\*` (except for the required `protected static ?string $model = X::class` in Resource classes)
- Calls `Model::query()`, `Model::find()`, `Model::create()`, `Model::where()`, or any static Eloquent method
- Uses `$record->relationship()` to run queries

...must be refactored to delegate that logic to the appropriate service via its interface. The `ArchitectureGuardTest` enforces this — make sure it passes after changes.

### 2.2 Verify all service public methods return DTOs, not models

Grep every service in `app/Services/` for public methods. Confirm none return raw Eloquent models. Check return type declarations. If any public method returns `Model`, `Builder`, `Collection` of models (without DTO mapping), or uses `->get()` without mapping, fix it.

### 2.3 Verify controllers are model-free

Confirm no controller in `app/Http/Controllers/` imports or references any Eloquent model. The `ArchitectureGuardTest` checks this, but do a manual sweep to catch edge cases the regex might miss (e.g., inline `new Model()` without an import).

### 2.4 Ensure all interfaces are bound

Every interface in `app/Contracts/` must be bound in `AppServiceProvider`. The `ArchitectureGuardTest::test_all_contracts_have_bindings` enforces this. Verify it passes.

---

## Phase 3 — Service Layer Quality Improvements

### 3.1 Reduce service class sizes

Several services are very large:
- `HomepagePublishingService` — has 15+ private methods for serialization/deserialization
- `ManageHomepage.php` — 1100+ lines with 11 section field builders

For `ManageHomepage.php`: Extract the per-section field builder methods (`heroFields`, `heroStatsFields`, etc.) into a dedicated `HomepageFormSchema` support class or trait. The Filament page should only orchestrate actions and delegate form building.

For `HomepagePublishingService`: The serialization/deserialization helpers (`sectionFromArray`, `translationFromArray`, `serializeSections`, etc.) overlap with `HomepagePayloadMapper`. Consolidate into `HomepagePayloadMapper` and have the service delegate to it.

### 3.2 Eliminate code duplication

- `PreviewService::buildHomepagePreview()` duplicates section-from-draft mapping logic that also exists in `HomepageDraftReader` and `HomepagePublishingService`. Consolidate into one shared mapper.
- `HomeController::findSection()` and `PreviewController::findSection()` are identical private methods. Extract to a shared trait or helper.
- `PageService::updateArabicTranslation` / `updateEnglishTranslation` and `updateArabicSeo` / `updateEnglishSeo` are thin wrappers that just pass a locale string. This is fine architecturally but verify the interface actually needs both locale-specific methods vs a single `updateTranslation(int $pageId, string $locale, ...)`.

### 3.3 Add missing return type declarations

Scan all service public methods for missing return types. PHP 8.2 supports full type declarations — every public method should have an explicit return type. Fix any that are missing.

### 3.4 Strengthen DTO consistency

- Verify all DTOs in `app/DTOs/` are `final readonly class` with typed constructor properties
- Verify no DTO has methods or logic beyond the constructor
- Verify no DTO uses `mixed` type where a more specific type is possible

---

## Phase 4 — Performance Improvements

### 4.1 N+1 query prevention

Review these hot paths for N+1 queries:
- `HomepageSectionService::getPublicHomepage()` — loads sections then maps each with translations. Ensure `with('translations')` eager loading is applied.
- `PageService::getPublicPageBySlug()` → `PagePublicReadService` — ensure page + translations + seoMeta are eager loaded in a single query.
- `MenuService::getHeaderTree()` / `getFooterTree()` — menu items with children. Ensure the full tree is loaded in one query with `with('children')` or a flat query + in-memory tree build.
- `NavigationService::getFullNavigationPayload()` — calls multiple services. Ensure each sub-call is already optimized.

### 4.2 Cache key consistency

Review all cache keys across `CacheService`, `CachePublicPages` middleware, and service-level caching. Ensure:
- All cache keys include locale
- Cache tags are used consistently (e.g., `homepage`, `pages`, `navigation`, `settings`)
- No orphan cache keys that never get invalidated
- The `flushTag` calls in event listeners match the tags used when caching

### 4.3 Database query optimization

- Check that the composite performance indexes migration (`2026_04_30_000002_add_composite_performance_indexes.php`) covers the actual query patterns used by services
- Verify `HomepageSection` queries filter by `is_enabled` and order by `sort_order` — both should be indexed
- Verify `Page` queries on `slug + status + is_enabled` have a composite index
- Verify `MenuItem` queries on `type + locale + is_enabled + sort_order` are indexed

### 4.4 Optimize HomepagePayloadMapper

`HomepagePayloadMapper` has static methods that iterate arrays and build DTOs. Ensure:
- No unnecessary array copies
- No repeated string operations
- Null checks are done early to short-circuit

---

## Phase 5 — Security & Robustness

### 5.1 Input sanitization coverage

- Verify `HtmlSanitizer` is called on all user-supplied HTML content before storage (page body, section payloads, settings with HTML)
- Verify `MediaService` blocks SVG uploads (already done per commit history, but verify the check is in the upload validation)
- Verify preview tokens are hashed at rest (already done, but verify `PreviewTokenStore` uses HMAC-SHA256 consistently)

### 5.2 Optimistic locking completeness

- `HomepagePublishingService::saveDraft()` and `PageService::saveDraft()` both support `expectedVersion` for optimistic locking
- Verify the `ConflictException` is thrown correctly when versions mismatch
- Verify the `DraftConflictDetected` event is dispatched on conflict
- Verify the `version` column is incremented on every save

### 5.3 Authorization completeness

- Verify every Filament page/resource has a `canAccess()` method that checks the appropriate Gate
- Verify the `before()` method in every Policy returns `true` only for `super_admin`
- Verify `faculty_editor` cannot access homepage, menu, settings, users, or audit (per the test expectations)
- Verify locked accounts cannot authenticate (already tested, but verify the middleware chain)

---

## Phase 6 — Test Suite Improvements

### 6.1 Increase test coverage for refactored code

After refactoring, ensure:
- Any new support classes (e.g., `HomepageFormSchema`) have unit tests
- Any new interface methods have corresponding service tests
- The architecture guard test still passes with all changes

### 6.2 Fix any tests broken by refactoring

If refactoring changes method signatures, moves logic, or renames classes, update all affected tests. Never delete a test to make the suite pass.

### 6.3 Run the full test suite

After all changes, run `php artisan test --exclude-group=property` and confirm 0 failures. Then run `php artisan test` (full suite including property tests) and confirm 0 failures.

---

## Constraints

1. **Do not expand scope** — This is homepage + admin panel foundation only. Do not build new modules.
2. **Do not change the database schema** — No new migrations unless absolutely required to fix a bug. The existing schema is the source of truth.
3. **Do not remove or weaken tests** — Fix production code to match test expectations, not the other way around.
4. **Do not introduce new packages** — Use what's already in `composer.json`.
5. **Follow the architecture rules in AGENTS.MD** — Service layer is the only place for business logic. Controllers and Filament are thin. Models are passive. All dependencies use interfaces.
6. **Maintain backward compatibility** — All existing interface method signatures must remain unchanged unless a method is being added. Never remove or rename an existing interface method.
7. **Every phase must end with all tests passing** — Do not proceed to the next phase if tests are failing.

---

## Success Criteria

- [ ] All 366+ tests pass (0 failures)
- [ ] `ArchitectureGuardTest` passes with no violations
- [ ] No Eloquent model imports in controllers
- [ ] No Eloquent model static calls in Filament (except `$model` property)
- [ ] All service public methods have explicit return types
- [ ] All DTOs are `final readonly` with typed properties
- [ ] No N+1 queries on homepage, page, or navigation hot paths
- [ ] Cache keys are locale-aware and tag-invalidation is consistent
- [ ] `php artisan launch:validate` exits 0 with seeded data
- [ ] No code duplication across PreviewService / HomepageDraftReader / HomepagePublishingService for section mapping
- [ ] `ManageHomepage.php` is under 600 lines (field builders extracted)
- [ ] Faculty editor can access PageResource and MediaAssetResource but nothing else

---

## Reference Files

- `#[[file:AGENTS.MD]]`
- `#[[file:Docs/ARCHITECTURE.md]]`
- `#[[file:Docs/BACKEND_RULES.md]]`
- `#[[file:Docs/Dev-Handoff-Report-PX05-PX08.md]]`
- `#[[file:app/Providers/AppServiceProvider.php]]`
- `#[[file:app/Providers/EventServiceProvider.php]]`
- `#[[file:bootstrap/app.php]]`
- `#[[file:tests/Feature/ArchitectureGuardTest.php]]`
