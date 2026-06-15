# SPU Backend Foundation Enhancement Todo

## 1. Document Metadata

| Field | Value |
| --- | --- |
| Document purpose | Evidence-based implementation backlog for the SPU Laravel/Filament backend foundation hardening phase |
| Generated | 2026-06-15 |
| Repository | `C:\Un Web\SPU-BACKEND\SPU_Website` |
| Branch observed during audit | `try-some-enhances` |
| Baseline commit observed during audit | `37acc065326928c7d3bcc15b84d8257dec829e1d` |
| Architecture source of truth | `Docs/ARCHITECTURE.md` |
| Stack baseline | PHP 8.2, Laravel 12, Filament v3, PHPUnit 11, Larastan/PHPStan |
| Active test framework | PHPUnit 11 |
| Pest status | Not installed; do not assume or install without explicit approval |
| Active spec governance target | Spec Kit, not yet installed |
| Non-active spec/tooling inputs | Existing `.kiro/specs/**` and `.github/copilot-instructions.md` are repository evidence only, not active governance |
| Scope | Homepage plus admin panel foundation only |
| Modified by this planning task | `enhance_todo.md` only |
| Status | Ready for review and P0 execution |

This document is a planning artifact. It does not implement application code, migrations, config, routes, tests, lock files, or existing documentation changes.

Clarification on Kiro/Copilot: this roadmap does not require Kiro or Copilot. Existing Kiro and Copilot instruction files are treated only as stale or historical repository inputs that can mislead future work if left unmarked. Spec Kit is the preferred future spec workflow, but it must be introduced as its own approved change after P0 architecture reconciliation.

Because PHPUnit 11 is already installed and supported in this repository, architecture enforcement should first strengthen the existing PHPUnit guard rather than introducing Pest as a new dependency.

## 2. Executive Decision Summary

The repository is not greenfield and should not be regenerated from older prompt packs. It already contains a serious Laravel/Filament backend foundation with service contracts, DTOs, admin workflows, preview tokens, homepage CMS structure, policies, and tests. The correct next step is focused reconciliation and hardening.

Final executive decisions:

| Decision | Outcome |
| --- | --- |
| Source of truth | Use `Docs/ARCHITECTURE.md` as the current architecture baseline. |
| Stack | Stay on PHP 8.2, Laravel 12, Filament v3, PHPUnit 11, Larastan/PHPStan. |
| Pest | Do not assume or install Pest. Keep architecture tests PHPUnit-based unless explicitly approved later. |
| Filament | Allow Eloquent for Filament resource CRUD mechanics, but require services for business workflows. |
| Homepage sections | Enforce exactly 11 fixed section keys. |
| Translation model | Keep explicit AR/EN tables; do not reintroduce global polymorphic translations. |
| Spec governance | Do not use Kiro/Copilot as active governance. Adopt Spec Kit later as a separate approved change. |
| Locale | Arabic appears to be intended default, but fallback policy must be explicitly decided and tested. |
| Publication status | Add typed publication status modeling instead of repeated raw strings. |
| Publish side effects | Reconcile transactions, events, cache invalidation, preview token invalidation, and audit logging into one policy. |
| Support helpers | Move DB side effects out of `app/Support` or document a narrow legacy exception. |
| Architecture docs | Accepted decisions from this roadmap must eventually be merged back into `Docs/ARCHITECTURE.md`. |

Recommended first execution batch:

1. `ARCH-P0-007`: Neutralize stale non-spec guidance.
2. `ARCH-P0-005`: Move or formalize `app/Support/LegacyImport` database side effects.
3. `ARCH-P0-006`: Expand architecture guard coverage.
4. `ARCH-P0-001`: Resolve locale default and fallback policy.
5. `ARCH-P0-002`: Introduce `PublicationStatus` enum.

This order is intentional. Stale guidance and known exception decisions should be handled before making the architecture guard stricter, otherwise the guard may produce noisy failures for unresolved but known exceptions.

## 3. Repository Evidence Summary

| Area | Evidence | Current Assessment | Required Action |
| --- | --- | --- | --- |
| Architecture source | `Docs/ARCHITECTURE.md:3-8` defines MVC plus Service Layer. | Strong and current. | Keep as source of truth. |
| Current scope | `Docs/ARCHITECTURE.md:11-30` limits this phase to homepage/admin foundation. | Clear boundary. | Prevent full-module scope creep. |
| Controller rules | `Docs/ARCHITECTURE.md:57-67` requires thin controllers with interfaces and no Eloquent. | Correct rule. | Strengthen executable guard coverage. |
| Service rules | `Docs/ARCHITECTURE.md:71-88` says services own business logic and do not return raw models. | Correct rule. | Audit public service methods and contracts. |
| Support helper rules | `Docs/ARCHITECTURE.md:111-118` says Support helpers must not write DB or perform side effects. | Partially violated by legacy import helpers. | Move DB logic or document exception. |
| DTO rules | `Docs/ARCHITECTURE.md:129-141` requires `final readonly` DTOs with no logic. | Mostly compliant. | Decide `HomepageDTO::findSection()` allowlist or move helper. |
| Translation strategy | `Docs/ARCHITECTURE.md:207-224` requires explicit translation tables. | Compliant in architecture. | Guard against old polymorphic model. |
| Homepage CMS | `Docs/ARCHITECTURE.md:226-253` defines fixed 11-section CMS page. | Compliant in contract. | Remove stale 10-section references from active guidance. |
| Interface rules | `Docs/ARCHITECTURE.md:280-318` defines return and critical rule checks. | Correct rule set. | Enforce with tests. |
| Composer stack | `composer.json:9-15` requires PHP `^8.2`, Laravel `^12.0`, Filament `^3.3`. | Current stack confirmed. | Do not upgrade stack in this hardening task. |
| Test tools | `composer.json:17-25` includes PHPUnit and Larastan; Pest package not found. | PHPUnit-based test suite. | Do not assume Pest. |
| Workflow quality gates | `.github/workflows/security-ci.yml:51-57` has Pint/PHPStan as `continue-on-error`. | Not launch-grade yet. | Make gates enforceable after fixes. |
| Public routes | `routes/web.php:18` redirects `/` to `/ar`; `routes/web.php:23-25` constrains `ar\|en`. | Arabic-first route behavior. | Align locale config/fallback. |
| Preview route | `routes/web.php:44` defines `/{locale}/preview` inside cached locale group. | Needs cache-bypass tests. | Add preview/cache matrix tests. |
| Homepage keys | `HomepageSectionServiceInterface.php:18-30` defines 11 keys. | Compliant. | Keep architecture guard. |
| Broad page contract | `PageServiceInterface.php:27-123` includes CRUD, translation, SEO, draft, publish, preview, breadcrumbs. | Functional but broad. | Split or document facade decision. |
| DTO helper logic | `HomepageDTO.php:21-33` contains `findSection()`. | Minor DTO purity drift. | Decide allowlist or move to presenter/helper. |
| Status strings | `HomepageDraftDTO.php:16`, `PageDraftDTO.php:15`, `PageMetadataDTO.php:22` use strings. | Typed status missing. | Add `PublicationStatus`. |
| Homepage publish | `HomepagePublishingService.php:126-208` uses transaction, cache invalidation, token invalidation, audit. | Good foundation. | Reconcile with domain event policy. |
| Page publish | `PageService.php:524-573` uses transaction, validation, cache invalidation, token invalidation, audit. | Good foundation. | Reconcile with domain event policy. |
| Events | `EventServiceProvider.php:31-34` registers publish listeners; grep found no publish dispatch calls. | Partial/dead workflow risk. | Decide dispatch or remove. |
| Preview tokens | `PreviewTokenStore.php:24-66` hashes random tokens; `PreviewTokenStore.php:138-170` snapshots drafts. | Strong direction. | Harden lifecycle tests. |
| Architecture tests | `ArchitectureGuardTest.php:25-202` checks some boundaries. | Useful but incomplete. | Expand guard tests. |
| Locale config | `.env.example:11-12` defaults locale/fallback to `en`. | Conflicts with route/spec Arabic default. | Decide and patch. |
| Kiro specs | `.kiro/specs/.../requirements.md:15-16` says 10-section homepage. | Stale/historical only. | Do not use as active governance. |
| Copilot instructions | `.github/copilot-instructions.md:3-13` says Laravel 11 and bans Filament models. | Stale/historical only. | Neutralize or mark stale if active. |
| Spec Kit | No `.specify/` or root `specs/` found. | Not installed. | Adopt later in dedicated change. |

## 4. Architecture Compliance Matrix

| Architecture Rule | Status | Evidence | Impact | Required Action |
| --- | --- | --- | --- | --- |
| Controllers do not import Eloquent models | Compliant based on inspected files | Audit searches found no confirmed `App\Models` imports in controllers. | Preserves service boundary. | Add stronger scanner for imports and query calls. |
| Controllers inject service interfaces | Mostly compliant based on inspected files | Controllers inspected use service contracts for public workflows. | Keeps controllers orchestration-only. | Add guard for concrete `App\Services` injection. |
| Contracts do not import/return Eloquent | Mostly compliant based on inspected files | `app/Contracts/**` inspection found no model imports. | Strong DTO boundary. | Add return-type and import scanner. |
| Services own business logic | Mostly compliant | Publishing, preview, menu, settings, media workflows live in services. | Correct architecture direction. | Audit for raw model returns and business leakage. |
| Public service methods do not return raw models | Mostly compliant | Contract returns are DTOs/bool/collections; internal `PreviewTokenStore` returns model but is not a public contract. | Boundary mostly preserved. | Add service/contract scanners and keep `PreviewTokenStore` internal. |
| DTOs are `final readonly` with no logic | Partial | DTO grep found many `final readonly`; `HomepageDTO::findSection()` has helper logic. | Small DTO purity drift. | Decide allowlist or move helper. |
| Models contain no business logic | Not fully re-audited | Architecture states model limits; models were inspected only selectively. | Model drift can hide business rules. | Add model audit task if scope expands. |
| Middleware has no Eloquent/business logic | Mostly compliant based on inspected files | No model imports found; webhook middleware has cache nonce side effect. | Low current risk. | Add middleware scanner. |
| Filament does not call Actions directly | Compliant | No `app/Actions/` directory and no direct `App\Actions` usage found. | Good workflow boundary. | Keep guard. |
| Filament uses services for business workflows | Mostly compliant | Manage homepage/menu/settings/media/page flows use service interfaces. | Good admin architecture. | Audit duplicated validation/workflow logic. |
| Support helpers are DB-free | Non-compliant or exception needed | `app/Support/LegacyImport/**` queries/writes Eloquent. | Architecture boundary drift. | Move to services or document exception. |
| Homepage uses fixed 11 keys | Compliant | `HomepageSectionServiceInterface::SECTION_KEYS` and guard test. | Strong scope control. | Keep guard. |
| Draft content does not leak publicly | Mostly compliant based on tests/design | Preview is tokenized and public reads use published state. | Critical security requirement. | Add preview/cache matrix tests. |
| Publish validates, caches, audits | Partial/coherent but duplicated risk | Services perform these; events are registered but not dispatched. | Maintenance and stale-cache risk. | Reconcile side-effect policy. |
| Locale fallback is consistent | Non-compliant/undecided | `.env.example` says `en`; route/spec imply `ar`. | Public content and SEO risk. | Decide and test. |
| Pest architecture tests exist | Not applicable | Pest is not installed. | No current issue. | Keep PHPUnit guard unless Pest is explicitly approved. |
| Spec Kit governs changes | Not yet | No `.specify/` or root `specs/`. | Governance gap but not code risk. | Adopt later in dedicated change. |

## 5. Final Technical Decisions

This section summarizes the architecture decisions for executive readability. Detailed implementation tasks below carry affected files, tests, rollback, risk, and acceptance criteria for each decision.

| ID | Decision | Status | Consequence |
| --- | --- | --- | --- |
| ADR-001 | `Docs/ARCHITECTURE.md` is the current architecture source of truth. | Accepted | Stale Kiro/Copilot text cannot override it. |
| ADR-002 | Preserve PHP 8.2, Laravel 12, Filament v3, PHPUnit 11, Larastan/PHPStan baseline. | Accepted | No framework/test-stack upgrade in this hardening plan. |
| ADR-003 | Do not install or assume Pest. | Accepted | Architecture guard remains PHPUnit-based unless approved later. |
| ADR-004 | Filament resources may use Eloquent for CRUD mechanics; workflows must call service interfaces. | Accepted | Avoid unrealistic Filament bans while preserving business boundaries. |
| ADR-005 | Homepage is exactly 11 fixed CMS sections. | Accepted | Remove or neutralize active 10-section assumptions. |
| ADR-006 | Use explicit AR/EN translation tables, not global polymorphic translations. | Accepted | New CMS-managed content must follow explicit locale storage. |
| ADR-007 | Public service interfaces return DTOs, collections, bool, paginator where appropriate, or composite payload arrays only. | Accepted | Raw Eloquent models do not cross public contracts. |
| ADR-008 | Internal integer IDs remain default; opaque tokens are used for public secure handles. | Accepted | No global UUID/ID migration without concrete need. |
| ADR-009 | Arabic is likely product default, but exact fallback policy requires approval. | Proposed | Do not silently change fallback behavior without tests. |
| ADR-010 | Publishing needs one coherent transaction/cache/event/audit policy. | Proposed | Avoid dead events and duplicate invalidation. |
| ADR-011 | `app/Support` should be deterministic and DB-free unless a legacy exception is explicitly approved. | Proposed | Legacy import helpers must move or be documented. |
| ADR-012 | Spec Kit is the desired future spec workflow, but must be introduced separately. | Proposed | Do not initialize Spec Kit while changing architecture/application code. |
| ADR-013 | Accepted decisions from this roadmap must eventually be merged back into `Docs/ARCHITECTURE.md`. | Proposed | Prevents `enhance_todo.md` from becoming a competing architecture source. |

Filament integration rationale: Filament resources are Eloquent-backed by design, including resource queries that start from `getEloquentQuery()`. The architecture boundary is therefore not "no Eloquent in Filament". The boundary is: Filament may use Eloquent for resource mechanics, while business workflows such as publishing, preview generation, settings updates, menu updates, media uploads, and account state changes must call service interfaces.

Rejected alternatives:

| Alternative | Reason Rejected |
| --- | --- |
| Regenerate foundation from old prompts | Current repository is already partially implemented and prompt assumptions are stale. |
| Ban all Eloquent from Filament | Conflicts with normal Filament resource behavior. |
| Add Pest automatically | Dependency is absent and PHPUnit already exists. |
| Treat Kiro specs as active source of truth | They contain stale 10-section homepage language. |
| Treat Copilot instructions as active source of truth | They mention Laravel 11 and over-ban Filament model usage. |
| Adopt Spec Kit inside the same code-change task | Would create process/tooling drift while architecture contradictions still exist. |
| Keep `enhance_todo.md` as the permanent architecture source | Architecture decisions belong in `Docs/ARCHITECTURE.md` after approval. |

## 6. Target Architecture Enhancements

After this roadmap is implemented, the target architecture should have the following shape:

- Controllers remain thin orchestration endpoints.
- Controllers and higher layers inject interfaces from `app/Contracts`.
- Public service interfaces are the only application workflow boundary for controllers and admin workflows.
- Services own business logic, transactions, publish rules, cache decisions, audit decisions, and Eloquent access.
- Internal service collaborators are allowed when they are owned by services and do not become a new controller-facing layer.
- Filament resources use Eloquent only for Filament CRUD/table/form mechanics.
- Filament business actions call service interfaces and do not duplicate publishability rules.
- Publication state is represented by `PublicationStatus` or an equivalent typed value object.
- Homepage publishing and page publishing have clear contracts, either as separate publishing services or an explicitly documented facade decision.
- Preview resolution produces typed DTO payloads at public boundaries.
- Draft content is reachable only through valid, non-expired preview tokens.
- Public cache never stores preview, authenticated, admin, non-GET, or draft responses.
- Cache invalidation, preview token invalidation, event dispatch, and audit logging follow one documented side-effect policy.
- `app/Support` contains deterministic helpers only, with legacy import DB exceptions removed or narrowly documented.
- Composite arrays used for homepage/navigation shell payloads have PHPDoc array shapes where DTOs are not appropriate.
- Display-card DTOs for article/research/event homepage sections are documented as display contracts, not full module implementations.
- Architecture guard tests enforce the rules in `Docs/ARCHITECTURE.md`.
- Accepted decisions are merged back into `Docs/ARCHITECTURE.md` after approval.
- Spec Kit governs future feature planning after it is intentionally introduced.

## 7. Implementation Roadmap

### Phase P0: Architecture Reconciliation And Safety Gates

Goal: Resolve contradictions before new feature work.

Deliverables:

- Stale Kiro/Copilot guidance neutralized or marked historical.
- Support-layer legacy import DB side effects moved or documented.
- Architecture guard expanded.
- Locale default and fallback decision implemented or explicitly documented.
- `PublicationStatus` enum introduced.
- Page publishing contract boundary clarified.
- Publish event/cache/audit/token policy reconciled.

Exit criteria:

- `php artisan about` succeeds.
- `php artisan route:list` succeeds.
- `php artisan test --filter=ArchitectureGuardTest` succeeds.
- Targeted publish/preview tests succeed.
- No public service contract returns raw Eloquent models.
- Draft content does not appear publicly without preview token.

### Phase P1: Workflow Hardening

Goal: Make validation, authorization, transactions, publishing, preview, cache, audit, menu, settings, and media behavior deterministic.

Deliverables:

- Publish result/preflight semantics standardized.
- Preview token lifecycle tests expanded.
- Filament workflow validation delegated to services.
- Public cache bypass matrix covered.
- Form Request and Policy boundaries documented and tested.
- Transaction and audit/event/after-commit policies documented and tested.
- Composite array PHPDoc shapes added.
- Accepted architecture decisions patched into `Docs/ARCHITECTURE.md`.

Exit criteria:

- Homepage/page publish workflows have success, failure, rollback, audit, cache, token invalidation tests.
- Admin workflows call services for business operations.
- FormRequest and Policy coverage exists for public/admin write paths.
- `Docs/ARCHITECTURE.md` does not contradict this roadmap's accepted decisions.

### Phase P2: Governance, Quality Gates, And Foundation Completeness

Goal: Make the foundation maintainable and launch-ready.

Deliverables:

- Quality gates are enforceable.
- Spec Kit introduced only after approval.
- Display-card DTO scope documented.
- Menu/navigation, settings, and media tests hardened.
- Lead capture scope bounded.

Exit criteria:

- Automated checks fail on Pint/PHPStan/tests after issues are resolved or baselined.
- Spec Kit pilot exists if approved.
- No task expands into full News/Research/Events/Facilities/Admissions/CRM modules.

### First Execution Batch

Recommended first batch for the next implementation agent:

1. `ARCH-P0-007`: Neutralize stale non-spec guidance.
2. `ARCH-P0-005`: Move or formalize `app/Support/LegacyImport` DB side effects.
3. `ARCH-P0-006`: Expand PHPUnit architecture guard.
4. `ARCH-P0-001`: Resolve locale default/fallback policy.
5. `ARCH-P0-002`: Introduce `PublicationStatus` enum.

Do not start with Spec Kit initialization. Do not edit Kiro/Copilot files unless the task is specifically to neutralize stale active guidance.

## 8. Detailed Task Backlog

### ARCH-P0-001: Resolve Locale Default And Fallback Policy

Priority: P0.
Status: Proposed.
Type: Config, service behavior, tests, documentation follow-up.
Rationale: Public routes redirect `/` to `/ar`, but `.env.example` defaults locale and fallback to `en`. This can affect content fallback, SEO, cache keys, and admin defaults.
Evidence: `routes/web.php:18`, `.env.example:11-12`, `.kiro/specs/.../requirements.md:18` as historical evidence only.
Files: `.env.example`, `.env.production.example` if present, `config/app.php`, locale middleware, SEO/navigation services, public runtime tests.
Steps: Confirm desired fallback behavior; patch config/env examples; review fallback code; review SEO/cache assumptions; add AR/EN tests.
Acceptance: Config examples match policy; `/` redirects to `/ar`; AR/EN fallback is deterministic; locale-aware cache is tested.
Tests: `php artisan test --filter=PublicRuntimeTest`, `php artisan test --filter=NavigationShellTest`, targeted locale tests.
Dependencies: Product decision on fallback behavior.
Risk: Visible content language can change.
Rollback: Revert config/env/test changes and restore previous fallback behavior.

### ARCH-P0-002: Introduce `PublicationStatus` Enum

Priority: P0.
Status: Proposed.
Type: Domain modeling, DTOs, services, tests.
Rationale: Publication states are repeated as raw strings across DTOs and services.
Evidence: `HomepageDraftDTO.php:16`, `PageDraftDTO.php:15`, `PageMetadataDTO.php:22`, `HomepagePublishingService.php`, `PageService.php`, `PreviewTokenStore.php:21`.
Files: New `app/Enums/PublicationStatus.php`, DTOs, services, validators, factories, tests.
Steps: Add backed enum; replace literals safely; expose strings at DTO/template boundaries if needed; add state transition tests.
Acceptance: Workflow code uses enum/constants; invalid statuses are rejected; public output compatibility is preserved.
Tests: `HomepageCmsWorkflowTest`, `PageServiceIntegrationTest`, `PublicRuntimeTest`, enum unit tests.
Dependencies: None.
Risk: DTO constructor changes can break consumers.
Rollback: Keep enum internally and expose string values externally.

### ARCH-P0-003: Split Or Clarify Page Publishing Contract Boundaries

Priority: P0.
Status: Proposed.
Type: Contract design, service refactor, tests.
Rationale: `PageServiceInterface` is broad and includes CRUD, translation, SEO, draft, publish, public reads, previews, breadcrumbs, and language switching.
Evidence: `PageServiceInterface.php:27-123`, `PageService.php:524-573`, separate `HomepagePublishingServiceInterface` exists.
Files: `PageServiceInterface`, optional `PagePublishingServiceInterface`, `PageService`, optional `PagePublishingService`, Filament page resource actions, provider bindings, tests.
Steps: Inventory publish call sites; decide extract versus facade; if extracting, move publish methods only; bind interface; update consumers.
Acceptance: Page publishing has a clear contract; no concrete service injection; existing editor/public behavior unchanged.
Tests: `ArchitectureGuardTest`, `PageServiceIntegrationTest`, Filament page tests.
Dependencies: ARCH-P0-002 recommended first.
Risk: Interface churn across admin resources.
Rollback: Keep `PageServiceInterface` as documented facade and defer extraction.

### ARCH-P0-004: Reconcile Publish Events, Cache Invalidation, Preview Tokens, And Audit Logging

Priority: P0.
Status: Proposed.
Type: Workflow consistency, events, cache, audit, tests.
Rationale: Publish services currently invalidate cache and audit inline, while domain listeners are registered but publish events are not dispatched.
Evidence: `EventServiceProvider.php:31-34`, `HomepagePublishingService.php:126-208`, `PageService.php:524-573`, grep found no publish event dispatch calls.
Files: Publish services, events, listeners, cache service, audit service, workflow tests.
Steps: Choose side-effect policy; dispatch after successful transaction or remove unused listeners; avoid duplicate invalidation; test success/failure paths.
Acceptance: Events are either dispatched and tested or removed from active workflow; cache invalidates only after successful writes; audit logs are reliable; preview tokens invalidate on publish/unpublish.
Tests: `HomepageCmsWorkflowTest`, `PageServiceIntegrationTest`, `EventListenerTest`.
Dependencies: ARCH-P0-002 recommended.
Risk: Stale public content or duplicate invalidation.
Rollback: Restore current inline behavior while documenting event classes as inactive.

### ARCH-P0-005: Move Or Formalize `app/Support/LegacyImport` DB Side Effects

Priority: P0.
Status: Proposed.
Type: Architecture boundary, service extraction, tests.
Rationale: Support helpers are supposed to be deterministic and DB-free, but legacy import helpers query/write Eloquent and return models.
Evidence: `Docs/ARCHITECTURE.md:111-118`, `TargetIdResolver.php:7-42`, `MigrationLogger.php:7-58`.
Files: `app/Support/LegacyImport/**`, optional legacy import contracts/services, import commands, tests.
Steps: Inventory call sites; choose move versus documented exception; move persistence to services; return DTOs/scalars from public interfaces; add guard.
Acceptance: `app/Support` is deterministic or exceptions are explicit; no public contract returns migration Eloquent models.
Tests: Legacy import service tests, `ArchitectureGuardTest`.
Dependencies: None.
Risk: Import commands may expect model returns.
Rollback: Temporarily allowlist existing helpers with TODO and owner.

### ARCH-P0-006: Expand Architecture Guard Coverage

Priority: P0.
Status: Proposed.
Type: Tests, static architecture enforcement.
Rationale: Current architecture guard is useful but does not fully enforce documented rules.
Evidence: `ArchitectureGuardTest.php:25-202`, `Docs/ARCHITECTURE.md:53-141`, `Docs/ARCHITECTURE.md:280-318`.
Files: `tests/Feature/ArchitectureGuardTest.php`, optional `tests/Architecture/**`, no Pest unless approved.
Steps: Add scanners for controllers, contracts, middleware, DTOs, Support, Action usage, concrete service injection, and homepage keys; add allowlist only for approved exceptions.
Acceptance: Guard fails with clear file/rule output; existing exceptions are documented; Pest is not required.
Tests: `php artisan test --filter=ArchitectureGuardTest`.
Dependencies: ARCH-P0-005 if Support allowlist is needed.
Risk: Regex false positives can block work.
Rollback: Narrow patterns or temporary allowlist with task ID.

### ARCH-P0-007: Neutralize Stale Non-Spec Guidance

Priority: P0.
Status: Proposed.
Type: Documentation governance.
Rationale: Existing Kiro/Copilot files contain stale assumptions. They should not be used as active spec governance when Spec Kit is the intended future workflow.
Evidence: `.kiro/specs/.../requirements.md:15-16` says 10 sections; `.github/copilot-instructions.md:3-13` says Laravel 11 and over-bans Filament Eloquent.
Files: `.kiro/specs/**`, `.github/copilot-instructions.md`, optional docs note.
Steps: Decide whether to mark historical or patch; add header saying not active governance; point to `Docs/ARCHITECTURE.md` and future Spec Kit.
Acceptance: No active guidance claims 10 homepage sections, Laravel 11, or impossible Filament Eloquent bans.
Tests: Documentation grep for stale phrases.
Dependencies: ADR-001, ADR-004, ADR-012.
Risk: Editing historical specs can reduce traceability.
Rollback: Add historical warning headers instead of rewriting content.

### ARCH-P0-008: Audit Controllers For Model Usage And Query Leakage

Priority: P0.
Status: Proposed.
Type: Architecture audit, tests.
Rationale: Controller compliance was inspected, but it should become an explicit audit task and executable guard.
Evidence: `Docs/ARCHITECTURE.md:57-67`, current guard checks model imports only.
Files: `app/Http/Controllers/**`, `ArchitectureGuardTest`.
Steps: Scan imports and query calls; review false positives; add guard for model imports, `::query`, `DB::`, and concrete service injection.
Acceptance: Controllers remain orchestration-only and guard catches new violations.
Tests: `ArchitectureGuardTest`.
Dependencies: ARCH-P0-006.
Risk: Static regex may flag framework classes incorrectly.
Rollback: Narrow scanner patterns.

### ARCH-P0-009: Audit Contracts For Eloquent Imports And Return Types

Priority: P0.
Status: Proposed.
Type: Architecture audit, tests.
Rationale: Contracts are the public service boundary and must not leak Eloquent.
Evidence: `Docs/ARCHITECTURE.md:280-293`, inspected contracts showed no model imports.
Files: `app/Contracts/**`, `ArchitectureGuardTest`.
Steps: Scan for `App\Models`, `Illuminate\Database\Eloquent`, model return hints, and untyped public methods.
Acceptance: All contracts remain DTO/collection/bool/paginator/composite payload compliant.
Tests: `ArchitectureGuardTest`.
Dependencies: ARCH-P0-006.
Risk: Composite arrays need documented exceptions.
Rollback: Allowlist named composite payload methods with PHPDoc requirements.

### ARCH-P0-010: Audit Services For Raw Model Returns

Priority: P0.
Status: Proposed.
Type: Architecture audit, service boundary.
Rationale: Services may use Eloquent internally but public service methods should not return raw models, especially when implementing contracts.
Evidence: `Docs/ARCHITECTURE.md:71-88`; `PreviewTokenStore::resolve()` returns a model but is internal and not a contract.
Files: `app/Services/**`, `app/Contracts/**`, tests.
Steps: Inventory public service methods; compare against contracts; identify internal collaborators; convert public model returns to DTO/scalar where needed.
Acceptance: Contract implementations do not expose raw models; internal exceptions are documented.
Tests: Architecture guard plus targeted service tests.
Dependencies: ARCH-P0-009.
Risk: Internal collaborators can be over-constrained.
Rollback: Limit rule to contract implementations first.

### ARCH-P0-011: Audit Middleware For Eloquent And Domain Logic

Priority: P0.
Status: Proposed.
Type: Architecture audit, middleware tests.
Rationale: Middleware must not contain business logic or Eloquent queries.
Evidence: `Docs/ARCHITECTURE.md:36-54`, audit found no model imports but noted webhook nonce cache side effect.
Files: `app/Http/Middleware/**`, middleware tests, architecture guard.
Steps: Scan imports and DB/query usage; classify cache nonce behavior; add guard for Eloquent and domain service calls.
Acceptance: Middleware stays limited to locale/auth/role/CSRF/throttle/cache/security checks.
Tests: `ArchitectureGuardTest`, middleware pipeline tests.
Dependencies: ARCH-P0-006.
Risk: Security middleware may need non-domain side effects.
Rollback: Allowlist security nonce cache behavior with explanation.

### ARCH-P0-012: Audit Filament For Direct Actions And Workflow Leakage

Priority: P0.
Status: Proposed.
Type: Admin architecture audit.
Rationale: Filament may use Eloquent for CRUD but must not call Actions directly or own business workflows.
Evidence: `Docs/ARCHITECTURE.md:91-102`, no `app/Actions/` or direct Action usage found.
Files: `app/Filament/**`, architecture guard, Filament tests.
Steps: Scan for `App\Actions`; inventory publish/save workflow methods; classify UI-only versus business logic; add guard.
Acceptance: Business workflows delegate to service interfaces; direct Action calls remain blocked.
Tests: `ArchitectureGuardTest`, Filament workflow tests.
Dependencies: ARCH-P0-006.
Risk: Over-banning normal Filament model mechanics.
Rollback: Scope guard to Actions and workflow calls, not `$model` declarations.

### ARCH-P1-001: Standardize Publish Result Semantics

Priority: P1.
Status: Proposed.
Type: Contract behavior, validation DTOs, admin UX.
Rationale: `bool` write results satisfy architecture rules but do not carry structured validation messages without duplicated UI logic.
Evidence: `HomepagePublishingService.php:126-140`, `PageServiceInterface.php:68-85`, `ValidationResultDTO` exists.
Files: Publishing interfaces/services, validators, Filament actions, workflow tests.
Steps: Decide separate preflight validation versus `PublishResultDTO`; move duplicated validation to services; map messages in Filament.
Acceptance: Filament does not duplicate publishability rules; editors receive actionable errors; return rules remain compliant.
Tests: Homepage publish validation tests, Filament publish action tests.
Dependencies: ARCH-P0-003, ARCH-P0-004.
Risk: Contract changes can cause UI churn.
Rollback: Keep publish `bool` and add separate validation methods.

### ARCH-P1-002: Harden Preview Token Lifecycle Tests

Priority: P1.
Status: Proposed.
Type: Security, preview workflow, tests.
Rationale: Preview tokens are hashed and snapshot-bound; lifecycle behavior needs complete regression coverage.
Evidence: `PreviewTokenStore.php:24-66`, `PreviewTokenStore.php:89-107`, `PreviewTokenStore.php:138-170`, `routes/web.php:44`.
Files: Preview services, preview controller tests, public runtime tests.
Steps: Test invalid/expired/tampered tokens; test invalidation on publish; test snapshot immutability; test unsupported locale/device; test cache bypass.
Acceptance: Raw tokens are never stored; invalid tokens cannot render drafts; snapshots are isolated; preview is never cached publicly.
Tests: `PublicRuntimeTest`, `HomepageCmsWorkflowTest`, dedicated preview tests.
Dependencies: ARCH-P0-004.
Risk: Tests may reveal behavior gaps.
Rollback: Keep current behavior and mark gaps as security risks until fixed.

### ARCH-P1-003: Consolidate Filament Workflow Validation Into Services

Priority: P1.
Status: Proposed.
Type: Admin workflow, service validation.
Rationale: Filament should own forms and presentation, not publishability or domain validation rules.
Evidence: `Docs/ARCHITECTURE.md:57-102`; audit noted likely validation duplication in homepage management.
Files: `ManageHomepage`, page resource pages, validators, tests.
Steps: Inventory Filament validation; classify UI versus domain; move domain validation to services; preserve UI messages.
Acceptance: Service validators are authoritative; admin behavior remains unchanged for valid/invalid submissions.
Tests: Filament page tests, service validator tests.
Dependencies: ARCH-P1-001.
Risk: Message mapping can regress admin UX.
Rollback: Keep UI mapping as presentation layer over service validation.

### ARCH-P1-004: Add Public Cache Bypass Matrix Tests

Priority: P1.
Status: Proposed.
Type: Cache safety, middleware tests.
Rationale: Public cache must be locale-aware and must bypass preview, auth, admin, non-GET, and non-public responses.
Evidence: `Docs/ARCHITECTURE.md:243-253`, `routes/web.php:23-25`, `routes/web.php:44`.
Files: `CachePublicPages` middleware, middleware tests, public runtime tests.
Steps: Test GET `/ar`, GET `/en`, preview, authenticated, admin, POST, errors; assert locale keys; assert publish invalidation.
Acceptance: Draft/preview/auth/admin responses are not cached; AR/EN cache entries do not collide.
Tests: Middleware pipeline tests, `PublicRuntimeTest`.
Dependencies: ARCH-P0-004.
Risk: Cache fakes can hide driver behavior.
Rollback: Keep conservative bypass behavior.

### ARCH-P1-005: Make Quality Gates Enforceable

Priority: P1.
Status: Proposed.
Type: Static analysis, formatting, workflow checks.
Rationale: Pint and PHPStan are currently allowed to fail in the workflow.
Evidence: `.github/workflows/security-ci.yml:51-57`.
Files: GitHub workflow, `phpstan.neon`, optional baselines.
Steps: Run Pint/PHPStan; fix or baseline issues; remove `continue-on-error`; verify workflow environment.
Acceptance: Automated checks fail on Pint/PHPStan errors after baseline is controlled.
Tests: `vendor\bin\pint --test`, `vendor\bin\phpstan analyse --memory-limit=1G`, `php artisan test`.
Dependencies: ARCH-P0-006 recommended.
Risk: Gates can block urgent fixes if enabled prematurely.
Rollback: Temporarily restore `continue-on-error` with dated owner/TODO.

### ARCH-P1-006: Define Form Request Validation Boundary

Priority: P1.
Status: Proposed.
Type: Validation architecture, tests.
Rationale: FormRequests should validate request shape and simple input constraints; business validation belongs in services.
Evidence: Existing FormRequests for contact/auth flows; architecture requires controllers stay thin.
Files: `app/Http/Requests/**`, controllers, services, tests.
Steps: Inventory write endpoints; ensure FormRequest coverage; move business rules from requests/controllers into services; add tests.
Acceptance: Every public/admin write route has validation; FormRequests do not perform business workflow decisions.
Tests: Feature tests for invalid/valid submissions, architecture guard if needed.
Dependencies: ARCH-P0-008.
Risk: Moving validation can change error messages.
Rollback: Keep request-level shape validation and migrate business rules incrementally.

### ARCH-P1-007: Define Policy Authorization Coverage

Priority: P1.
Status: Proposed.
Type: Authorization, RBAC, tests.
Rationale: Roles and faculty scope must be enforced consistently by policies/gates, not bypassed in controllers/admin UI.
Evidence: `AppServiceProvider.php:167-185` registers policies/gates; project rules define `super_admin`, `editor`, `faculty_editor`.
Files: `app/Policies/**`, services using `Gate`, Filament resources/pages, auth tests.
Steps: Map each admin/public write action to policy/gate; test role matrix; test locked accounts and faculty scope where applicable.
Acceptance: No write workflow bypasses policy checks; role behavior is covered by tests.
Tests: Policy tests, admin feature tests, auth lockout tests.
Dependencies: ARCH-P0-006.
Risk: Tests may expose missing faculty scope logic.
Rollback: Add explicit denies while filling missing scope behavior.

### ARCH-P1-008: Define Transaction Boundary Policy

Priority: P1.
Status: Proposed.
Type: Service transaction architecture, tests.
Rationale: Multi-write workflows need consistent transaction boundaries and side effects outside failed transactions.
Evidence: Homepage/page/menu/settings services use transactions in audited code.
Files: Publishing services, menu/settings services, audit/cache/event code, tests.
Steps: Inventory multi-write workflows; document transaction boundary rules; ensure side effects occur after commit; add rollback tests.
Acceptance: Failed writes leave no partial state and do not invalidate cache/audit incorrectly.
Tests: Publish/menu/settings rollback tests.
Dependencies: ARCH-P0-004.
Risk: Moving side effects after commit can change timing.
Rollback: Restore current transaction layout with explicit known-risk note.

### ARCH-P1-009: Define Domain Event, Audit, And After-Commit Policy

Priority: P1.
Status: Proposed.
Type: Events, audit, observability.
Rationale: Domain events, audit logs, and cache listeners need one clear policy to avoid duplicates or dead registrations.
Evidence: Registered listeners exist; publish dispatches were not found; audit logging is inline.
Files: Events, listeners, services, `EventServiceProvider`, audit service, tests.
Steps: Decide which events exist; decide audit inline versus listener; mark listeners after-commit if needed; test exact side effects.
Acceptance: Every domain event is dispatched or removed; audit policy is documented; listeners do not run on failed transactions.
Tests: Event listener tests, publish workflow tests.
Dependencies: ARCH-P0-004, ARCH-P1-008.
Risk: Duplicate audit/cache records.
Rollback: Keep audit inline and remove unused events from active registration.

### ARCH-P1-010: Add PHPDoc Array Shapes For Composite Payloads

Priority: P1.
Status: Proposed.
Type: Type safety, static analysis.
Rationale: Composite view payload arrays are allowed, but their shape should be documented to reduce drift.
Evidence: `Docs/ARCHITECTURE.md:81-88`, homepage/navigation shell returns can be composite arrays.
Files: service interfaces, services, DTOs if introduced, PHPStan config if needed.
Steps: Identify composite array returns; add PHPDoc shapes; convert entity arrays to DTOs where appropriate; run PHPStan.
Acceptance: Composite arrays have documented shapes; entity data is not returned as ambiguous arrays.
Tests: PHPStan, affected feature tests.
Dependencies: ARCH-P0-009.
Risk: PHPDoc can become stale if not tested by static analysis.
Rollback: Add shapes incrementally to high-risk payloads first.

### ARCH-P1-011: Patch `Docs/ARCHITECTURE.md` With Accepted Decisions

Priority: P1.
Status: Proposed.
Type: Architecture documentation.
Rationale: `enhance_todo.md` records decisions, but accepted architecture decisions must eventually live in the source-of-truth architecture document.
Evidence: `Docs/ARCHITECTURE.md` is the source of truth; this roadmap introduces accepted/proposed clarifications.
Files: `Docs/ARCHITECTURE.md`, architecture guard tests if rules change.
Steps: Add Filament integration contract after approval; add publication/status model after approval; add validation/authorization/transaction/event policy after approval; add locale fallback policy after approval; add Support/LegacyImport exception or relocation rule after approval; add Spec Kit governance note after approval.
Acceptance: `Docs/ARCHITECTURE.md` contains accepted architecture decisions; it does not contradict `enhance_todo.md`; architecture guard aligns with the updated document; proposed decisions are not presented as implemented facts.
Tests: Documentation grep, `php artisan test --filter=ArchitectureGuardTest`.
Dependencies: Approval of ADR-004, ADR-009, ADR-010, ADR-011, ADR-012.
Risk: Updating docs before code can create stricter rules than implementation satisfies.
Rollback: Revert documentation patch or mark decisions as proposed.

### ARCH-P2-001: Introduce Spec Kit Governance

Priority: P2.
Status: Proposed.
Type: Process, specs, documentation.
Rationale: Spec Kit can become the future spec -> plan -> tasks workflow, but it is not installed and should not be mixed with code changes.
Evidence: No `.specify/` or root `specs/`; stale `.kiro/specs` exists.
Files: `.specify/**`, `specs/**`, governance docs, only after approval.
Steps: Get explicit approval; create constitution; pilot one small non-code or test-focused enhancement; keep Kiro historical.
Acceptance: Spec Kit artifacts reflect current 11-section architecture and do not override `Docs/ARCHITECTURE.md`.
Tests: Process review plus targeted feature tests for pilot work.
Dependencies: ARCH-P0-007.
Risk: More stale artifacts if adopted too early.
Rollback: Remove unused Spec Kit artifacts before they become active.

### ARCH-P2-002: Bound Foundation Lead Capture Scope

Priority: P2.
Status: Proposed.
Type: Scope control, service/API behavior.
Rationale: Contact capture is optional foundation behavior, but full CRM is out of scope.
Evidence: `Docs/ARCHITECTURE.md:24-30`, `routes/web.php:46-49`.
Files: contact controller/request/service, policies, tests, optional admin resource.
Steps: Define included/excluded behavior; ensure service interface and FormRequest; test validation/throttle/persistence/auth.
Acceptance: Lead capture does not expand into CRM workflows.
Tests: Contact feature tests, policy tests.
Dependencies: None.
Risk: Stakeholders may expect CRM behavior.
Rollback: Keep storage-only contact capture and defer CRM module.

### ARCH-P2-003: Document Article, Research, And Event Card DTOs As Display Contracts

Priority: P2.
Status: Proposed.
Type: Scope documentation, DTO semantics.
Rationale: Card DTOs support homepage display sections but must not imply full News/Research/Event modules are in scope.
Evidence: `ArticleCardDTO`, `ResearchCardDTO`, `EventCardDTO` exist; full modules are out of scope in architecture.
Files: DTO docs/comments, homepage services, architecture docs if approved, tests.
Steps: Document card DTO purpose; ensure services treat cards as manual/display payloads; guard against repository-module assumptions.
Acceptance: Display-card DTOs are understood as homepage contracts only.
Tests: Homepage section payload tests.
Dependencies: ADR-010 equivalent scope decision.
Risk: Documentation-only task may be skipped and scope creep returns.
Rollback: Add stronger comments near DTOs or in architecture docs.

### ARCH-P2-004: Harden Menu And Navigation Tree Tests

Priority: P2.
Status: Proposed.
Type: Navigation workflow tests.
Rationale: Menu/navigation is foundation scope and impacts public shell, language switching, and cache.
Evidence: Architecture scope includes menu builder and public navigation shell; menu service uses transactions in audited code.
Files: menu service, navigation service, Filament menu page, tests.
Steps: Test tree ordering, nesting, disabled items, locale labels, active state, cache invalidation, invalid hierarchy.
Acceptance: Navigation tree behavior is deterministic for AR/EN public shell.
Tests: `NavigationShellTest`, menu service tests.
Dependencies: ARCH-P1-008.
Risk: Existing fixtures may not cover nested edge cases.
Rollback: Add tests incrementally around existing behavior first.

### ARCH-P2-005: Harden Settings Update Cache And Audit Tests

Priority: P2.
Status: Proposed.
Type: Settings workflow tests.
Rationale: Settings affect public shell, SEO, contact, footer, and cache behavior.
Evidence: Architecture scope includes settings; settings service transaction/cache/audit behavior was observed during audit.
Files: settings service, Filament settings page, tests.
Steps: Test grouped updates, locale-aware values, validation, cache invalidation, audit entries, rollback behavior.
Acceptance: Settings writes are service-owned, audited, and cache-safe.
Tests: `SettingsServiceIntegrationTest`, Filament settings tests.
Dependencies: ARCH-P1-008, ARCH-P1-009.
Risk: Tests may require stable seed data.
Rollback: Use focused factories/fixtures.

### ARCH-P2-006: Harden Media Upload DTO Tests

Priority: P2.
Status: Proposed.
Type: Media service tests.
Rationale: Media library is foundation scope and service methods should return DTOs, not models.
Evidence: `MediaServiceInterface` returns media DTOs; `MediaUploadResultDTO` exists; media service tests exist.
Files: media service, media validator, Filament media resource pages, tests.
Steps: Test upload validation, metadata update, storage failure behavior, DTO shape, audit logging, authorization.
Acceptance: Media workflows return DTOs and handle failure safely.
Tests: `MediaServiceTest`, Filament media tests.
Dependencies: ARCH-P1-007.
Risk: Filesystem fakes can differ from production disks.
Rollback: Keep tests driver-agnostic and use Laravel storage fakes.

## 9. Spec Kit Execution Plan

Current state:

- Spec Kit is not installed.
- No `.specify/` directory was found.
- No root `specs/` directory was found.
- Existing `.kiro/specs/**` files are historical/stale inputs, not active governance.
- Existing `.github/copilot-instructions.md` is stale input, not active governance.

Execution rule:

Do not initialize Spec Kit in the same task that modifies architecture or application code. Spec Kit adoption must be its own change because this repository already contains Kiro specs and other spec-driven artifacts that need reconciliation.

Recommended Spec Kit sequence:

1. Complete P0 architecture reconciliation.
2. Ask for explicit approval to add Spec Kit files.
3. Create a Spec Kit constitution from `Docs/ARCHITECTURE.md`, this file, and current project rules.
4. Mark Kiro/Copilot files as historical or stale if they remain in the repository.
5. Pilot one small task, preferably `ARCH-P1-004` public cache bypass matrix tests.
6. Require each future spec to include scope, architecture constraints, AR/EN behavior, draft/preview/cache behavior, audit/security behavior, acceptance criteria, tests, and rollback.

Spec Kit must not:

- Reintroduce 10-section homepage assumptions.
- Treat Laravel 11 as current stack.
- Ban normal Filament resource Eloquent mechanics.
- Expand into full News/Research/Events/Facilities/Admissions/CRM modules.
- Generate application code without an approved spec, plan, tasks, and test strategy.
- Override `Docs/ARCHITECTURE.md`.

## 10. Testing Strategy

Local commands for future implementation tasks:

```powershell
composer validate
php artisan about
php artisan route:list
php artisan test
vendor\bin\phpunit
vendor\bin\phpstan analyse --memory-limit=1G
vendor\bin\pint --test
npm ci
npm run build
```

Targeted test groups:

| Area | Required Coverage |
| --- | --- |
| Architecture | Controllers, contracts, middleware, DTOs, Support, Filament workflow boundaries, bindings, homepage keys. |
| Public runtime | `/`, `/ar`, `/en`, localized pages, 404 for draft/disabled/unpublished/scheduled early content. |
| Preview | Valid, invalid, expired, tampered, snapshot-bound, token invalidation, cache bypass. |
| Homepage publish | Save draft, publish, schedule, unpublish, validation failure, transaction rollback, cache, audit, token invalidation. |
| Page publish | Draft application, publishability validation, schedule, unpublish, rollback, cache, audit, token invalidation. |
| Admin/Filament | Workflow actions delegate to services, policies enforced, validation messages preserved. |
| Auth/RBAC | Login lockout, locked account denial, two-factor challenge, role and faculty scope. |
| Menu/navigation | Tree ordering, active state, locale labels, cache invalidation, invalid hierarchy. |
| Settings | Grouped updates, locale values, validation, audit, cache invalidation. |
| Media | Upload validation, DTO returns, storage failure, metadata update, audit, authorization. |
| Contact | Validation, throttle, persistence, minimal foundation scope. |
| Static/tooling | Pint, PHPStan/Larastan, Composer audit, NPM audit, tests. |

Architecture guard requirements:

- No controller Eloquent model imports.
- No controller direct Eloquent query or `DB` usage.
- No controller concrete `App\Services` injection.
- No contract Eloquent imports or return types.
- No middleware Eloquent queries or domain workflow logic.
- DTOs are `final readonly` and constructor-only unless explicitly allowlisted.
- No Support-layer DB writes except approved legacy exception.
- No direct Action calls from controllers or Filament.
- Homepage keys exactly match the approved 11-key list.
- All contracts are container-bound.
- Legacy `app/Http/Kernel.php` does not exist.

## 11. Risk Register

| Risk ID | Risk | Severity | Likelihood | Evidence | Mitigation | Owner |
| --- | --- | --- | --- | --- | --- | --- |
| R-001 | Locale fallback mismatch serves wrong-language content or SEO. | High | Medium | `.env.example:11-12`, `/` redirects to `/ar`. | `ARCH-P0-001`. | Backend lead |
| R-002 | Raw string statuses allow invalid workflow states. | High | Medium | DTO/service status strings. | `ARCH-P0-002`. | Backend lead |
| R-003 | Publish events are registered but not dispatched. | Medium | High | Event provider registrations, no dispatch found. | `ARCH-P0-004`. | Backend lead |
| R-004 | Support-layer Eloquent side effects undermine architecture. | Medium | Medium | `app/Support/LegacyImport/**`. | `ARCH-P0-005`. | Backend lead |
| R-005 | Stale 10-section Kiro spec misleads future work. | High | Medium | `.kiro/specs` 10-section text. | `ARCH-P0-007`. | Tech lead |
| R-006 | Stale Copilot instructions mislead future work. | Medium | Medium | Laravel 11 and Filament Eloquent ban. | `ARCH-P0-007`. | Tech lead |
| R-007 | Workflow allows Pint/PHPStan failures. | Medium | High | `continue-on-error`. | `ARCH-P1-005`. | DevOps/backend |
| R-008 | Filament duplicates business validation. | Medium | Medium | Service-layer rules and observed candidates. | `ARCH-P1-003`. | Backend/admin |
| R-009 | Preview cache leakage exposes draft content. | Critical | Low-Medium | Preview route inside public locale group. | `ARCH-P1-002`, `ARCH-P1-004`. | Backend/security |
| R-010 | Spec Kit adoption creates more stale artifacts. | Medium | Medium | Existing stale spec artifacts. | `ARCH-P2-001` after P0 only. | Tech lead |
| R-011 | Scope creep into full content modules delays foundation. | Medium | Medium | Display-card DTOs can be misread. | `ARCH-P2-003`. | Product/backend |
| R-012 | Authorization gaps for editor/faculty editor roles. | High | Medium | Role rules require scoped access. | `ARCH-P1-007`. | Backend/security |
| R-013 | `enhance_todo.md` becomes a competing architecture source. | Medium | Medium | Roadmap records decisions not yet merged into `Docs/ARCHITECTURE.md`. | `ARCH-P1-011`. | Tech lead |

## 12. Definition of Done

A task from this roadmap is done only when all applicable criteria are met:

- Code compiles and Laravel commands run.
- `php artisan about` succeeds.
- `php artisan route:list` succeeds.
- Interfaces resolve through the Laravel container.
- Controllers remain thin and do not import/query Eloquent models.
- Controllers and higher layers inject interfaces rather than concrete services.
- Business logic lives in services or approved service-owned collaborators.
- Public service interfaces do not type-hint or return Eloquent models.
- DTOs remain immutable data carriers unless an exception is approved.
- AR/EN behavior is implemented and tested.
- RTL/LTR behavior is preserved where UI output is affected.
- Draft and scheduled content do not leak publicly.
- Preview flows are token-protected and cache-bypassed.
- Publish workflows validate required content, invalidate relevant cache, invalidate preview tokens where needed, and create audit logs.
- Transactions protect multi-write workflows and side effects do not run on failed writes.
- Authorization uses policies/gates and role scope is tested.
- Tests cover success, failure, authorization, and rollback paths.
- Architecture guard tests cover any new boundary rule.
- No old homepage block names or stale 10-section assumptions are introduced.
- No full-module scope creep is introduced beyond homepage/admin foundation.
- Quality-gate commands are run or documented as not run with reason.
- Rollback impact is understood and documented.
- Accepted decisions that change architecture rules are reflected in `Docs/ARCHITECTURE.md`.

## 13. Recommended Next Opencode Prompts

### Prompt: Neutralize Stale Non-Spec Guidance

```text
Review repository guidance files that may mislead future agents, especially `.kiro/specs/**` and `.github/copilot-instructions.md`. Treat `Docs/ARCHITECTURE.md` and `enhance_todo.md` as sources of truth. Mark stale Kiro/Copilot guidance as historical or patch it so it no longer claims Laravel 11, 10 homepage sections, or an impossible Filament no-Eloquent rule. Do not modify application code. Run documentation grep checks and report exactly which files changed.
```

### Prompt: Legacy Support Exception Decision

```text
Inspect `app/Support/LegacyImport/**` and all call sites. Decide whether these classes should move to service-layer collaborators or remain as a narrow documented legacy exception. Implement the smallest safe change. Do not modify unrelated Support helpers. Add or update architecture guard allowlists only after the decision is clear. Run targeted tests and report risks.
```

### Prompt: Architecture Guard Expansion

```text
Expand the existing PHPUnit architecture guard for the SPU Laravel foundation. Use Docs/ARCHITECTURE.md and enhance_todo.md as sources of truth. Add checks for controllers importing/querying Eloquent, concrete service injection, contracts importing/returning Eloquent, middleware Eloquent usage, DTO methods beyond constructors unless allowlisted, Support-layer DB writes unless explicitly allowlisted, direct Action usage by controllers/Filament, all contracts bound in the container, and the fixed 11 homepage section keys. Do not install Pest. Run targeted tests and report results.
```

### Prompt: Locale Policy Patch

```text
Patch the SPU Laravel foundation so locale default/fallback behavior is consistent with the Arabic-first product requirement after confirming exact fallback behavior. Inspect locale middleware, config/app.php, .env examples, SEO/navigation services, cache keys, and public runtime tests before editing. Add tests for / redirect, /ar, /en, missing translation fallback, and locale-aware cache keys. Keep controllers thin and do not modify unrelated files.
```

### Prompt: Publication Status Enum

```text
Introduce a PHP 8.2 backed enum for publication workflow statuses in the SPU Laravel foundation. Replace repeated status string literals in publishing, draft, preview, and validation code where safe. Preserve external DTO/template compatibility unless all consumers are migrated. Add tests for status transitions, scheduled visibility, draft public hiding, and preview editable status behavior.
```

### Prompt: Publish Side-Effect Reconciliation

```text
Reconcile homepage/page publish side effects. Inspect HomepagePublishingService, PageService, EventServiceProvider, events, listeners, cache service, audit service, preview token invalidation, and publish tests. Choose one coherent policy for transactions, cache invalidation, preview token invalidation, audit logging, and domain events. Avoid duplicate invalidation. Ensure side effects happen only after successful transactions. Add success/failure tests.
```

### Prompt: Architecture Source Patch

```text
Patch `Docs/ARCHITECTURE.md` with only accepted decisions from `enhance_todo.md`. Add Filament Integration Contract, publication/status model, cross-cutting validation/authorization/transaction/event policy, locale fallback policy, Support helper exception policy, and Spec Kit governance note only where decisions are approved. Do not modify application code. Run documentation grep and architecture guard tests.
```

### Prompt: Spec Kit Pilot After P0

```text
Prepare a Spec Kit adoption pilot only after P0 architecture reconciliation is complete. Do not initialize Spec Kit in the same task that modifies application code. Treat existing Kiro and Copilot files as historical/stale inputs, not active governance. Draft a Spec Kit constitution from Docs/ARCHITECTURE.md and enhance_todo.md, then pilot one small test-focused enhancement such as public cache bypass matrix tests.
```

## 14. Appendix

### Command And Inspection Log

Successful commands observed during the audit session:

- `pwd` returned `C:\Un Web\SPU-BACKEND\SPU_Website`.
- `git branch --show-current` returned `try-some-enhances`.
- `git rev-parse HEAD` returned `37acc065326928c7d3bcc15b84d8257dec829e1d`.
- `php -v` returned PHP 8.2.12.
- `composer show laravel/framework --no-interaction` returned Laravel framework v12.62.0.
- `composer show filament/filament --no-interaction` returned Filament v3.3.54.
- `composer show larastan/larastan --no-interaction` returned Larastan v3.9.6.
- `php artisan --version` returned Laravel Framework 12.62.0.
- `php artisan about` succeeded.
- `php artisan route:list` succeeded.
- `composer show --no-interaction --direct` succeeded.
- `vendor\bin\phpunit --version` returned PHPUnit 11.5.55.
- `vendor\bin\phpstan --version` returned PHPStan 2.1.54.
- `npm --version` returned 11.5.1.

Failed commands observed during the audit session:

- `composer show pestphp/pest --no-interaction` failed because `pestphp/pest` is not installed.
- `php artisan route:list --compact` failed because the `--compact` option does not exist.
- `bash -lc "find . -maxdepth 3 -type f | sort"` failed because WSL has no installed distributions.
- `rg -L "final readonly class" app/DTOs` failed because `rg` was not available in the PowerShell session.

Search findings:

- No root `ARCHITECTURE.md` was found; `Docs/ARCHITECTURE.md` is the applicable architecture source.
- No `.specify/` directory was found.
- No root `specs/` directory was found.
- No `tests/Architecture/**/*.php` files were found.
- No `app/Enums/**/*.php` files were found.
- No `app/Actions/**/*.php` files were found.
- Grep found no publish event dispatch calls for `HomepagePublished`, `PagePublished`, or `PageUnpublished`.

### Incomplete Evidence And Assumptions

This audit did not run migrations or seeders.

This audit did not execute the full test suite after editing this planning file. Future code tasks must run targeted and relevant full verification.

Route list execution was confirmed earlier, but full route output is not reproduced here beyond direct route file evidence.

Filament workflow observations were based on inspected source files, not browser runtime testing.

Database-dependent behavior such as account locks, audit retention, queue processing, cache driver behavior, and scheduled publishing needs integration verification in a configured environment.

Arabic default/fallback is proposed, not implemented. Confirm exact fallback behavior before changing config or public rendering behavior.

Spec Kit adoption is planned only. No Spec Kit files were created by this task.

### Change Control

Before implementing any task:

1. Read referenced files and confirm evidence is still current.
2. Check `git status --short` and preserve unrelated user changes.
3. Implement the smallest safe change.
4. Add or update targeted tests.
5. Run targeted verification first, then broader verification when feasible.
6. Update this backlog only if task status, evidence, or decisions changed.

Task status values:

- Proposed: ready for review and prioritization.
- Approved: accepted for implementation.
- In progress: actively being implemented.
- Blocked: waiting on decision, dependency, or environment.
- Done: implemented, tested, and verified.
- Superseded: replaced by a newer task or decision.
