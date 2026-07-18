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

## Home

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/` | D | Y | P | Y | P | Partial | Browser-level slider, counter, reduced-motion, and responsive QA remains. |

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
| `/admissions/` | D | Y | P | Y | Y | Partial | Resource inventory and final interaction/visual parity remain incomplete. |
| `/admissions/requirements/` | D | Y | Y | Y | P | Partial | Browser keyboard/RTL interaction coverage remains. |
| `/admissions/tuition/` | D | Y | P | Y | P | Partial | Placeholder financial data and inert payment CTA remain. |
| `/admissions/faq/` | D | Y | Y | Y | P | Partial | Search/accordion browser and AR/RTL coverage remains. |
| `/admissions/how-to-apply/` | D | Y | P | Y | P | Partial | Final application CTA does not start a real application flow. |
| `/admissions/transfer/` | D | Y | Y | Y | P | Partial | Browser/AR interaction coverage remains. |
| `/admissions/calendar/` | D | Y | N | N | P | Partial | Download control points to `#`; calendar PDF is absent. |
| `/admissions/documents/` | D | Y | P | N | P | Partial | Document/download links are inert and reference tables are reduced. |
| `/admissions/study-system/` | R | NA | P | NA | Y | Redirect | Redirect does not open the intended documents tab. |
| `/admissions/academic-warnings/` | R | NA | P | NA | Y | Redirect | Redirect does not open the intended warnings tab. |
| `/admissions/filling-vacancies/` | D | Y | P | Y | Y | Partial | Missing landing link and real vacancy application flow. |
| `/admissions/graduation-exams/` | D | Y | Y | Y | P | Partial | Missing landing resource and target-specific workflow/AR tests. |

## Research

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/research/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed gateway. |
| `/research/repository/` | D | N | N | Y | P | Partial | Filters are not processed on this route and content is bundled data. |
| `/research/centers/` | D | N | Y | Y | P | Partial | Centers lack CMS publication workflow. |
| `/research/centers/ai-digital-innovation/` | D | N | Y | Y | P | Partial | Center detail is bundled JSON without CMS workflow. |
| `/research/centers/clinical-research-simulation/` | D | N | Y | Y | P | Partial | Center detail is bundled JSON without CMS workflow. |
| `/research/centers/energy-sustainable-systems/` | D | N | Y | Y | P | Partial | Center detail is bundled JSON without CMS workflow. |
| `/research/projects/` | D | N | N | Y | P | Partial | Search/status/faculty/theme controls are inert. |
| `/research/projects/earthquake-resistant-concrete-syria/` | D | N | Y | Y | P | Partial | Project detail lacks CMS workflow. |
| `/research/projects/ai-dental-diagnostics-system/` | D | N | Y | Y | P | Partial | Project detail lacks CMS workflow. |
| `/research/projects/arabic-clinical-nlp-system/` | D | N | Y | Y | P | Partial | Project detail lacks CMS workflow. |
| `/research/projects/pharmaceutical-quality-monitoring/` | D | N | Y | Y | P | Partial | Project detail lacks CMS workflow. |
| `/research/projects/reservoir-characterization-ai/` | D | N | Y | Y | P | Partial | Project detail lacks CMS workflow. |
| `/research/publications/` | D | Y | P | Y | Y | Partial | Pagination/reset/no-result parity remains. |
| `/research/publications/machine-learning-pharmaceutical-quality-control/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/ai-dental-diagnostics/` | D | Y | P | Y | Y | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/structural-analysis-earthquake-resistant-concrete/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/deep-learning-reservoir-permeability/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/clinical-simulation-training-medical-students/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/arabic-medical-record-nlp/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/business-analytics-healthcare-supply-chain/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/publications/renewable-energy-integration-syrian-grid/` | D | Y | P | Y | P | Partial | Scholarly metadata and complete citation fields are missing. |
| `/research/researchers/` | D | P | N | Y | P | Partial | Search, faculty, and expertise filters are inert. |
| `/research/themes/` | D | N | Y | Y | P | Partial | Theme catalog lacks CMS workflow. |
| `/research/themes/ai-ml/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/pharmaceutical-sciences/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/clinical-medicine/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/dental-sciences/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/petroleum-engineering/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/construction-engineering/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/business-administration/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/medical-education/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/biomedical-engineering/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/energy-systems/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/data-science/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/themes/structural-engineering/` | D | N | Y | Y | P | Partial | Theme detail lacks CMS workflow. |
| `/research/expert-finder/` | D | Y | N | Y | P | Partial | Search and faculty/expertise filters are inert. |
| `/research/conferences/` | D | Y | P | Y | P | Partial | Proceedings/download controls still use `#`. |
| `/research/conferences/register/` | D | P | P | Y | Y | Partial | Missing reference program/speakers/topics tabs and event-bound submission context. |
| `/research/library/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed resource page. |
| `/research/policies/` | D | Y | N | N | P | Partial | Policy download links are inert. |
| `/research/office/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed office page. |

## Campus Life

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/campus-life/` | D | Y | P | Y | Y | Partial | Two visible portal controls still point to `#`. |
| `/campus-life/services/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed static service directory. |
| `/campus-life/transport/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed transport content. |
| `/campus-life/clubs-activities/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed clubs content. |
| `/campus-life/career-development/` | D | Y | Y | Y | Y | Complete | Dedicated CMS-backed career content. |
| `/campus-life/career-development/jobs/` | D | N | N | Y | P | Partial | Search/category/type filters and pagination are absent. |
| `/campus-life/career-development/jobs/apply/` | D | N | P | Y | Y | Partial | `?job=` is not displayed or persisted as application context. |
| `/campus-life/career-development/jobs/lecturer-computer-science/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/research-assistant/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/administrative-coordinator/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/admissions-officer/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/campus-bus-driver/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/it-support-specialist/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/laboratory-technician/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
| `/campus-life/career-development/jobs/dental-clinic-supervisor/` | D | N | P | Y | P | Partial | Sharing, related jobs, and CMS management are missing. |
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
| `/virtual-tour/` | D | N | N | P | N | Partial | Current page is static; scene switching, panorama controls, hotspots, fullscreen, and CMS are absent. |

## E-Services

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/e-services/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual CMS-backed gateway. |
| `/e-services/suggestions-complaints/` | D | N | P | Y | Y | Partial | Attachment handling and dedicated CMS/preview workflow are absent. |
| `/e-services/library/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual CMS workflow, verified open-resource links, safe external-link handling, localized SEO/structured data, sitemap, aliases, and tests are implemented. |
| `/e-services/staff-email/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual guidance page, protected preview/publication workflow, credential-safety guidance, internal support path, SEO, sitemap, aliases, and tests are implemented. |
| `/e-services/it-support/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual support guidance, validated contact-topic flow, CMS workflow, localized SEO/structured data, sitemap, aliases, and tests are implemented. |

## News And Events

| Reference path | Route | CMS | Behavior | Assets | Tests | Overall | Primary gap |
|---|---|---|---|---|---|---|---|
| `/news/` | D | Y | Y | Y | Y | Complete | Dedicated bilingual News gateway. |
| `/news/articles/` | D | P | Y | Y | Y | Partial | Registered page-shell editor remains pending. |
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
| `/facilities/artificial-intelligence/overview/` | D | Y | P | Y | P | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/business-administration/overview/` | D | Y | P | Y | P | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/building-construction-engineering/overview/` | D | Y | P | Y | P | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/dentistry/overview/` | D | Y | P | Y | P | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/medicine/overview/` | D | Y | P | Y | Y | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/petroleum/overview/` | D | Y | P | Y | P | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/pharmacy/overview/` | D | Y | P | Y | P | Partial | Latest-research block and complete profile linking are missing. |
| `/facilities/artificial-intelligence/departments/` | D | Y | Y | Y | P | Partial | AR/public family coverage and deep-link continuity remain. |
| `/facilities/business-administration/departments/` | D | Y | Y | Y | P | Partial | AR/public family coverage and deep-link continuity remain. |
| `/facilities/building-construction-engineering/departments/` | D | Y | Y | Y | P | Partial | AR/public family coverage and deep-link continuity remain. |
| `/facilities/dentistry/departments/` | D | Y | Y | Y | P | Partial | AR/public family coverage and deep-link continuity remain. |
| `/facilities/medicine/departments/` | D | Y | Y | Y | Y | Partial | Remaining family-wide AR/browser verification. |
| `/facilities/petroleum/departments/` | D | Y | Y | Y | P | Partial | AR/public family coverage and deep-link continuity remain. |
| `/facilities/pharmacy/departments/` | D | Y | Y | Y | P | Partial | AR/public family coverage and deep-link continuity remain. |
| `/facilities/artificial-intelligence/labs/` | D | Y | P | Y | N | Partial | Six-item pagination and related-lab details are missing. |
| `/facilities/building-construction-engineering/labs/` | D | Y | P | Y | N | Partial | Six-item pagination and related-lab details are missing. |
| `/facilities/dentistry/labs/` | D | Y | P | Y | N | Partial | Six-item pagination and related-lab details are missing. |
| `/facilities/medicine/labs/` | D | Y | P | Y | N | Partial | Six-item pagination and related-lab details are missing. |
| `/facilities/petroleum/labs/` | D | Y | P | Y | N | Partial | Six-item pagination and related-lab details are missing. |
| `/facilities/pharmacy/labs/` | D | Y | P | Y | N | Partial | Six-item pagination and related-lab details are missing. |
| `/facilities/pharmacy/training/` | D | N | Y | Y | N | Partial | Registered training target is not exposed by a saveable Pharmacy editor. |
| `/facilities/artificial-intelligence/projects/` | D | Y | N | Y | P | Partial | Pagination controls are inert. |
| `/facilities/business-administration/projects/` | D | Y | N | Y | P | Partial | Pagination controls are inert. |
| `/facilities/building-construction-engineering/projects/` | D | Y | N | Y | P | Partial | Pagination controls are inert. |
| `/facilities/dentistry/projects/` | D | Y | N | Y | P | Partial | Pagination controls are inert. |
| `/facilities/medicine/projects/` | D | Y | N | Y | P | Partial | Pagination controls are inert. |
| `/facilities/pharmacy/projects/` | D | Y | N | Y | P | Partial | Pagination controls are inert. |
| `/facilities/artificial-intelligence/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/business-administration/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/building-construction-engineering/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/dentistry/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/medicine/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/petroleum/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/pharmacy/research/` | M | N | N | N | N | Missing | No route, service subpage, CMS target, view, or tests. |
| `/facilities/artificial-intelligence/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/business-administration/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/building-construction-engineering/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/dentistry/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/medicine/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/petroleum/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/pharmacy/study-plan/` | D | Y | P | Y | P | Partial | Browser accessibility and AR interaction coverage remain. |
| `/facilities/artificial-intelligence/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/business-administration/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/building-construction-engineering/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/dentistry/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/medicine/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/petroleum/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/pharmacy/study-plan/course/` | D | Y | P | P | N | Partial | Query behavior lacks HTTP/AR tests and file-resolution validation. |
| `/facilities/artificial-intelligence/alumni/` | D | Y | P | P | P | Partial | Page size differs from reference and AR/public controls need tests. |
| `/facilities/business-administration/alumni/` | D | Y | P | P | P | Partial | Page size differs from reference and AR/public controls need tests. |
| `/facilities/building-construction-engineering/alumni/` | D | Y | P | P | P | Partial | Page size differs from reference and AR/public controls need tests. |
| `/facilities/dentistry/alumni/` | D | Y | P | P | P | Partial | Page size differs from reference and AR/public controls need tests. |
| `/facilities/medicine/alumni/` | D | Y | P | P | Y | Partial | Page size differs from reference and image fallback remains generic. |
| `/facilities/petroleum/alumni/` | D | Y | P | P | P | Partial | Page size differs from reference and AR/public controls need tests. |
| `/facilities/pharmacy/alumni/` | D | Y | P | P | P | Partial | Page size differs from reference and AR/public controls need tests. |
| `/facilities/artificial-intelligence/valedictorians/` | D | Y | P | P | P | Partial | Page size, quote section, image, and AR parity differ. |
| `/facilities/business-administration/valedictorians/` | D | Y | P | P | P | Partial | Page size, quote section, image, and AR parity differ. |
| `/facilities/building-construction-engineering/valedictorians/` | D | Y | P | P | P | Partial | Page size, quote section, image, and AR parity differ. |
| `/facilities/dentistry/valedictorians/` | D | Y | P | P | P | Partial | Page size, quote section, image, and AR parity differ. |
| `/facilities/medicine/valedictorians/` | D | Y | P | P | Y | Partial | Page size, quote section, and image parity differ. |
| `/facilities/petroleum/valedictorians/` | D | Y | P | P | P | Partial | Page size, quote section, image, and AR parity differ. |
| `/facilities/pharmacy/valedictorians/` | D | Y | P | P | P | Partial | Page size, quote section, image, and AR parity differ. |

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

| Priority | Slice |
|---|---|
| P1 | Implement the remaining missing module pages. |
| P1 | Implement seven faculty research pages. |
| P1 | Restore Research filters, CMS coverage, downloads, pagination, and scholarly metadata. |
| P1 | Restore Campus Life job filtering, selected-job context, sharing, related jobs, and CMS coverage. |
| P1 | Implement the interactive CMS-managed Virtual Tour. |
