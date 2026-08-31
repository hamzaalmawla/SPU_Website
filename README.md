# Syrian Private University — Website Foundation

The official website platform for [Syrian Private University](https://spu.edu.sy), built as a bilingual
(Arabic / English) CMS with a Filament v3 admin panel. It covers the full public site — About, Admissions,
Campus Life, Contact, E-Services, Facilities, News and Events, Research, Alumni and the Virtual Tour — plus
the admin panel that manages them and the legacy URL continuity layer that carries the old site's addresses
forward.

The build is deployed at `v2.spu.edu.sy` and is being prepared to take over `spu.edu.sy`. Launch gates and
their current status live in `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`; nothing in this README
implies deployment or launch approval.

## Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 (PHP 8.2+) |
| Admin Panel | Filament v3 |
| Database | MariaDB 10.11 in production; MySQL 8 compatible. Tests run on SQLite, so keep SQL portable |
| Cache / Queue / Session | File cache, database queue and sessions in production — **there is no Redis on the host.** Redis is supported by the code and used nowhere live |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 7 |
| Icons | Static SVG assets / inline SVG |
| Sanitization | HTMLPurifier 4 |
| 2FA | Google2FA (TOTP) |
| Monitoring | Sentry (APM + error tracking) |
| Testing | PHPUnit 11 |

## Quick Start

```bash
git clone <repo-url> && cd SPU_Website
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
php artisan migrate:fresh --seed
php artisan serve
```

| URL | What |
|-----|------|
| `http://localhost:8000/ar` | Public homepage (Arabic) |
| `http://localhost:8000/en` | Public homepage (English) |
| `http://localhost:8000/admin` | Admin panel |

Admin login uses `ADMIN_EMAIL` and `ADMIN_PASSWORD` from your environment. Local development falls back to `local-development-password` only when `ADMIN_PASSWORD` is unset.

> **Note:** Set `CACHE_STORE=array` in `.env` for local development. The default `database` driver does not
> support cache tags. Production runs `CACHE_STORE=file` — the host has neither Redis nor Memcached, and `file`
> benchmarks around 5x faster than `database` while keeping page HTML out of the database.
> See `deploy/v2-staging/README.md` section 6.

## Architecture

MVC + Service Layer with strict layer separation:

```
Request → Middleware → Controller → Service → Model → Database
                         ↓
                        DTO → View / JSON
```

**Rules enforced by automated tests:**
- Controllers never import Eloquent models
- Services are the only layer with business logic
- All service dependencies use interfaces from `app/Contracts/`
- Public service methods never return raw Eloquent models — only DTOs
- Models contain relationships, scopes, and casts only

```
app/
├── Contracts/          105 service interfaces
├── DTOs/               179 data transfer objects (final readonly)
├── Services/           135 service implementations
├── Models/             89 Eloquent models
├── Http/
│   ├── Controllers/    21 thin controllers
│   └── Middleware/     9 custom middleware
├── Filament/
│   ├── Pages/          22 admin pages
│   ├── Resources/      16 admin resources
│   └── Support/        extracted form-schema builders
├── Policies/           11 authorization policies
├── Support/            payload mappers, HtmlSanitizer, media URL resolution
├── Console/Commands/   71 artisan commands
└── Providers/          AppServiceProvider (all bindings)
```

Counts move as the site grows — `ArchitectureGuardTest` is what actually holds the layering, not this diagram.

## Features

### Public Site
- **Bilingual homepage** — 11 CMS-driven sections with independent AR/EN content
- **RTL / LTR** — Arabic renders right-to-left, English left-to-right
- **Landing pages** — Generic page builder with structured content blocks
- **Navigation shell** — Header, footer, and utility menus with locale-aware URLs
- **SEO** — Per-page meta tags, canonical URLs, hreflang alternates, XML sitemap, robots.txt
- **Legacy continuity** — Exact and pattern-based redirects from the old site, with loop detection and unresolved request logging
- **Response caching** — Locale-aware page cache with tag-based invalidation

### Admin Panel (Filament v3)

| Resource / Page | URL | Access |
|----------------|-----|--------|
| ManageHomepage | `/admin/manage-homepage` | super_admin, editor |
| ManageMenu | `/admin/manage-menu` | super_admin, editor |
| ManageSettings | `/admin/manage-settings` | super_admin, editor |
| PageResource | `/admin/pages` | super_admin, editor, faculty_editor |
| MediaAssetResource | `/admin/media-assets` | super_admin, editor, faculty_editor |
| UserResource | `/admin/users` | super_admin only |
| AuditLogResource | `/admin/audit-logs` | super_admin only |
| TwoFactorSetup | `/admin/two-factor-setup` | All authenticated |

### CMS Workflow
- **Draft** → Save work in progress without affecting the live site
- **Preview** → Tokenized preview URLs for AR and EN, never leaks draft content publicly
- **Publish** → Validates required content, writes translations, invalidates cache, creates audit log
- **Schedule** → Set a future publish date
- **Unpublish** → Remove from public view
- **Optimistic locking** — Version-based conflict detection prevents concurrent editors from overwriting each other

### Security
- **RBAC** — Three roles (super_admin, editor, faculty_editor) enforced via Gates and Policies
- **Account locking** — 5 failed login attempts lock the account; requires super_admin to unlock
- **HTML sanitization** — HTMLPurifier with strict allowlist on all user-supplied content
- **2FA** — TOTP-based two-factor authentication
- **Webhook verification** — HMAC-SHA256 signature validation
- **Security headers** — X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **Audit logging** — Append-only trail for every admin write operation

## Homepage Sections

The homepage is a fixed 11-section CMS page. Each section supports independent AR/EN content:

| # | Key | Description |
|---|-----|-------------|
| 1 | `hero` | Hero banner with background image, headline, CTAs |
| 2 | `hero_stats` | Statistics row below the hero |
| 3 | `academic_faculties` | Faculty cards grid |
| 4 | `achievements_highlights` | University achievements and highlights |
| 5 | `choose_your_path` | Program/pathway selection cards |
| 6 | `university_news` | News article feed |
| 7 | `research_studies` | Research publications |
| 8 | `events_activities` | Upcoming events with calendar |
| 9 | `medical_facilities_services` | Medical center information |
| 10 | `bottom_stats` | Footer statistics row |
| 11 | `footer` | Footer content, social links, legal links |

## Role System

| Role | Homepage | Pages | Menu | Media | Settings | Users | Audit |
|------|----------|-------|------|-------|----------|-------|-------|
| super_admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| editor | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| faculty_editor | ❌ | scoped | ❌ | scoped | ❌ | ❌ | ❌ |

Faculty editors see only pages and media matching their `faculty_scope_slug`.

## Caching

- **Driver:** file with tag-based invalidation (production), array (local). Redis is supported by the code but is not available on the host
- **Public pages:** Cached with locale in the key, bypassed for authenticated users, admin routes, preview flows, and non-GET requests
- **Invalidation:** Publish/update/delete operations flush the corresponding cache tags immediately for both AR and EN
- **Headers:** `X-Cache: HIT | MISS | BYPASS` on every public response
- **TTLs:** Public HTML response cache defaults to 5 minutes via `config('cache.public_page_ttl', 300)`; service-level navigation/settings cache TTLs are configured in their services.

## Testing

Run the current test suite with `php artisan test`. Test counts vary as property and phase-specific coverage changes.

```bash
# Full suite
php artisan test

# Quick check (skip property tests)
php artisan test --exclude-group=property

# Architecture guard only
php artisan test --filter=ArchitectureGuardTest

# Property tests only
php artisan test --group=property

# Specific phase
php artisan test --filter=PX06
```

| Category | Tests | What they cover |
|----------|-------|-----------------|
| Property tests | ~2,000 | Canonical URLs, hreflang, SEO fallbacks, sitemap filtering, redirect resolution, cache key locale, optimistic locking, HTML sanitization, faculty scoping, locked accounts, payload round-trip |
| Feature tests | ~1,100 | Admin access control, CMS workflow, middleware pipeline, navigation, settings, public runtime, launch validation, CLI commands |
| Unit tests | ~200 | Slug generation, media upload, HTML sanitization, TOTP, SEO metadata, form schema, payload mapper |

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `launch:validate` | Pre-launch checks: homepage rendering, SEO, sitemap, redirects, cache, audit |
| `cache:warm` | Warms homepage, navigation, settings, and sitemap caches |
| `continuity:validate-redirects` | Detects duplicate/conflicting redirect rules (`--fix` to auto-resolve) |
| `continuity:export-url-inventory` | Exports redirect rules as JSON or CSV |
| `continuity:export-file-inventory` | Exports file continuity inventory |
| `continuity:report-unresolved` | Reports unresolved legacy requests (`--since`, `--type` filters) |
| `continuity:validate-seo` | Identifies pages with weak or missing SEO metadata |
| `continuity:reconciliation-report` | Combined report of all continuity checks |

## Routes

**Public:**
| Method | Path | Handler |
|--------|------|---------|
| GET | `/` | Redirect → `/ar` |
| GET | `/{locale}` | HomeController (homepage) |
| GET | `/{locale}/preview` | PreviewController (tokenized) |
| GET | `/{locale}/{slug}` | PageController (landing pages) |
| POST | `/{locale}/contact` | PublicContactController (throttled) |
| GET | `/sitemap.xml` | SitemapController |
| GET | `/robots.txt` | SitemapController |

**Admin:** Filament panel at `/admin` with custom auth routes for login, logout, and 2FA challenge.

**Webhook:** `POST /webhook/incoming` with HMAC-SHA256 signature verification.

## Environment

Copy `.env.example` for local development. See `.env.production.example` and `Docs/production-env-baseline.md` for production requirements.

Key settings:

| Variable | Local | Production |
|----------|-------|------------|
| `APP_ENV` | local | production |
| `APP_DEBUG` | true | false |
| `APP_URL` | http://localhost | https://spu.edu.sy |
| `CACHE_STORE` | array | file |
| `SESSION_DRIVER` | database | database |
| `QUEUE_CONNECTION` | database | database |
| `SESSION_SECURE_COOKIE` | false | true |
| `SESSION_ENCRYPT` | false | true |
| `SENTRY_LARAVEL_DSN` | (empty) | (your DSN) |

## Database

84 migrations covering CMS tables, legacy continuity, and the full domain model. 35 seeders for local
development and production baseline data.

```bash
php artisan migrate:fresh --seed    # Full reset with seed data
php artisan db:seed                 # Seed only (no migration reset)
```

## Project Documentation

| File | Contents |
|------|----------|
| `AGENTS.MD` | Architecture rules, scope definition, AI generation constraints |
| `Docs/ARCHITECTURE.md` | Layer responsibilities, interface contracts, DTO conventions |
| `Docs/BACKEND_RULES.md` | Laravel conventions, service/controller/model rules |
| `Docs/launch-readiness-checklist.md` | 11-section pre-launch verification checklist |
| `Docs/production-env-baseline.md` | Production environment requirements and launch gate |
| `Docs/Dev-Handoff-Report-PX05-PX08.md` | Implementation report for phases 5–8 |
| `Docs/rollback-preparation.md` | Rollback thresholds and abort criteria |

## Development Scripts

```bash
composer dev          # Starts server, queue worker, log tail, and Vite concurrently
composer test         # Clears config cache and runs full test suite
composer setup        # Full project setup (install, key, migrate, build)
npm run dev           # Vite dev server with HMR
npm run build         # Production asset build
```

## Current Scope

`AGENTS.MD` is authoritative: this repository is in **full-site completion and production-readiness scope**.
The foundation-only phase restriction previously recorded here is obsolete — every module it listed as out of
scope is built and deployed.

Built:

- Public homepage (11 CMS sections, bilingual) and the navigation shell
- About, Admissions, Campus Life, Contact, E-Services, Facilities, News and Events, Research, Alumni, Virtual Tour
- Faculty pages, subpages, profiles, projects, study plans, course pages, alumni and valedictorian directories
- Dynamic forms, registrations, applications, server-side filtering and pagination
- Admin panel and CMS coverage for managed content, with draft / preview / publish / schedule workflows
- Menu builder, settings, media library, audit logging
- Legacy URL continuity and the SEO layer (sitemap, robots.txt, canonical, hreflang)

Migrated content in the deployed database: 2,093 published articles (1,090 news and 1,003 announcements),
289 research publications, 260 faculty members, 4,939 alumni.

What remains before this can become the primary domain is tracked as gates, not as scope — see
`Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md` and `Docs/launch-readiness-checklist.md`. Several of those
gates are host-level (OPcache, compression and PHP-FPM pool sizing all need WHM/root) and cannot be closed
from this repository.

## License

Proprietary — Syrian Private University(hamza almawla & amer khorsheed). All rights reserved.
