# WORKFLOW.md

## Development Model

Two backend developers working in parallel.

---

## Prompt Execution Rules

- One prompt per OpenCode session
- Always start a fresh session per prompt
- Never combine prompts
- Paste the full prompt including CONTEXT, TASK, DONE WHEN, and CONSTRAINTS
- Ask the agent to confirm understanding before writing code
- Generate stubs / structure first, then fill implementations
- Run validation commands frequently instead of waiting until the end

---

## Execution Order

Strict order — never skip ahead:

P01 → P02 → P03 → P04 → P05 → P06 → P07 → P08 → P09 → P10 → P11 → P12

---

## Parallel Work

Allowed only when stated dependencies are satisfied.

Recommended safe parallelization:
- P02 + P03 can run in parallel after P01 is complete

Do not parallelize prompts that modify the same shared core files unless both developers coordinate first.

---

## Validation After Each Prompt

Run what is actually available and relevant after each prompt.

Preferred validation set:
```bash
php artisan list
php artisan route:list
php artisan tinker
php artisan test
```

Practical rule:
- if routes do not exist yet, `route:list` is informational
- if tests do not exist yet, `php artisan test` may be limited
- if DB-dependent code was added, validate against a working database
- do not move on while the current prompt is broken

---

## Handoff Rules

Before handing off to the other developer:

- code compiles with no PHP errors
- interfaces resolve via the DI container
- current prompt acceptance criteria are verified
- no accidental expansion beyond the current prompt scope
- committed to Git on the correct branch

---

## Git Strategy

- One branch per prompt:
  - `feature/p01-interfaces-patch`
  - `feature/p02-middleware`
  - `feature/p03-auth-rbac`
  - etc.
- No direct commits to `main`
- Merge only after validation passes

Coordinate before touching shared files such as:
- migrations
- `AppServiceProvider`
- `bootstrap/app.php`
- public routes
- admin routes
- Filament panel/resources/pages
- shared DTOs and interfaces

---

## Failure Handling

If generated code fails any DONE WHEN criterion:

1. Stop immediately
2. Fix the specific failure
3. Re-run relevant validation commands
4. Re-check DONE WHEN criteria
5. Only then continue

Never build on broken code.
Never move to the next prompt with failing acceptance criteria.

---

## Agent Context Block

When starting each OpenCode session, prepend this to the prompt:

```text
This is a Laravel 11 project using Filament v3, MySQL 8, Redis, and optional Meilisearch.
Architecture: MVC + Service Layer.
Controllers consume DTOs or structured service payloads only.
Interfaces live in app/Contracts/.
DTOs live in app/DTOs/.
Services live in app/Services/.
Services are the only place for business logic.
Public service methods must not return raw Eloquent models.
This sprint scope is only the homepage, navigation shell, landing-page builder, and admin/CMS foundation.
```

---

## Reference Documents

| Document | Purpose |
|---|---|
| SPU_Requirements_v5_1.docx | Authoritative requirements source |
| SPU OpenCode Prompts — Homepage + Admin Panel Foundation (v5.1 aligned) | Current prompt sequence |
| ARCHITECTURE.md | Layer responsibilities and data flow |
| BACKEND_RULES.md | Laravel conventions and enforcement rules |
| STYLEGUIDE.md | PHP standards, naming, return types |
| WORKFLOW.md | Prompt execution workflow |

---

## Final Rule

The prompt pack and these supporting docs are for **homepage + admin panel foundation only**.

If a prompt or generated output starts drifting into:
- full Facilities
- full Research
- full News
- full Events
- full Admissions
- full Contact

stop and pull it back to the approved sprint scope before continuing.
