# Frontend Route Parity Matrix

This matrix reconciles the approved reference frontend with the Laravel implementation. The reference inventory contains 167 entries in `src/config/site-pages.json`; the frontend generator adds eight career-detail pages, producing 175 effective pages.

Reference paths are mapped to their Laravel `/{locale}` equivalents for module classification. Approved unprefixed deep links negotiate browser language, and the physical reference HTML inventory redirects to canonical localized paths.

## Legend

| Code | Meaning |
|---|---|
| `D` | Dedicated Laravel handler |
| `R` | Approved redirect |
| `M` | Missing dedicated handler or compatibility route |
| `Y` | Complete evidence exists |
| `P` | Partial, incomplete, or insufficiently verified |
| `N` | Missing |
| `NA` | Not applicable |

Columns: `Route`, `CMS`, `Behavior`, `Assets`, and `Tests`. `Overall` is conservative and does not treat route existence alone as parity.

## Current Remediation Overlay - 2026-08-21

The per-route rows below record implemented reference-route parity at the time they
were written. They do not establish current production content readiness or launch
sign-off. The following overlay supersedes any broader current-state inference:

| Affected slice | Code status | Remaining parity evidence |
|---|---|---|
| Admissions, Campus Life, E-Services, Research, News Events/Gallery, faculty projects | Fixture/sample-backed public fallbacks removed locally where remediated. | Publish reviewed AR/EN CMS/database content or approve retirement/navigation changes; deploy and verify empty, 404, listing, detail, and asset behavior. |
| Research publication sitemap | Fixed locally so eligible published database records do not depend on a synthetic CMS archive payload. | Deploy and validate canonical sitemap output and draft exclusion. |
| Cross-cutting accessibility | Semantics remediated locally with automated coverage. Rendered-page audit run against the live site 2026-09-02 (`tests/browser/accessibility-audit.mjs`, 13 routes × AR/EN × desktop/mobile): keyboard traversal, focus visibility, layout overflow, computed contrast, reduced motion, console and network all checked; two defect classes found and fixed. | **Screen-reader QA still outstanding** — no automated tool can produce it. Also unexercised: routes outside the audited 13, admin, forms under submission, announcement behaviour, and the contrast of 33 text elements that sit on background images and cannot be computed from style. |
| Origin and front controller | Canonical host/proxy/front-controller hardening implemented locally. | Host deployment and proxy/header/redirect/path probes. |

Until that evidence exists, `Complete` below means implementation parity evidence,
not production-ready content, browser accessibility approval, deployment, or sign-off.
See `Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`.

## Home

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/` | D | Y | Y | Y | Y | Complete | CMS content, RTL-aware keyboard sliders, reduced-motion behavior, focus/autoplay handling, counters, reveals, assets, and focused tests are implemented. |

## About

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/about/` | D | Y | Y | Y | Y | Complete | Verified bilingual content, three managed images, CMS highlights/stats, Vision/Mission CTA, metadata, and keyboard/touch/RTL navigation are implemented. |
| `/about/vision-mission/` | D | Y | Y | Y | Y | Complete | Dedicated typed payload, bilingual CMS editor/workflow, canonical SEO, accessible responsive rendering, reference assets, and tested HTML continuity. |
| `/about/history/` | D | Y | Y | Y | Y | Complete | Verified 2005/2009/2012 timeline, managed founding image, bilingual narrative, SEO, and responsive timeline are implemented. |
| `/about/leadership/` | D | Y | Y | Y | Y | Complete | Person draft/preview/publish/schedule workflow, faculty filter, and accessible keyboard/touch RTL dean carousel are implemented. |
| `/about/profile/` | R | Y | Y | Y | Y | Redirect | Legacy query profiles resolve once to typed Person or FacultyMember canonical profiles; both entity sources have publication workflows. |
| `/about/directorates/` | D | Y | Y | Y | Y | Complete | Published directorate entities, linked bilingual listing, correspondence guidance, staff CTA, metadata, and tests are implemented. |
| `/about/partnership/` | R | Y | Y | Y | Y | Redirect | One-hop canonical redirect; verified destination has search, stable category filtering, pagination, empty/reset state, proposal flow, and entity workflow. |
| `/about/directorates/scientific-research/` | D | Y | Y | Y | Y | Complete | Directorate entity workflow, localized services, contact flow, related About navigation, SEO/sitemap, and focused route tests are implemented. |
| `/about/directorates/student-affairs/` | D | Y | Y | Y | Y | Complete | Directorate entity workflow, localized services, contact flow, related About navigation, SEO/sitemap, and focused route tests are implemented. |
| `/about/directorates/it-services/` | D | Y | Y | Y | Y | Complete | Directorate entity workflow, localized services, contact flow, related About navigation, SEO/sitemap, and focused route tests are implemented. |
| `/about/directorates/public-relations/` | D | Y | Y | Y | Y | Complete | Directorate entity workflow, localized services, contact flow, related About navigation, SEO/sitemap, and focused route tests are implemented. |
| `/about/directorates/staff/` | D | Y | Y | Y | Y | Complete | Publication-aware Person/FacultyMember directory with server filtering, pagination, query-preserving locale links, and typed profiles is implemented. |
| `/about/accreditation/` | D | Y | Y | Y | Y | Complete | Saveable bilingual editor, conservative verified national-accreditation narrative/facts, preview/publication workflow, SEO, and tests are implemented. |
| `/about/why-spu/` | D | Y | Y | Y | Y | Complete | Saveable bilingual editor, six managed advantages, narrative, preview/publication workflow, SEO, and tests are implemented. |
| `/about/quality-policy/` | D | Y | Y | Y | Y | Complete | Managed badge, multi-paragraph narrative, policy cards, SEO, bilingual workflow, and tests are implemented. |
| `/about/ethical-charter/` | D | Y | Y | Y | Y | Complete | Managed badge, multi-paragraph narrative, principle cards, SEO, bilingual workflow, and tests are implemented. |
| `/about/organizational-structure/` | D | Y | Y | Y | Y | Complete | Managed badge, governance narrative, structure cards, SEO, bilingual workflow, and tests are implemented. |
| `/about/university-council/` | R | NA | NA | NA | Y | Redirect | Approved redirect to leadership is implemented. |

## Admissions

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/admissions/` | D | Y | Y | Y | Y | Complete | Approved assets, complete localized resources, responsive rendering, accessible navigation, and tests are implemented. |
| `/admissions/requirements/` | D | Y | Y | Y | Y | Complete | Bilingual CMS content, accessible interactions, RTL behavior, and focused tests are implemented. |
| `/admissions/tuition/` | D | Y | Y | Y | Y | Complete | Fabricated finance data is removed; only verified CMS data/actions render, with transparent managed guidance otherwise. |
| `/admissions/faq/` | D | Y | Y | Y | Y | Complete | Search, accessible accordion behavior, empty states, AR/RTL rendering, and tests are implemented. |
| `/admissions/how-to-apply/` | D | Y | Y | Y | Y | Complete | A validated Admissions application flow with server-owned context, persistence, and authorized review is implemented. |
| `/admissions/transfer/` | D | Y | Y | Y | Y | Complete | Bilingual CMS tabs, accessible navigation, RTL behavior, and focused tests are implemented. |
| `/admissions/calendar/` | D | Y | Y | Y | Y | Complete | Inert downloads and fabricated dates are removed; verified Media Library resources render when available with managed guidance otherwise. |
| `/admissions/documents/` | D | Y | Y | Y | Y | Complete | Addressable accessible tabs and reviewed Media Library documents replace inert links, with safe managed fallback guidance. |
| `/admissions/study-system/` | R | NA | Y | NA | Y | Redirect | Redirect opens the addressable study-system tab and preserves locale state. |
| `/admissions/academic-warnings/` | R | NA | Y | NA | Y | Redirect | Redirect opens the addressable academic-warnings tab and preserves locale state. |
| `/admissions/filling-vacancies/` | D | Y | Y | Y | Y | Complete | Fabricated seats/dates are removed; bilingual CMS-published factual content and transparent guidance are implemented and tested. |
| `/admissions/graduation-exams/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual CMS content, landing resource, localized links, and focused AR/EN tests are implemented. |

## Research

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/research/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed gateway. |
| `/research/repository/` | D | Y | Y | Y | Y | Complete | Authoritative CMS/database publications, functional filters/pagination, reset/empty states, locale query preservation, and tests are implemented. |
| `/research/centers/` | D | Y | Y | Y | Y | Complete | Bilingual center/laboratory editor, protected preview, validated publication workflow, localized SEO, sitemap, assets, and tests are implemented. |
| `/research/centers/ai-digital-innovation/` | D | Y | Y | Y | Y | Complete | Published center catalog drives the detail page, explicit relationships, real affiliated researchers, contact fields, preview, SEO, sitemap, and tests. |
| `/research/centers/clinical-research-simulation/` | D | Y | Y | Y | Y | Complete | Published center catalog drives the detail page, explicit relationships, real affiliated researchers, contact fields, preview, SEO, sitemap, and tests. |
| `/research/centers/energy-sustainable-systems/` | D | Y | Y | Y | Y | Complete | Published center catalog drives the detail page, explicit relationships, real affiliated researchers, contact fields, preview, SEO, sitemap, and tests. |
| `/research/projects/` | D | Y | Y | Y | Y | Complete | Bilingual project catalog, functional filtering/pagination, protected preview, validated publication workflow, sitemap, and tests are implemented. |
| `/research/projects/earthquake-resistant-concrete-syria/` | D | Y | Y | Y | Y | Complete | Published project catalog drives the detail, preview, relationships, SEO, sitemap, and tests. |
| `/research/projects/ai-dental-diagnostics-system/` | D | Y | Y | Y | Y | Complete | Published project catalog drives the detail, preview, relationships, SEO, sitemap, and tests. |
| `/research/projects/arabic-clinical-nlp-system/` | D | Y | Y | Y | Y | Complete | Published project catalog drives the detail, preview, relationships, SEO, sitemap, and tests. |
| `/research/projects/pharmaceutical-quality-monitoring/` | D | Y | Y | Y | Y | Complete | Published project catalog drives the detail, preview, relationships, SEO, sitemap, and tests. |
| `/research/projects/reservoir-characterization-ai/` | D | Y | Y | Y | Y | Complete | Published project catalog drives the detail, preview, relationships, SEO, sitemap, and tests. |
| `/research/publications/` | D | Y | Y | Y | Y | Complete | CMS-backed filtering, pagination, reset/empty states, query-preserving locale links, scholarly metadata, and downloads are implemented. |
| `/research/publications/machine-learning-pharmaceutical-quality-control/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/ai-dental-diagnostics/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/structural-analysis-earthquake-resistant-concrete/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/deep-learning-reservoir-permeability/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/clinical-simulation-training-medical-students/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/arabic-medical-record-nlp/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/business-analytics-healthcare-supply-chain/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/publications/renewable-energy-integration-syrian-grid/` | D | Y | Y | Y | Y | Complete | Verified bibliographic/citation fields, safe files, Dublin Core, ScholarlyArticle metadata, placeholder suppression, and tests are implemented. |
| `/research/researchers/` | D | Y | Y | Y | Y | Complete | CMS taxonomy, functional controls, exact listing/profile preview, bilingual invariants, canonical profiles, and tests are implemented. |
| `/research/themes/` | D | Y | Y | Y | Y | Complete | Bilingual theme catalog, protected preview, validated publication workflow, sitemap, and tests are implemented. |
| `/research/themes/ai-ml/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/pharmaceutical-sciences/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/clinical-medicine/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/dental-sciences/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/petroleum-engineering/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/construction-engineering/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/business-administration/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/medical-education/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/biomedical-engineering/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/energy-systems/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/data-science/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/themes/structural-engineering/` | D | Y | Y | Y | Y | Complete | Published theme catalog drives detail relationships, preview, SEO, sitemap, and tests. |
| `/research/expert-finder/` | D | Y | Y | Y | Y | Complete | CMS-backed search and faculty filtering, pagination, reset/empty state, canonical profiles, and tests are implemented. |
| `/research/conferences/` | D | Y | Y | Y | Y | Complete | Verified proceedings/registration destinations, transparent unavailable states, bilingual CMS workflow, preview, and tests are implemented. |
| `/research/conferences/register/` | D | Y | Y | Y | Y | Complete | Accessible program/speaker/topic content and server-validated event-bound submissions with authorized review are implemented. |
| `/research/library/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed resource page. |
| `/research/policies/` | D | Y | Y | Y | Y | Complete | Reviewed Media Library or safe HTTPS policy documents, transparent unavailable states, CMS readiness, preview, and tests are implemented. |
| `/research/office/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed office page. |

## Campus Life

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/campus-life/` | D | Y | Y | Y | Y | Complete | Unverified figures and inert portals are removed; bilingual CMS guidance, safe destinations, assets, and tests are implemented. |
| `/campus-life/services/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed static service directory. |
| `/campus-life/transport/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed transport content. |
| `/campus-life/clubs-activities/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed clubs content. |
| `/campus-life/career-development/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed career content. |
| `/campus-life/career-development/jobs/` | D | Y | Y | Y | Y | Complete | Bilingual CMS catalog, filtering, pagination, open/closed enforcement, protected preview, and tests are implemented. |
| `/campus-life/career-development/jobs/apply/` | D | Y | Y | Y | Y | Complete | Published open-job context is validated, displayed, locale-preserved, persisted with private CV storage, and exposed to authorized review. |
| `/campus-life/career-development/jobs/lecturer-computer-science/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/research-assistant/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/administrative-coordinator/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/admissions-officer/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/campus-bus-driver/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/it-support-specialist/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/laboratory-technician/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/career-development/jobs/dental-clinic-supervisor/` | D | Y | Y | Y | Y | Complete | CMS detail, dates/status, related jobs, canonical sharing, JobPosting metadata, preview, and tests are implemented. |
| `/campus-life/dental/` | D | Y | Y | Y | Y | Complete | Dedicated date-aware CMS-backed clinic page. |
| `/campus-life/hospital/` | D | Y | Y | Y | Y | Complete | Dedicated date-aware CMS-backed hospital page. |
| `/campus-life/health-insurance/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed insurance content. |
| `/campus-life/damascus-research-pub/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed content. |
| `/campus-life/rules-regulations/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed content. |
| `/campus-life/general-rules/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed content. |
| `/campus-life/exam-instructions/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed content. |
| `/campus-life/exam-penalties/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed content. |

## Virtual Tour

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/virtual-tour/` | D | Y | Y | Y | Y | Complete | CMS-managed photo scenes, accessible scene switching, pan/zoom, hotspots, autoplay, thumbnails, fullscreen fallback, reduced motion, and tests are implemented. |

## E-Services

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/e-services/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual CMS-backed gateway. |
| `/e-services/suggestions-complaints/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual CMS workflow, secure throttled submission, private validated attachments, consent/context, admin review/download, and tests are implemented. |
| `/e-services/library/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual CMS workflow, verified open-resource links, safe external-link handling, localized SEO/structured data, sitemap, aliases, and tests are implemented. |
| `/e-services/staff-email/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual guidance page, protected preview/publication workflow, credential-safety guidance, internal support path, SEO, sitemap, aliases, and tests are implemented. |
| `/e-services/it-support/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual support guidance, validated contact-topic flow, CMS workflow, localized SEO/structured data, sitemap, aliases, and tests are implemented. |

## News And Events

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/news/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual News gateway. |
| `/news/articles/` | D | Y | Y | Y | Y | Complete | CMS-managed bilingual shell, working listing controls/pagination, protected preview, canonical article sharing, and tests are implemented. |
| `/news/article/` | R | P | Y | Y | Y | Redirect | Reference `?id=` links resolve to canonical published article URLs. |
| `/news/announcements/` | D | Y | Y | Y | Y | Complete | Dedicated filtered/paginated CMS-backed page. |
| `/news/events/` | D | Y | Y | Y | Y | Complete | Dedicated functional monthly calendar. |
| `/news/events-list/` | D | Y | Y | Y | Y | Complete | Dedicated event catalog and filters. |
| `/news/events-list/register/` | D | Y | Y | Y | Y | Complete | Event-bound validated registration flow. |
| `/news/events-list/past/` | D | Y | Y | Y | Y | Complete | Query-selected past-event details and gallery. |
| `/news/gallery/` | D | Y | Y | Y | Y | Complete | CMS-curated filtering, pagination, and accessible viewer. |

## Contact

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/contact/` | D | Y | Y | Y | Y | Complete | Dedicated validated contact flow and CMS workflow. |

## Facilities And Faculties

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/facilities/` | D | Y | Y | Y | Y | Complete | Catalog and reference `?id=` selectors resolve to canonical faculty URLs. |
| `/facilities/artificial-intelligence/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/business-administration/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/building-construction-engineering/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/dentistry/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/medicine/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/petroleum/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/pharmacy/overview/` | D | Y | Y | Y | Y | Complete | CMS overview, eligible central research, canonical profile linking, AR/EN rendering, and tests are implemented. |
| `/facilities/artificial-intelligence/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/business-administration/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/building-construction-engineering/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/dentistry/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/medicine/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/petroleum/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/pharmacy/departments/` | D | Y | Y | Y | Y | Complete | Bilingual CMS rendering, study-plan links, canonical/hreflang, and exact deep-link continuity are tested. |
| `/facilities/artificial-intelligence/labs/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, full-collection detail lookup, related labs, locale query preservation, and tests are implemented. |
| `/facilities/building-construction-engineering/labs/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, full-collection detail lookup, related labs, locale query preservation, and tests are implemented. |
| `/facilities/dentistry/labs/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, full-collection detail lookup, related labs, locale query preservation, and tests are implemented. |
| `/facilities/medicine/labs/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, full-collection detail lookup, related labs, locale query preservation, and tests are implemented. |
| `/facilities/petroleum/labs/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, full-collection detail lookup, related labs, locale query preservation, and tests are implemented. |
| `/facilities/pharmacy/labs/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, full-collection detail lookup, related labs, locale query preservation, and tests are implemented. |
| `/facilities/pharmacy/training/` | D | Y | Y | Y | Y | Complete | Structured Pharmacy-only bilingual editor, full preview/publication lifecycle, scoped authorization, rendering, and tests are implemented. |
| `/facilities/artificial-intelligence/projects/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, bounded pages, query-preserving controls, localized links, and tests are implemented. |
| `/facilities/business-administration/projects/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, bounded pages, query-preserving controls, localized links, and tests are implemented. |
| `/facilities/building-construction-engineering/projects/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, bounded pages, query-preserving controls, localized links, and tests are implemented. |
| `/facilities/dentistry/projects/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, bounded pages, query-preserving controls, localized links, and tests are implemented. |
| `/facilities/medicine/projects/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, bounded pages, query-preserving controls, localized links, and tests are implemented. |
| `/facilities/pharmacy/projects/` | D | Y | Y | Y | Y | Complete | Six-item server pagination, bounded pages, query-preserving controls, localized links, and tests are implemented. |
| `/facilities/artificial-intelligence/research/` | D | Y | Y | Y | Y | Complete | Localized central-publication listing, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/business-administration/research/` | D | Y | Y | Y | Y | Complete | Localized central-publication listing, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/building-construction-engineering/research/` | D | Y | Y | Y | Y | Complete | Localized central-publication listing, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/dentistry/research/` | D | Y | Y | Y | Y | Complete | Localized central-publication listing, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/medicine/research/` | D | Y | Y | Y | Y | Complete | Localized central-publication listing, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/petroleum/research/` | D | Y | Y | Y | Y | Complete | Two localized reference publications, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/pharmacy/research/` | D | Y | Y | Y | Y | Complete | Localized central-publication listing, faculty-scoped CMS workflow, canonical detail links, SEO/sitemap, continuity, and server pagination are implemented. |
| `/facilities/artificial-intelligence/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/business-administration/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/building-construction-engineering/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/dentistry/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/medicine/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/petroleum/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/pharmacy/study-plan/` | D | Y | Y | Y | Y | Complete | Accessible dialog/focus behavior, keyboard graph controls, localized labels, RTL/reduced-motion handling, and tests are implemented. |
| `/facilities/artificial-intelligence/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/business-administration/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/building-construction-engineering/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/dentistry/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/medicine/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/petroleum/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/pharmacy/study-plan/course/` | D | Y | Y | Y | Y | Complete | Service-validated selectors, safe materials/profiles, query-preserving locale/SEO links, AR/EN rendering, and tests are implemented. |
| `/facilities/artificial-intelligence/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/business-administration/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/building-construction-engineering/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/dentistry/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/medicine/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/petroleum/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/pharmacy/alumni/` | D | Y | Y | Y | Y | Complete | Twelve-item directory pagination, validated filters, managed media, AR/EN query preservation, and tests are implemented. |
| `/facilities/artificial-intelligence/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |
| `/facilities/business-administration/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |
| `/facilities/building-construction-engineering/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |
| `/facilities/dentistry/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |
| `/facilities/medicine/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |
| `/facilities/petroleum/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |
| `/facilities/pharmacy/valedictorians/` | D | Y | Y | Y | Y | Complete | Six-item pagination, managed quote/media behavior, AR/EN query preservation, and tests are implemented. |

## Shared Project Detail

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/projects/detail/` | R | P | Y | P | Y | Redirect | Reference `?id=` selectors resolve to canonical faculty project details. |

## Cross-Cutting Route Gaps

| Gap | Impact |
|---|---|
| Unprefixed deep links | Approved reference section paths now negotiate browser locale and preserve path/query state. |
| Physical `.html` aliases | All 175 exact reference HTML files have locale-aware canonical mappings with query preservation; broader old-production URLs remain a separate inventory. |
| Generic catch-all | A generic `PageController` 200 does not establish specialized parity; `/events` is the confirmed example. |
| Orphan research detail | `/research/detail/?id=...` exists outside the 175-page registry and requires a separate continuity decision. |
| Query compatibility | Profile, article, facilities hub, and shared project detail are resolved; selected-job context and some course/lab flows remain. |

## Verified Priority Gaps

This is a historical backlog snapshot and is superseded for current execution by
`Docs/CURRENT_REMEDIATION_EXECUTION_CHECKLIST.md`.

| Priority | Slice |
|---|---|
| P1 | Implement the remaining missing module pages. |
| P1 | Implement seven faculty research pages. |
| P1 | Restore Research filters, CMS coverage, downloads, pagination, and scholarly metadata. |
| P1 | Restore Campus Life job filtering, selected-job context, sharing, related jobs, and CMS coverage. |
| P1 | Implement the interactive CMS-managed Virtual Tour. |
