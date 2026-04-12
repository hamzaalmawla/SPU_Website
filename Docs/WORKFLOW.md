# WORKFLOW.md

## Development Model

Two backend developers working in parallel.


---

## Prompt Execution Rules

- One prompt per OpenCode session
- Always start a fresh session per prompt
- Never combine prompts
- Paste full prompt including CONTEXT, TASK, DONE WHEN, and CONSTRAINTS
- Ask agent to confirm understanding before writing code
- Generate stubs first, then fill implementations
- Run php artisan after each file to catch syntax errors early

---

## Execution Order

Strict order — never skip ahead:

P01 → P02 → P03 → P04 → P05 → P06 → P07 → P08 → P09 → P10 → P11 → P12 → P13 → P14 → P15

---

## Parallel Work

Allowed only when stated dependencies are satisfied:

- P02 + P03 can run in parallel after P01 is complete
- P11 + P12 + P13 + P14 can all run in parallel after P06 is complete

Good split for parallel: Hamza on P11+P12, Mudar on P13+P14.

---



## Validation After Each Prompt

Run after every completed prompt:

```bash
php artisan list
php artisan route:list
php artisan tinker
php artisan test
```

All must pass with 0 errors before moving to the next prompt.

---

## Handoff Rules

Before handing off to the other developer:

- Code compiles with no PHP errors
- All interfaces resolve via DI container
- No TODOs left (except P03 stubs — these are intentional)
- All DONE WHEN criteria explicitly verified
- Committed to Git

---

## Git Strategy

- One branch per prompt: feature/p01-interfaces, feature/p02-middleware, etc.
- No direct commits to main
- Merge only after validation commands pass
- Notify Mudar before running prompts that touch shared files:
  - Migrations (P04)
  - AppServiceProvider (P01, P05, P06)
  - bootstrap/app.php (P02, P14)
  - routes/web.php (P08)

---

## Failure Handling

If generated code fails any DONE WHEN criterion:

1. STOP immediately
2. Fix the specific failure
3. Re-run all validation commands
4. Confirm all DONE WHEN criteria pass
5. Only then continue

Never build on broken code.
Never move to the next prompt with a failing acceptance criterion.

---

## Agent Context Block

When starting each OpenCode session, prepend this to the prompt:

```
This is a Laravel 11 project using Filament v3, MySQL 8, Redis, and Meilisearch.
Architecture: MVC + Service Layer.
DTOs: all service interface methods returning entities use PHP 8.2 readonly DTOs.
Controllers consume DTOs only — no Eloquent model imports outside app/Services/.
Every service has a private toDTO(Model): DTO mapping method.
Interfaces in app/Contracts/. DTOs in app/DTOs/. Services in app/Services/.
```

---

## Reference Documents

| Document | Purpose |
|---|---|
| SPU_Requirements_v4_Final.docx | Authoritative requirements source |
| SPU_OpenCode_Backend_Prompts_v1.docx | Sprint 1 prompt sequence |
| ARCHITECTURE.md | Layer responsibilities and data flow |
| BACKEND_RULES.md | Laravel conventions and enforcement rules |
| STYLEGUIDE.md | PHP standards, naming, return types |
| ARCHITECTURE_DECISIONS.md | Locked architectural decisions (ADRs) |
