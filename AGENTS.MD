# AGENTS.md

## Project
Syrian Private University Website (spu.edu.sy)

Stack:
- Laravel 12 (must follow Laravel 11+ structure)
- PHP 8.2+
- MySQL 8
- Redis
- Meilisearch
- Filament v3

---

## Source of Truth
- Requirements: SRS v4.0
- Architecture: MVC + Service Layer

---

## Core Architecture Rules (NON-NEGOTIABLE)

1. Service Layer is the ONLY place for business logic
2. Controllers must be thin (no logic)
3. Models contain ONLY:
   - relationships
   - scopes
   - casts
   - simple accessors
4. All dependencies must use interfaces (app/Contracts)
5. NEVER instantiate services using `new`
6. Use DTOs for structured data
7. All methods must have typed signatures

---

## Laravel Structure Rules (CRITICAL)

- Use Laravel 11+ structure
- DO NOT generate app/Http/Kernel.php
- Middleware must be registered in bootstrap/app.php
- Use modern service providers
- Use FormRequest for validation
- Use Policies / Gates for authorization

---

## AI GENERATION RULES

When generating code:

- NEVER move business logic into controllers or models
- NEVER invent database columns
- NEVER assume missing relationships
- ALWAYS respect interfaces
- ALWAYS generate full imports and namespaces
- ALWAYS validate against acceptance criteria

---

## Multilingual Rules (CRITICAL)

- Default locale: ar
- Secondary: en
- All content must support AR/EN
- Use Translation polymorphic model
- No hardcoded translatable strings

---

## Role System

- super_admin → full access
- editor → content only
- faculty_editor → scoped by faculty_id

Never bypass role checks.

---

## System Guarantees

- No placeholder pages at launch
- All admissions pages must be complete
- CMS-driven architecture only
- Homepage has exactly 10 sections

---

## Forbidden

- ❌ Business logic in controllers
- ❌ Business logic in models
- ❌ Direct DB queries outside services
- ❌ Hardcoded CMS content
- ❌ Using concrete classes instead of interfaces
- ❌ Breaking architecture

---

## Definition of Done

Task is complete ONLY if:

- Code compiles (php artisan works)
- Interfaces resolve via container
- No architecture violations
- Acceptance criteria satisfied
- No missing imports or classes