# SPU Spec-Driven Execution TODO

Project: Syrian Private University Website Foundation

Repository: `SPU_Website`

Created: 2026-06-15

Document status: Active execution TODO

Primary method: Spec-driven development adapted from `github/spec-kit`

Primary constraint: Current repository scope is homepage plus admin/CMS foundation only

---

## 1. Purpose

This document is a new execution-grade TODO for finishing the current SPU website foundation with a controlled, spec-driven workflow.

It does not replace `TODO.md`. It converts the current verified project state, local architecture rules, and the useful parts of Spec Kit into a practical queue of tasks that can be executed safely by OpenCode or developers.

The goal is high-quality execution, not scope expansion.

---

## 2. Governing Sources

| Priority | Source | Role |
| ---: | --- | --- |
| 1 | `AGENTS.md` | Non-negotiable local rules and current sprint scope. |
| 2 | `Docs/ARCHITECTURE.md` | Layer boundaries, DTO rules, homepage contract. |
| 3 | `Docs/BACKEND_RULES.md` | Laravel, service, controller, model, cache, CMS rules. |
| 4 | `Docs/STYLEGUIDE.md` | PHP and project style standards. |
| 5 | `Docs/WORKFLOW.md` | Execution sequence and validation rules. |
| 6 | `TODO.md` | Existing audit remediation source of truth. |
| 7 | `github/spec-kit` | Process model only: constitution, spec, plan, tasks, analyze, implement. |

If this document conflicts with `AGENTS.md`, `AGENTS.md` wins.

If this document conflicts with full-site roadmap ideas, current foundation scope wins.

---

## 3. Current Scope Boundary

In scope:

| Area | Included Work |
| --- | --- |
| Public homepage | 11-section CMS homepage, AR/EN, RTL/LTR, draft/preview/publish. |
| Public shell | Header, footer, navigation, locale-aware public pages. |
| Landing-page foundation | Generic bilingual page shell, SEO metadata, breadcrumbs, previews. |
| Admin panel foundation | Filament pages/resources for homepage, pages, menu, media, settings, users, audit. |
| Publishing workflow | Validation, scheduling, cache invalidation, preview token invalidation, audit logs. |
| Performance foundation | Vite build, homepage JS splitting, lazy images, cache behavior, indexes. |
| Security foundation | Sanitization, upload hardening, preview token hashing, RBAC, security headers. |
| Launch readiness | Tests, route boot, build, launch validation, rollback readiness. |

Out of scope until explicitly approved:

| Area | Reason |
| --- | --- |
| Full News module | Explicitly out of scope in `AGENTS.md` and architecture docs. |
| Full Facilities module | Explicitly out of scope for current foundation. |
| Full Research repository | Explicitly out of scope for current foundation. |
| Full Events module | Explicitly out of scope for current foundation. |
| Full Admissions module | Explicitly out of scope for current foundation. |
| Full CRM/contact system | Contact foundation exists, but full CRM is out of scope. |
| Migration dashboard buildout | Conditional only after a business decision confirms migration within 6 months. |
| Repository or DDD layers | Forbidden by local rules for this project phase. |

---

## 4. Verified Baseline

Latest verified state from this session:

| Verification | Result |
| --- | --- |
| `php artisan test` | Passing, 3395 tests and 15159 assertions on full run. |
| `npm run build` | Passing with no unexpected warnings. |
| `php artisan route:list` | Bootstraps routes successfully. |
| `php artisan test --filter=PageService` | Passing, publish validation covered. |
| `php artisan test tests/Feature/PublicRuntimeTest.php tests/Feature/HomepageBlade` | Passing, public runtime and homepage Blade covered. |
| `app/Actions` | Not present. Action-class refactor not started. |
| `.specify` and `specs` | Not present. Spec Kit is not installed locally. |
| Worktree noise | Untracked `setup-fresh.bat` and `update-packages.bat` existed and are unrelated. |

Frontend build measurement captured 2026-06-15:

| Artifact | Built Output | Gzip |
| --- | ---: | ---: |
| `public/build/js/app.CBkSiQxH.js` | 143.03 kB | 45.24 kB |
| `public/build/js/homepage.hSud3-IL.js` | 15.56 kB | 6.07 kB |
| `public/build/assets/app.C0Q5iHGS.css` | 198.71 kB | 34.86 kB |

---

## 5. Spec-Driven Workflow For This Repository

This project does not need blind Spec Kit installation before productive work. The process should be used as a discipline.

| Spec Kit Stage | Local Equivalent | Required Output |
| --- | --- | --- |
| Constitution | `AGENTS.md`, `Docs/ARCHITECTURE.md`, `Docs/BACKEND_RULES.md` | No task may violate these files. |
| Specify | Task card in this document | What, why, acceptance criteria, out-of-scope boundary. |
| Clarify | One short question only if needed | Decision recorded in this document or relevant docs. |
| Plan | Files, sequence, risks, validation commands | Minimal implementation path. |
| Tasks | Checkbox steps under each task card | Executable by one developer/session. |
| Analyze | Scope and architecture review before coding | No controller/model business logic, no raw model returns. |
| Implement | Code/docs/tests | Smallest correct change. |
| Validate | Targeted commands plus full gates when needed | Passing tests/build/routes. |

---

## 6. Definition Of Ready

A task is ready only when all conditions are true.

| Check | Requirement |
| --- | --- |
| Scope | Fits homepage/admin foundation scope. |
| Acceptance | Has objective pass/fail criteria. |
| Files | Likely touched files are known. |
| Architecture | Service-layer and DTO rules are understood. |
| Risk | Known behavior risks are listed. |
| Verification | Commands are specified before editing. |
| Dependencies | Required prior tasks are complete. |

---

## 7. Definition Of Done

A task is done only when all required conditions are true.

| Category | Requirement |
| --- | --- |
| Code | Minimal correct change with no unrelated scope. |
| Architecture | Business logic remains in services or approved service-layer collaborators. |
| Controllers | No Eloquent imports, no queries, no business logic. |
| Models | Relationships, casts, scopes, lightweight helpers only. |
| DTOs | Public service outputs are DTOs, collections of DTOs, scalars, bools, or approved composite arrays. |
| Tests | Targeted tests pass. Full suite passes for release-critical changes. |
| Build | `npm run build` passes after frontend/CSS/Blade asset changes. |
| Routes | `php artisan route:list` passes after route/provider/middleware changes. |
| Docs | Requirements, TODOs, and launch docs are updated when behavior changes. |
| Scope | No News/full migration/full-site module is introduced without approval. |

---

## 8. Execution Queue

Task states:

| State | Meaning |
| --- | --- |
| Proposed | Candidate task, not ready to implement. |
| Ready | Clear enough to implement. |
| In Progress | Currently being worked. |
| Blocked | Cannot proceed without decision or dependency. |
| Done | Implemented and verified. |
| Deferred | Valid idea, not for current phase. |

---

## 9. Workstream A: Source Of Truth And Governance

### A01: Keep Scope Documents In Sync

Status: Ready

Priority: High

Goal: Ensure `AGENTS.md`, architecture docs, backend rules, and TODOs remain aligned after any foundation change.

Why this matters: The repository already has multiple planning documents. Drift creates false implementation permission and causes agents to build out-of-scope modules.

Likely files:

| File | Expected Use |
| --- | --- |
| `AGENTS.md` | Current non-negotiable instructions. |
| `Docs/ARCHITECTURE.md` | Layer and scope changes. |
| `Docs/BACKEND_RULES.md` | Backend rule updates. |
| `TODO.md` | Audit remediation status. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Execution queue. |

Implementation steps:

- [ ] Before starting any task, read the relevant section in `AGENTS.md` and this document.
- [ ] If implementation changes behavior, update the appropriate source-of-truth doc in the same change.
- [ ] If a requested task expands scope, record it as Deferred unless the user explicitly approves scope expansion.
- [ ] If a task is conditional, record the decision gate before writing code.

Acceptance criteria:

- [ ] No authoritative document contradicts the implemented homepage/admin foundation behavior.
- [ ] No TODO marks a task complete without evidence.
- [ ] Out-of-scope modules remain explicitly deferred.

Verification:

```bash
php artisan test tests/Feature/ArchitectureGuardTest.php
```

---

### A02: Decide Whether To Install Spec Kit Locally

Status: Proposed

Priority: Medium

Goal: Decide if this repository should add `.specify` artifacts or keep using local docs as the constitution and spec workflow.

Why this matters: Installing tooling into an existing Laravel repository creates files and process overhead. It should be a deliberate decision, not an automatic side effect.

Options:

| Option | Recommendation | Notes |
| --- | --- | --- |
| Keep local docs only | Recommended now | Existing docs already act as constitution and constraints. |
| Add `.specify` later | Acceptable | Useful if the team wants formal feature specs per future module. |
| Initialize Spec Kit immediately | Not recommended | Adds process files before the current foundation queue needs them. |

Implementation steps:

- [ ] Ask product/tech lead whether formal Spec Kit artifacts are desired in this repository.
- [ ] If yes, run initialization in a controlled branch and review generated files before keeping them.
- [ ] If no, keep this document as the local spec-driven execution queue.

Acceptance criteria:

- [ ] Decision is recorded in this document or a future `DECISIONS.md`.
- [ ] No generated workflow files are added without review.

Verification:

```bash
git status --short
```

---

## 10. Workstream B: Release Gate And Verification

### B01: Create A Single Launch Verification Command Matrix

Status: Done

Priority: High

Goal: Consolidate the commands that prove the foundation is launch-ready.

Why this matters: The project has many targeted suites. A clear matrix reduces missed checks before release.

Likely files:

| File | Expected Use |
| --- | --- |
| `Docs/launch-readiness-checklist.md` | Launch checklist updates. |
| `TODO.md` | Release gate status. |
| `SPU_SPEC_DRIVEN_EXECUTION_TODO.md` | Command matrix. |

Implementation steps:

- [x] Confirm the current full test duration and any slow test groups.
- [x] Define quick local validation commands for normal tasks.
- [x] Define release-critical validation commands for merges.
- [x] Define frontend-specific validation commands.
- [x] Define route/provider/middleware validation commands.

Acceptance criteria:

- [x] Every task type has a recommended validation command set.
- [x] Release gate commands are explicit and copyable.
- [x] No validation command requires unavailable services unless marked conditional.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Launch checklist | `Docs/launch-readiness-checklist.md` now contains quick local gates, release-candidate gate, manual browser gate, and evidence rules. |
| Runtime impact | Documentation-only change; no application runtime code changed. |
| Changelog | `SPU_SPEC_DRIVEN_CHANGELOG.md` records the completed B01 implementation. |

Recommended command matrix:

| Change Type | Required Commands |
| --- | --- |
| Docs only | `git diff --check` |
| Public Blade only | `php artisan test tests/Feature/HomepageBlade` and `php artisan test tests/Feature/PublicRuntimeTest.php` |
| Vite/CSS/JS | `npm run build` and public Blade/runtime tests |
| Service publish workflow | `php artisan test --filter=PageService` and relevant feature tests |
| Homepage CMS workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` |
| Route/provider/middleware | `php artisan route:list` and `php artisan test tests/Feature/MiddlewarePipelineTest.php` |
| Security-sensitive | Relevant feature tests plus full `php artisan test` |
| Release candidate | `php artisan test`, `npm run build`, `php artisan route:list`, `php artisan launch:validate --environment=production` where environment allows |

Verification:

```bash
git diff --check
```

---

### B02: Keep Release Gate Status Current

Status: Done

Priority: High

Goal: Update release checklist status only when evidence exists.

Why this matters: Release checklists become unreliable when checkboxes are not tied to actual verification.

Implementation steps:

- [x] Record date and command output summary for full test suite.
- [x] Record date and command output summary for frontend build.
- [x] Record date and command output summary for route boot.
- [x] Keep staging-only checks unchecked until run in staging.
- [x] Keep manual QA checks unchecked until performed in browser.

Acceptance criteria:

- [x] Automated checks are marked complete only with command evidence.
- [x] Manual checks are marked complete only after actual browser review.
- [x] Staging checks are not silently inferred from local results.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Full test suite | `php artisan test` passed: 3395 tests, 15259 assertions, 177.89s. |
| Frontend build | `npm run build` passed: Vite build completed in 1.81s with no unexpected warnings. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Release gate status | `TODO.md` now marks only the verified automated release gates complete. |
| Launch evidence | `Docs/launch-readiness-checklist.md` records the evidence and explicitly leaves manual/staging/review gates pending. |
| Runtime impact | Documentation-only change; no application runtime code changed. |

Verification:

```bash
git diff --check
```

---

## 11. Workstream C: Frontend Performance And Core Web Vitals

### C01: Complete Manual Homepage Performance QA

Status: Ready

Priority: High

Goal: Validate that the optimized homepage performs correctly on desktop and mobile beyond automated tests.

Why this matters: Automated Blade tests prove markup, but Core Web Vitals require browser verification.

Preconditions:

| Check | Requirement |
| --- | --- |
| Build | `npm run build` passes with no warnings. |
| Public homepage data | AR and EN homepage render from real seeded/runtime data. |
| Browser | Chrome Lighthouse or equivalent is available. |

Manual QA checklist:

| Page | Viewport | Checks |
| --- | --- | --- |
| `/ar` | Mobile | RTL layout, mobile menu, hero, sections, sliders, footer. |
| `/ar` | Desktop | Hero LCP, nav dropdowns, sliders, reveal animations, footer. |
| `/en` | Mobile | LTR layout, mobile menu, hero, sections, sliders, footer. |
| `/en` | Desktop | Hero LCP, nav dropdowns, sliders, reveal animations, footer. |
| Public landing page | Mobile and desktop | Generic shell works without homepage chunk errors. |

Performance targets:

| Metric | Target |
| --- | ---: |
| LCP | Less than 2.5 seconds on a realistic staging profile. |
| CLS | Less than 0.1. |
| JavaScript errors | 0 browser console errors. |
| Missing assets | 0 missing CSS, JS, images, fonts. |

Implementation steps:

- [ ] Run production build.
- [ ] Serve app in a production-like local or staging environment.
- [ ] Test `/ar` and `/en` homepage in mobile and desktop viewports.
- [ ] Test one non-homepage public page to confirm homepage chunk is not required.
- [ ] Capture Lighthouse results or browser performance summary.
- [ ] Record results in `Docs/launch-readiness-checklist.md` or this document.

Acceptance criteria:

- [ ] No visible regression in AR/EN homepage.
- [ ] No browser console errors.
- [ ] No missing built assets.
- [ ] LCP and CLS meet targets or deviations are documented with cause.

Verification:

```bash
npm run build
php artisan test tests/Feature/PublicRuntimeTest.php tests/Feature/HomepageBlade
```

---

### C02: Review Remaining FontAwesome Cost

Status: Proposed

Priority: Medium

Goal: Decide whether remaining FontAwesome runtime usage should be replaced with static SVGs or narrower imports.

Why this matters: `resources/js/app.js` still uses FontAwesome DOM watching. It may be acceptable, but it should be measured before changing.

Current state:

| Item | State |
| --- | --- |
| Global CSS import from `@fortawesome/fontawesome-free` | Not present. |
| Tree-shaken icon package imports | Present. |
| `dom.watch()` | Present in `resources/js/app.js`. |
| Static local SVG image usage | Present across public views. |

Implementation steps:

- [ ] Search public views for actual FontAwesome class usage.
- [ ] Identify icons that can be replaced with existing `/images/*.svg` assets.
- [ ] Estimate bundle savings before changing code.
- [ ] Replace only if measurable benefit exceeds risk.
- [ ] Run visual checks for nav/footer/icon-heavy areas.

Acceptance criteria:

- [ ] Decision is evidence-based.
- [ ] If code changes, no icon disappears in public header/footer/admin auth.
- [ ] Build remains warning-free.

Verification:

```bash
npm run build
php artisan test tests/Feature/PublicRuntimeTest.php
```

---

## 12. Workstream D: Page Publish Correctness

### D01: Preserve Current Page Publish Validation Behavior

Status: Ready

Priority: High

Goal: Ensure invalid pages never become public.

Current implementation: `PageService` validates publishability before updating published state. Tests cover valid publish and invalid cases for missing slug, missing template, missing translations, and missing locale title.

Implementation rules:

| Rule | Requirement |
| --- | --- |
| Business logic | Service layer only. |
| Failed publish | Must return false or throw only where existing behavior expects it. |
| Partial state | Must not set `status = published` when validation fails. |
| Audit | Must not write successful publish audit on failed publish. |
| Cache | Must not expose invalid page through public cache. |

Implementation steps:

- [ ] Keep existing tests green before touching publish logic.
- [ ] Add characterization tests before extracting publish validation to a new class.
- [ ] Preserve `PageServiceInterface::publish(int $pageId, int $userId): bool` unless explicitly approved.
- [ ] Avoid introducing duplicate validators unless the extraction is part of a planned architecture refactor.

Acceptance criteria:

- [ ] Invalid pages cannot become `published`.
- [ ] Valid pages publish successfully.
- [ ] Failed publish has no successful audit log.
- [ ] Failed publish does not leak public content.

Verification:

```bash
php artisan test --filter=PageService
php artisan test tests/Feature/Integration/PageServiceIntegrationTest.php
```

---

### D02: Decide Whether To Extract PagePublishValidator

Status: Proposed

Priority: Medium

Goal: Decide if a dedicated page publish validator is worth extracting now.

Why this matters: The pasted roadmap proposes a new validator service, but the current code already has behavior and tests. Extraction should improve maintainability without churn.

Decision criteria:

| Criterion | Extract If True |
| --- | --- |
| Reuse | Validation is needed in multiple services or admin actions. |
| Complexity | Publish validation grows beyond simple field checks. |
| Testability | Current private helper blocks needed direct test coverage. |
| Reporting | Admin UI needs structured errors/warnings instead of bool. |

Implementation steps if approved:

- [ ] Add characterization tests for current behavior first.
- [ ] Create the smallest validator class compatible with existing DTO conventions.
- [ ] Bind interface only if higher layers or multiple services need it.
- [ ] Keep `PageService` public contract unchanged.
- [ ] Run full PageService integration tests.

Acceptance criteria:

- [ ] No behavior regression.
- [ ] No duplicate validation paths with conflicting rules.
- [ ] Validator returns project-compatible result type.

Verification:

```bash
php artisan test --filter=PageService
php artisan test tests/Feature/ArchitectureGuardTest.php
```

---

## 13. Workstream E: Architecture Refactor Readiness

### E01: Measure Service Size And Complexity Before Action Extraction

Status: Done

Priority: High

Goal: Create an evidence-based refactor plan before adding Action classes.

Why this matters: Action classes are not currently present. Adding them without measuring current hotspots risks over-engineering and breaking stable behavior.

Likely services:

| Service | Current Concern |
| --- | --- |
| `HomepagePublishingService` | Publish workflow, draft scheduling, serialization, cache, audit. |
| `PageService` | Metadata, translation, SEO, drafts, publish, scheduling, authorization. |
| `MediaService` | Upload validation, storage, image handling, metadata, authorization. |
| `PreviewService` | Preview token and payload concerns. |

Implementation steps:

- [x] Count service LOC and public/private methods.
- [x] Identify methods with more than one responsibility.
- [x] Identify private helpers that are pure mapping/retrieval and safe to extract.
- [x] Identify behavior that lacks characterization tests.
- [x] Produce a refactor plan before moving code.

Acceptance criteria:

- [x] Refactor candidates are ranked by risk and benefit.
- [x] No code is moved before tests exist for the behavior.
- [x] Candidate actions/support classes have clear single responsibility.

Measurement captured 2026-06-15:

| Service | LOC | Public Methods | Private Methods | Existing Collaborators | Primary Concern Mix |
| --- | ---: | ---: | ---: | --- | --- |
| `HomepagePublishingService` | 711 | 9 | 24 | `HomepageSectionServiceInterface`, `CacheServiceInterface`, `AuditServiceInterface`, `HtmlSanitizer`, `PreviewTokenStore`, `HomepagePayloadMapper` | Draft lifecycle, publish transaction, scheduling, section normalization, payload sanitization, cache and preview invalidation, audit logging. |
| `PageService` | 695 | 18 | 15 | `PagePublicReadService`, `PageDraftService`, `PageUrlResolver`, `HtmlSanitizer`, `PreviewTokenStore` | Page shell CRUD, metadata, translations, SEO, draft lifecycle, publish transaction, scheduling, authorization, cache and preview invalidation. |
| `MediaService` | 452 | 5 | 10 | `AuditServiceInterface`, Laravel filesystem, `MediaUrlResolver` | Upload validation/storage, metadata filtering, listing/query filters, faculty scoping, authorization, audit logging. |
| `PreviewService` | 381 | 4 | 12 | `PreviewTokenStore`, `PageServiceInterface`, `HomepageSectionServiceInterface`, `NavigationServiceInterface` | Token orchestration, authorization, page/homepage preview payload assembly, homepage draft section mapping, navigation shell assembly. |

Existing coverage found:

| Area | Current Evidence |
| --- | --- |
| Homepage publish/draft/preview | `tests/Feature/HomepageCmsWorkflowTest.php` covers fixed keys, draft save, preview token hashing, public leakage prevention, publish validation, cache invalidation, sanitization, unpublish, and schedule intent. |
| Page publish/validation | `tests/Feature/Integration/PageServiceIntegrationTest.php` covers shell creation, bilingual translation isolation, publish/unpublish, publish validation, and parent-cycle rejection. |
| Page sanitization | `tests/Unit/PageServiceSanitizationTest.php` covers body HTML, legacy HTML block, null body payload, and JavaScript URL sanitization. |
| Media upload/security | `tests/Unit/MediaServiceTest.php` covers allowed uploads, SVG rejection, oversized uploads, missing actor, metadata filtering, soft delete, listing, pagination, and basic faculty upload scoping. |
| Preview runtime | `tests/Feature/PublicRuntimeTest.php` covers page preview leakage prevention, preview locale switching, snapshot stability, and homepage preview draft hydration. |
| Optimistic locking | `tests/Feature/OptimisticLockingPropertyTest.php` covers stale-version rejection for homepage and page drafts. |
| Faculty scoping | `tests/Feature/SecurityScopingRegressionTest.php` covers page/media policy and listing scope regressions. |
| Homepage payload mapping | `tests/Unit/HomepagePayloadMapperTest.php` covers mapper round-trip equivalence. |

Multiple-responsibility hotspots:

| Service | Hotspot | Risk |
| --- | --- | --- |
| `HomepagePublishingService` | `publish()` combines draft loading, validation, section persistence, translation persistence, draft state transition, superseding, cache invalidation, token invalidation, and audit logging. | High regression risk because it controls public homepage publication. |
| `HomepagePublishingService` | `saveDraft()` combines authorization, optimistic locking, normalization, serialization, draft creation, superseding, preview invalidation, audit logging, and DTO mapping. | High because conflict behavior and draft leakage rules are security-adjacent. |
| `HomepagePublishingService` | `sectionsFromDraft()`, `normalizeSections()`, `sectionFromArray()`, `translationFromArray()` combine mapping, fallback recovery, and approved-key ordering. | Medium because pure mapping can be characterized before extraction. |
| `PageService` | `publishResolvedDraft()` combines draft mapping, publishability checks, transactional draft application, page status updates, cache invalidation, preview invalidation, and audit logging. | High because page publication must not expose incomplete or draft content. |
| `PageService` | `saveDraft()` combines authorization, optimistic locking, versioning, draft persistence, audit logging, preview invalidation, and DTO mapping. | Medium-high because optimistic locking has property coverage but preview invalidation behavior needs direct characterization. |
| `PageService` | `updateTranslation()` and `updateSeo()` combine authorization, sanitization/URL filtering, persistence, cache invalidation, and audit logging. | Medium because sanitization is covered, but extraction would need callback behavior preserved for draft publishing. |
| `MediaService` | `upload()` combines validation, authorization, storage, dimension extraction, metadata persistence, faculty scope resolution, audit logging, and DTO mapping. | High because upload validation is security-sensitive. |
| `MediaService` | `buildListQuery()` combines filters, faculty scoping, sorting, and query construction. | Low-medium because behavior is already covered and can be extracted after query tests are expanded. |
| `PreviewService` | `buildHomepagePreview()` combines draft discovery, snapshot interpretation, fallback published payload loading, fixed-key filtering, mapping, and locale direction. | Medium because runtime preview coverage exists, but direct mapping edge cases need characterization before extraction. |
| `PreviewService` | `sectionFromDraft()` combines fallback merge, locale selection, DTO construction, and translation fallback. | Medium-low because it is mapper-like but currently private and indirectly tested. |

Safe extraction candidates, ranked:

| Rank | Candidate | Type | Benefit | Risk | Required Before Moving Code |
| ---: | --- | --- | --- | --- | --- |
| 1 | Homepage draft section normalization/mapping from `HomepagePublishingService` and `PreviewService` into a support mapper owned by the service layer. | Support class, not controller-injected Action. | Reduces two large services and consolidates fallback/approved-key ordering. | Medium because preview and publish use subtly different fallback rules. | Add direct characterization tests for draft section order, unknown-key exclusion, locale fallback, missing `sectionAction` recovery, and empty-install normalization. |
| 2 | Page publishability checks from `PageService` into an internal validator/result object if structured errors are needed. | Service-layer validator. | Makes publish rejection reasons explicit and testable. | Medium because current public contract returns `bool`; do not change it without UI need. | Characterize current `bool` outcomes for live page and draft-payload publish paths, including missing AR title and missing EN title. |
| 3 | Media file validation from `MediaService` into a private service-layer collaborator. | Service-layer validator. | Isolates security-sensitive MIME/extension/size/dimension checks. | Medium-high because upload hardening must not weaken. | Expand tests for extension mismatch, image dimension limit, allowed Office types, and faculty editor with missing scope. |
| 4 | Preview homepage payload assembly from `PreviewService` into an internal assembler. | Service-layer assembler. | Narrows `PreviewService` back to token orchestration and authorization. | Medium because preview must remain snapshot-bound and not leak draft changes. | Add direct tests for token invalidation on page/homepage draft save and publish, unsupported target authorization, and snapshot fallback behavior. |
| 5 | Cache and preview-token invalidation helpers into explicit internal collaborators only if repeated changes continue. | Service-layer collaborator. | Reduces repeated invalidation branches and audit metadata duplication. | Low-medium, but over-extraction risk is real. | Keep deferred unless another task changes invalidation behavior. |

Do not extract yet:

| Candidate | Reason |
| --- | --- |
| Full Action class split of `PageService` or `HomepagePublishingService` | Too broad; it would create many collaborators before architecture docs approve Actions. |
| Full `MediaService::upload()` rewrite | Security-sensitive; first change should be characterization tests only. |
| Controller-injected Actions | Violates current controller/service-interface boundary unless architecture docs are explicitly updated. |
| Repository layer | Not part of this project's approved architecture. |

Refactor sequence recommendation:

| Step | Task | Result |
| ---: | --- | --- |
| 1 | Complete E02 before any Action-style class is added. | Architecture docs define whether Actions are allowed and who may inject them. |
| 2 | Add characterization tests for rank 1 homepage mapping/fallback behavior. | Current behavior is locked before moving code. |
| 3 | Extract only the rank 1 mapper/assembler helper and keep public service interfaces unchanged. | Smallest useful architecture change. |
| 4 | Run homepage CMS, public runtime preview, architecture guard, and full suite. | Proves no draft leakage or publish regression. |
| 5 | Reassess whether rank 2 or rank 3 still justifies extraction. | Avoids unnecessary abstraction. |

E01 completion evidence:

| Evidence | Result |
| --- | --- |
| Measurement source | Read `HomepagePublishingService`, `PageService`, `MediaService`, and `PreviewService` and searched method declarations in `app/Services`. |
| Scope | Documentation-only readiness work; no runtime code moved. |
| Architecture decision | Action extraction remains blocked behind E02 documentation and characterization tests. |
| Homepage verification | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 16 tests, 95 assertions. |
| Page verification | `php artisan test --filter=PageService` passed: 17 tests, 59 assertions. |
| Media verification | `php artisan test --filter=Media` passed: 32 tests, 73 assertions. |

Verification:

```bash
php artisan test tests/Feature/HomepageCmsWorkflowTest.php
php artisan test --filter=PageService
php artisan test --filter=Media
```

---

### E02: Add Architecture Documentation For Action Classes Before Implementation

Status: Done

Priority: Medium

Goal: If Action classes are adopted, document the pattern before creating classes.

Why this matters: `Docs/ARCHITECTURE.md` currently defines MVC plus Service Layer. Adding Actions changes internal service-layer structure and must be governed.

Required decisions:

| Decision | Required Answer |
| --- | --- |
| Are Actions part of services or a separate layer? | Recommended: internal service-layer collaborators. |
| Can controllers inject Actions? | Recommended: no. Controllers continue using service interfaces only. |
| Can Actions return Eloquent models? | No. DTOs, bools, scalars, or approved arrays only. |
| Can Actions call Eloquent? | Yes, only as service-layer collaborators if documented. |
| Are all complex helpers Actions? | No. Use support classes for pure mapping/normalization. |

Implementation steps:

- [x] Add an `Action Classes` section to `Docs/ARCHITECTURE.md` only if extraction is approved.
- [x] Define where Actions live and who may inject them.
- [x] Define return type and dependency rules.
- [x] Define testing requirements.
- [x] Update this TODO with approved extraction sequence.

Acceptance criteria:

- [x] Architecture docs govern the pattern before code uses it.
- [x] Controllers remain interface-only service consumers.
- [x] Action pattern does not create a second business-logic layer outside service ownership.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Architecture docs | `Docs/ARCHITECTURE.md` now defines Action-style classes as internal service-layer collaborators only. |
| Backend rules | `Docs/BACKEND_RULES.md` now forbids injecting Actions into controllers, middleware, views, or Filament workflow code. |
| Style guide | `Docs/STYLEGUIDE.md` now defines naming for architecture-approved Actions and pure support mappers/helpers. |
| Key decision | Pure mapping and normalization belongs in `app/Support/`; workflow Actions are only for cohesive business operations. |
| Verification | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |

Verification:

```bash
php artisan test tests/Feature/ArchitectureGuardTest.php
```

---

### E03: Extract Only One Low-Risk Collaborator First

Status: Done

Priority: Medium

Goal: Start architecture refactor with one low-risk extraction after E01 and E02 are complete.

Recommended first candidates:

| Candidate | Reason |
| --- | --- |
| Pure homepage payload mapper cleanup | Lower risk if existing tests cover output shape. |
| Preview token store consolidation | Already exists, avoid duplicating token logic. |
| Page publish validator | Medium risk, only if structured errors are needed. |

Selected extraction:

| Extraction | Result |
| --- | --- |
| `HomepageDraftSectionMapper` | Added `app/Support/HomepageDraftSectionMapper.php` for deterministic homepage draft section normalization, stored draft rehydration, and preview section mapping. |
| `HomepagePublishingService` | Delegates editable draft normalization and stored draft section mapping to the support mapper. Publish, authorization, validation, cache invalidation, preview token invalidation, audit logging, and persistence remain in the service. |
| `PreviewService` | Delegates homepage preview section mapping to the support mapper. Token lifecycle, authorization, page preview orchestration, navigation assembly, and fallback homepage retrieval remain in the service. |
| Public contracts | No service interface signatures changed. |
| Action classes | None added; this was a support helper extraction, not an Action extraction. |

Do not start with:

| Candidate | Reason |
| --- | --- |
| Full `HomepagePublishingService::publish()` rewrite | High regression risk. |
| Full `PageService` action split | Too broad for first extraction. |
| Full `MediaService` rewrite | Upload/security-sensitive. |

Acceptance criteria:

- [x] One responsibility moved.
- [x] Public interface unchanged.
- [x] Targeted tests pass before and after.
- [x] Full suite passes after extraction.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Pre-extraction characterization | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php tests/Feature/PublicRuntimeTest.php --filter=homepage` passed: 20 tests, 132 assertions. |
| Added characterization | Draft save now explicitly verifies unknown section filtering and fixed-key order restoration. |
| Added characterization | Homepage preview now explicitly verifies locale-specific draft payloads and published fallback preservation. |
| Post-extraction homepage/runtime | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php tests/Feature/PublicRuntimeTest.php` passed: 30 tests, 175 assertions. |
| Preview focused regression | `php artisan test --filter=Preview` passed: 12 tests, 65 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Full regression | `php artisan test` passed: 3397 tests, 15356 assertions, 164.04s. |

Verification:

```bash
php artisan test
```

---

### E04: Extract Page Publishability Validator

Status: Done

Priority: High

Goal: Move page publishability checks out of `PageService` into an internal service-layer validator after locking draft publish behavior.

Why this matters: E01 identified page publish validation as a medium-risk candidate. While characterizing it, a bug was found where explicitly empty draft titles were replaced by generated defaults before validation, allowing incomplete draft content to publish.

Implementation steps:

- [x] Add characterization for valid draft publish applying localized draft content.
- [x] Add characterization for draft publish rejection when Arabic title is explicitly empty.
- [x] Add characterization for draft publish rejection when English title is explicitly empty.
- [x] Preserve explicit empty draft titles through draft mapping so validation can reject them.
- [x] Extract publishability checks into an internal service-layer validator.
- [x] Keep `PageServiceInterface` unchanged.
- [x] Register the validator as an internal singleton collaborator.

Selected extraction:

| Extraction | Result |
| --- | --- |
| `PagePublishabilityValidator` | Added `app/Services/PagePublishabilityValidator.php` for live-page and draft DTO publishability checks. |
| `PageService` | Delegates page and draft publishability checks to the validator. Publish transactions, draft application, authorization, cache invalidation, preview invalidation, and audit logging remain in `PageService`. |
| `PageDraftService` | Preserves explicit empty draft translation titles instead of replacing them with generated defaults. Missing title keys still use existing fallback behavior. |
| `AppServiceProvider` | Registers `PagePublishabilityValidator` as an internal singleton collaborator. |
| Public contracts | No service interface signatures changed. |

Acceptance criteria:

- [x] Draft payload with valid AR/EN titles still publishes and applies localized draft content.
- [x] Draft payload with explicitly empty AR title does not publish.
- [x] Draft payload with explicitly empty EN title does not publish.
- [x] Existing live page publish validation behavior remains covered.
- [x] Public page service interface remains unchanged.
- [x] Full regression passes.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Characterization discovery | Initial draft-title tests failed because empty draft titles were hidden by generated defaults before validation. |
| PageService focused suite | `php artisan test --filter=PageService` passed: 20 tests, 73 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Preview focused regression | `php artisan test --filter=Preview` passed: 12 tests, 65 assertions. |
| Optimistic locking regression | `php artisan test tests/Feature/OptimisticLockingPropertyTest.php` passed: 40 tests, 120 assertions. |
| Full regression | `php artisan test` passed: 3400 tests, 15371 assertions, 162.07s. |

Verification:

```bash
php artisan test --filter=PageService
php artisan test tests/Feature/ArchitectureGuardTest.php
php artisan test --filter=Preview
php artisan test tests/Feature/OptimisticLockingPropertyTest.php
php artisan test
```

---

### E05: Extract Media File Validation

Status: Done

Priority: High

Goal: Move security-sensitive uploaded file validation out of `MediaService` into an internal service-layer validator without changing media service contracts.

Why this matters: E01 identified media upload validation as a medium-high risk candidate. It controls MIME allow-listing, extension matching, size limits, and image dimension limits, so tests must lock behavior before extraction.

Implementation steps:

- [x] Add characterization for MIME/extension mismatch rejection.
- [x] Add characterization for image dimension limit rejection.
- [x] Add characterization for allowed Office document MIME types.
- [x] Add characterization for faculty editor upload rejection when scope is missing.
- [x] Extract MIME, extension, size, and image dimension validation into an internal validator.
- [x] Keep metadata persistence, storage, faculty scope resolution, authorization, audit logging, and DTO mapping in `MediaService`.
- [x] Keep `MediaServiceInterface` unchanged.
- [x] Register the validator as an internal singleton collaborator.

Selected extraction:

| Extraction | Result |
| --- | --- |
| `MediaFileValidator` | Added `app/Services/MediaFileValidator.php` for MIME allow-list, extension, size, and image dimension validation. |
| `MediaService` | Delegates uploaded file validation and primary extension resolution to the validator. Upload persistence, authorization, storage, faculty scoping, audit logging, listing, metadata updates, and DTO mapping remain in `MediaService`. |
| `AppServiceProvider` | Registers `MediaFileValidator` as an internal singleton collaborator. |
| Public contracts | No service interface signatures changed. |

Acceptance criteria:

- [x] Disallowed MIME types remain rejected.
- [x] SVG remains rejected even when UI validation is bypassed.
- [x] MIME/extension mismatches remain rejected.
- [x] Oversized files remain rejected.
- [x] Images over `8000x8000` remain rejected.
- [x] PDF, WebP, DOCX, XLSX, and PPTX uploads remain accepted.
- [x] Faculty editor uploads still force scope and reject missing scope.
- [x] Full regression passes.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Environment check | `php -r "echo extension_loaded('gd') ? 'gd loaded' : 'gd missing';"` returned `gd loaded`, so dimension tests are valid in this environment. |
| Pre-extraction characterization | `php artisan test --filter=Media` passed: 36 tests, 88 assertions. |
| PHP syntax | `php -l` passed for `MediaFileValidator.php`, `MediaService.php`, and `AppServiceProvider.php`. |
| Post-extraction media suite | `php artisan test --filter=Media` passed: 36 tests, 88 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3404 tests, 15187 assertions, 175.12s. |

Verification:

```bash
php artisan test --filter=Media
php artisan test tests/Feature/ArchitectureGuardTest.php
php artisan route:list
php artisan test
```

---

### E06: Extract Homepage Preview Assembler

Status: Done

Priority: High

Goal: Move homepage preview draft discovery, snapshot fallback, and section assembly out of `PreviewService` while keeping token orchestration and navigation payload assembly in `PreviewService`.

Why this matters: E01 identified homepage preview assembly as the next medium-risk boundary. The behavior is preview/security adjacent because draft content must stay tokenized, snapshot-bound, and excluded from public cache.

Implementation steps:

- [x] Add characterization for manual preview token invalidation and reuse rejection.
- [x] Add characterization that homepage publish invalidates existing homepage preview tokens.
- [x] Add characterization that homepage preview token creation requires homepage management permission.
- [x] Add characterization that faculty editors can create page preview tokens only for scoped pages.
- [x] Extract homepage preview assembly into a service-layer collaborator.
- [x] Keep `PreviewServiceInterface` unchanged.
- [x] Register the collaborator contract in the service container.

Selected extraction:

| Extraction | Result |
| --- | --- |
| `HomepagePreviewAssemblerInterface` | Added a contract returning `HomepageDTO` from a locale and optional token snapshot. |
| `HomepagePreviewAssembler` | Owns homepage preview draft lookup, snapshot interpretation, public fallback loading, fixed-key mapping, and locale direction. |
| `PreviewService` | Keeps token creation, resolution, validation, invalidation, authorization, page preview delegation, and navigation payload assembly. |
| `AppServiceProvider` | Binds the new assembler contract to the service implementation. |
| Public contracts | `PreviewServiceInterface` remains unchanged. |

Acceptance criteria:

- [x] Homepage preview still hydrates draft payloads and locale-specific payloads.
- [x] Homepage preview remains bound to the original token snapshot.
- [x] Manual invalidation deletes the token and prevents preview reuse.
- [x] Homepage publish invalidates existing homepage preview tokens.
- [x] Faculty editors cannot create homepage preview tokens and remain scoped for page previews.
- [x] Architecture guard passes with the new contract binding.
- [x] Full regression passes.

Completion evidence captured 2026-06-15:

| Evidence | Result |
| --- | --- |
| Pre-extraction homepage workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 19 tests, 110 assertions. |
| Pre-extraction public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| PHP syntax | `php -l` passed for `HomepagePreviewAssemblerInterface.php`, `HomepagePreviewAssembler.php`, `PreviewService.php`, and `AppServiceProvider.php`. |
| Post-extraction homepage workflow | `php artisan test tests/Feature/HomepageCmsWorkflowTest.php` passed: 19 tests, 110 assertions. |
| Post-extraction public runtime | `php artisan test tests/Feature/PublicRuntimeTest.php` passed: 14 tests, 79 assertions. |
| Architecture guard | `php artisan test tests/Feature/ArchitectureGuardTest.php` passed: 5 tests, 6 assertions. |
| Preview banner regression | `php artisan test tests/Feature/HomepageBlade/PreviewBannerTest.php` passed: 2 tests, 2 assertions. |
| Preview route check | `php artisan route:list --path=preview` listed the preview route. |
| Homepage admin route check | `php artisan route:list --path=admin/manage-homepage` listed the manage-homepage route. |
| Route boot | `php artisan route:list` passed: 67 routes listed. |
| Full regression | `php artisan test` passed: 3407 tests, 15271 assertions, 152.82s. |

Verification:

```bash
php artisan test tests/Feature/HomepageCmsWorkflowTest.php
php artisan test tests/Feature/PublicRuntimeTest.php
php artisan test tests/Feature/ArchitectureGuardTest.php
php artisan test tests/Feature/HomepageBlade/PreviewBannerTest.php
php artisan route:list
php artisan test
```

---

## 14. Workstream F: Admin And Filament Boundaries

### F01: Keep Filament Workflow Logic Service-Owned

Status: Ready

Priority: High

Goal: Prevent workflow logic from moving back into Filament resources/pages.

Why this matters: Architecture guard tests already protect parts of this boundary. The rule needs to stay explicit.

Implementation steps:

- [ ] Before editing any Filament page/resource, identify the service method it should call.
- [ ] Do not add Eloquent queries to Filament pages/resources except allowed model references required by Filament resources.
- [ ] If a Filament action needs data transformation, add service-layer method or support class under service ownership.
- [ ] Run architecture guard after admin changes.

Acceptance criteria:

- [ ] No new forbidden model imports in Filament workflow code.
- [ ] No publish/save/delete workflow logic lives in Filament classes.
- [ ] Gates and policies remain the source of admin authorization.

Verification:

```bash
php artisan test tests/Feature/ArchitectureGuardTest.php
php artisan test tests/Feature/PX06
```

---

### F02: Preserve Admin Auth Bilingual Brand

Status: Ready

Priority: Medium

Goal: Keep the admin login page bilingual enough to satisfy institutional branding expectations.

Why this matters: The test suite asserts both `SPU CMS` and the Arabic university name are visible on the login page.

Implementation steps:

- [ ] Do not remove Arabic institutional name from admin auth layout.
- [ ] If branding changes, update tests only after product approval.
- [ ] Keep AR login fully RTL and EN login readable with Arabic brand fallback.

Acceptance criteria:

- [ ] `/admin/login` renders successfully.
- [ ] Page includes `SPU CMS`.
- [ ] Page includes Arabic university name.

Verification:

```bash
php artisan test tests/Feature/AdminAuthFlowTest.php --filter=test_admin_login_page_loads
```

---

## 15. Workstream G: Security And Robustness

### G01: Maintain Sanitization Coverage

Status: Ready

Priority: High

Goal: Ensure user-supplied HTML remains sanitized before public rendering.

Implementation steps:

- [ ] Preserve `HtmlSanitizer` usage in page translation updates.
- [ ] Preserve recursive homepage payload sanitization on publish.
- [ ] Add tests before supporting any new rich-text field.
- [ ] Never render raw CMS HTML unless it has passed the sanitizer path.

Acceptance criteria:

- [ ] Existing sanitizer tests pass.
- [ ] Homepage publish sanitizes nested payload strings.
- [ ] Page body and legacy HTML blocks are sanitized.

Verification:

```bash
php artisan test --filter=Sanitizer
php artisan test tests/Feature/HomepageCmsWorkflowTest.php --filter=sanitizes
```

---

### G02: Maintain Upload Hardening

Status: Ready

Priority: High

Goal: Keep media upload validation safe, especially SVG handling.

Implementation steps:

- [ ] Do not weaken MIME/extension validation in `MediaService`.
- [ ] Keep SVG blocked or strictly sanitized according to current rule.
- [ ] Keep authorization scope checks for faculty editors.
- [ ] Add tests for any new allowed media type.

Acceptance criteria:

- [ ] Unsafe upload types are rejected by service validation.
- [ ] UI-only validation is not relied upon.
- [ ] Media tests pass.

Verification:

```bash
php artisan test --filter=Media
```

---

### G03: Maintain Preview Token Confidentiality

Status: Ready

Priority: High

Goal: Ensure preview tokens remain protected and draft content does not leak publicly.

Implementation steps:

- [ ] Keep preview tokens hashed or HMAC-protected at rest.
- [ ] Invalidate page preview tokens after draft save/publish where current behavior requires it.
- [ ] Invalidate homepage preview tokens after publish/unpublish where current behavior requires it.
- [ ] Keep preview cache bypass rules intact.

Acceptance criteria:

- [ ] Draft content is not public without preview token.
- [ ] Preview token remains bound to original snapshot.
- [ ] Token invalidation behavior remains tested.

Verification:

```bash
php artisan test --filter=Preview
php artisan test tests/Feature/PublicRuntimeTest.php --filter=preview
```

---

## 16. Workstream H: Cache, SEO, And Continuity

### H01: Keep Locale-Aware Cache Behavior Verified

Status: Ready

Priority: High

Goal: Prevent AR/EN cache contamination and stale public output after publish/update.

Implementation steps:

- [ ] Confirm public cache keys include locale.
- [ ] Confirm admin/authenticated/preview/non-GET requests bypass public cache.
- [ ] Confirm publish/update/delete invalidates relevant tags or safe fallback flushes.
- [ ] Keep cache tests updated when tags or keys change.

Acceptance criteria:

- [ ] AR page never receives EN cached payload, and vice versa.
- [ ] Preview never uses public cache.
- [ ] Publish invalidates public output.

Verification:

```bash
php artisan test --filter=Cache
php artisan test tests/Feature/MiddlewarePipelineTest.php
```

---

### H02: Preserve SEO Rendering And Validation

Status: Ready

Priority: Medium

Goal: Keep SEO metadata rendering correct while publish workflow remains focused on content validity.

Implementation steps:

- [ ] Keep SEO fallback rules in service layer.
- [ ] Keep canonical and hreflang rendering covered for AR/EN.
- [ ] Use PX07 SEO validation command for SEO completeness checks.
- [ ] Do not block publish on SEO warnings unless acceptance criteria changes.

Acceptance criteria:

- [ ] Homepage and page canonical URLs render correctly.
- [ ] Hreflang alternates render where translations exist.
- [ ] SEO command catches missing metadata.

Verification:

```bash
php artisan test tests/Feature/PX05/SeoRenderingTest.php
php artisan test tests/Feature/PX07/SeoValidationTest.php
```

---

### H03: Preserve Redirect And File Continuity Foundation

Status: Ready

Priority: Medium

Goal: Keep existing redirect/file continuity tooling stable without expanding into a full migration dashboard.

Implementation steps:

- [ ] Keep exact redirect behavior tested.
- [ ] Keep pattern redirect behavior tested.
- [ ] Keep unresolved request logging tested.
- [ ] Keep file continuity mapping tested.
- [ ] Do not add migration dashboard code until Phase 3 decision gate is approved.

Acceptance criteria:

- [ ] Redirect tests pass.
- [ ] File continuity tests pass.
- [ ] Unsafe external redirects remain blocked by existing continuity rules.

Verification:

```bash
php artisan test tests/Feature/PX05/RedirectContinuityTest.php
php artisan test tests/Feature/PX05/FileContinuityTest.php
php artisan test tests/Feature/PX07/RedirectValidationTest.php
```

---

## 17. Workstream I: Conditional Migration Decision

### I01: Decide Whether Legacy Migration Is Within 6 Months

Status: Blocked

Priority: Medium

Blocker: Business decision required.

Decision question: Is the legacy SPU system migration scheduled within 6 months?

If yes:

| Action | Requirement |
| --- | --- |
| Create migration implementation spec | Must be separate from homepage/admin foundation tasks. |
| Review current continuity tables/models | Do not invent missing schema. |
| Build dry-run-first migration commands | Never write production data before dry-run verification. |
| Add runbook | Required before operational migration tooling. |

If no:

| Action | Requirement |
| --- | --- |
| Defer dashboard and ETL tooling | Keep existing redirect/file continuity foundation only. |
| Record decision | Use this document or future `DECISIONS.md`. |
| Revisit trigger | Reopen when migration date is within 6 months. |

Acceptance criteria:

- [ ] Decision is recorded.
- [ ] No migration dashboard or broadcasting stack is added without approval.
- [ ] Existing continuity tests remain green.

Verification:

```bash
php artisan test tests/Feature/PX05 tests/Feature/PX07
```

---

## 18. Workstream J: Explicitly Deferred Full Modules

### J01: Defer Full News Module

Status: Deferred

Priority: Low for current foundation

Reason: Full News module is explicitly outside current scope in local project rules.

Do not create in this phase:

| Artifact | Reason |
| --- | --- |
| `news_*` migrations | Full module scope expansion. |
| `NewsArticle` models | Full module scope expansion. |
| `NewsServiceInterface` | Full module scope expansion. |
| `NewsArticleResource` | Full admin module scope expansion. |
| Public news routes/controllers/views | Full public module scope expansion. |

Allowed in current phase:

| Artifact | Condition |
| --- | --- |
| Homepage news cards | Only as CMS-driven homepage preview cards. |
| Navigation placeholders | Only if current menu foundation already supports them. |
| SEO/redirect planning docs | Documentation only, no full module implementation. |

Reopen criteria:

- [ ] Product approves News module as in scope.
- [ ] Architecture docs are updated to include News.
- [ ] Separate spec, plan, task list, and acceptance criteria are created.
- [ ] Current foundation release gate is green.

---

### J02: Defer Full Facilities, Research, Events, Admissions, CRM

Status: Deferred

Priority: Low for current foundation

Reason: These modules are explicitly outside current homepage/admin foundation scope.

Reopen criteria:

- [ ] Product approves module scope.
- [ ] Requirements source is updated.
- [ ] Architecture docs define module boundaries.
- [ ] Separate feature spec exists.
- [ ] Existing foundation remains green.

---

## 19. Suggested Execution Order

| Order | Task | Why |
| ---: | --- | --- |
| 1 | B01 | Establish command matrix before further changes. |
| 2 | C01 | Manual browser QA is the main remaining frontend confidence gap. |
| 3 | B02 | Keep release gate evidence current. |
| 4 | E01 | Measure architecture hotspots before refactoring. |
| 5 | E02 | Document Action pattern before adding Actions. |
| 6 | E03 | Extract one low-risk collaborator only after tests and docs. |
| 7 | C02 | Optimize FontAwesome only if measured benefit exists. |
| 8 | I01 | Resolve migration dashboard decision gate. |
| 9 | J01/J02 | Keep deferred module boundaries explicit. |

---

## 20. Open Decisions

| ID | Decision | Owner | Status | Notes |
| --- | --- | --- | --- | --- |
| DEC-001 | Should `.specify` be installed in this existing repo? | Tech lead | Open | Recommended: not now. Use local docs as constitution. |
| DEC-002 | Is legacy migration scheduled within 6 months? | Product/operations | Open | Blocks migration dashboard work. |
| DEC-003 | Should Action classes be adopted now or post-launch? | Tech lead | Open | Requires E01 measurement and E02 architecture doc first. |
| DEC-004 | Should publish return structured validation errors to admin UI? | Product/tech lead | Open | If yes, consider D02 extraction. |
| DEC-005 | Is remaining FontAwesome runtime cost acceptable? | Frontend owner | Open | Requires C02 measurement. |

---

## 21. Risk Register

| Risk | Probability | Impact | Mitigation |
| --- | --- | --- | --- |
| Roadmap scope drift into full modules | Medium | High | Keep J01/J02 deferred and enforce `AGENTS.md`. |
| Refactor breaks stable publish workflow | Medium | High | Characterization tests before extraction. |
| Frontend chunking causes Alpine startup race | Low | High | Keep homepage registration before `Alpine.start()` and test public runtime. |
| Manual QA skipped after automated tests | Medium | Medium | C01 remains Ready until browser checks are recorded. |
| Stale TODO checkboxes misrepresent readiness | Medium | Medium | B02 requires evidence per checkbox. |
| Migration tooling writes unsafe data | Low if deferred | Critical | I01 blocks implementation until decision and dry-run rules exist. |

---

## 22. Session Start Checklist

Before any future implementation session:

- [ ] Read `AGENTS.md`.
- [ ] Read the target task card in this document.
- [ ] Check `git status --short`.
- [ ] Identify unrelated user changes and do not modify them.
- [ ] Confirm task is Ready, not Proposed, Blocked, or Deferred.
- [ ] Run the targeted baseline command if the change is risky.
- [ ] Make the smallest correct change.
- [ ] Run the task-specific validation command.
- [ ] Update docs only if behavior or evidence changed.

---

## 23. Completion Targets

This TODO is complete when:

- [ ] C01 manual browser QA is completed and recorded.
- [ ] B01 command matrix is accepted by the team.
- [ ] B02 release gate evidence is current.
- [ ] E01 architecture measurement is complete.
- [ ] E02 decision on Action classes is recorded.
- [ ] I01 migration timing decision is recorded.
- [ ] Deferred module boundaries remain intact.
- [ ] `php artisan test` passes.
- [ ] `npm run build` passes with no unexpected warnings.
- [ ] `php artisan route:list` passes.

---

## 24. Final Rule

Do only what is needed for the current foundation. High-quality work means correct scope, small safe changes, clear evidence, and no silent expansion into future modules.
