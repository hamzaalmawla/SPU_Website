# Admin/Public Parity Matrix

This matrix compares content rendered by the public website with the fields and workflows currently available in the admin panel. A module is marked `partial` when the main page is editable but one or more rendered fields still come from Blade, JSON, or service fallbacks.

| Area | Current status | Main gap | Primary files |
| --- | --- | --- | --- |
| Homepage | partial | Footer CMS fields, section order, and event CTA are now wired to the public shell. Remaining gaps include other shared UI copy and any content intentionally sourced from global settings. | `resources/views/public/layout/footer.blade.php`, `resources/views/public/home.blade.php`, `resources/views/public/home/sections/events-activities.blade.php` |
| About | partial | Partnership goals and leadership presentation copy are now structured CMS content and seeded into the existing About records. Generic fallback handling and some shared profile UI copy still need cleanup. People, directorates, and partnership records use separate resources. | `app/Filament/Pages/ManageAbout.php`, `resources/views/public/about/partnerships.blade.php`, `resources/views/public/about/leadership.blade.php`, `database/migrations/2026_08_06_000001_seed_partnership_goal_content.php` |
| Admissions | partial | Landing SEO title/description/image are now editable. Unsupported target fallback remains possible; simple pages expose only SEO description. | `app/Filament/Pages/ManageAdmissions.php`, `app/Services/Page/AdmissionsPageService.php` |
| Campus Life | partial | Landing SEO title/description/image are now editable. The generic pending-schema branch remains a safety net; landing eyebrow is not CMS data. | `app/Filament/Pages/ManageCampusLife.php`, `app/Services/Page/CampusLifePageService.php` |
| Virtual Tour | managed | Structured CMS editor covers hero, scenes, hotspots, highlights, facilities, and SEO. | `app/Filament/Pages/ManageCampusLife.php`, `app/Services/Page/VirtualTourPageService.php` |
| E-Services | partial | Main content and SEO are editable, but breadcrumb, image-alt, and several UI labels remain template copy. | `app/Filament/Pages/ManageEServicesPage.php`, `resources/views/public/e-services*.blade.php` |
| Contact | partial | Core content and SEO are editable, but several icons, date text, image alt text, and topic subjects are fixed in code. | `app/Filament/Pages/ManageContactPage.php`, `app/Services/Page/ContactPageService.php` |
| News | partial | Dedicated article/event/announcement workflows exist, but most listing-page SEO fields are absent and unsupported direct targets have a pending path. | `app/Filament/Pages/ManageNews.php`, `app/Filament/Pages/ManageEvents.php`, `app/Filament/Resources/NewsArticleResource.php` |
| Research | partial | Page SEO is derived; publication and researcher details are primarily record/JSON driven rather than fully editable in `ManageResearch`. | `app/Filament/Pages/ManageResearch.php`, `app/Services/Research/ResearchPageService.php` |
| Facilities / faculties | partial | Hub and most faculty/subpage SEO fields are derived; project detail data still has JSON-only fields; study plans use a separate workspace. | `app/Filament/Pages/ManageFacilities.php`, `app/Filament/Concerns/ManagesFacultyHomepage.php`, `app/Services/Page/FacultyPageService.php` |

## Priority Order

1. Make every homepage field authoritative by the homepage CMS payload.
2. Move About partnership and leadership presentation copy into structured CMS payloads.
3. Add editable SEO groups to Admissions and Campus Life targets.
4. Add missing listing-page SEO and detail editors for News and Research.
5. Replace faculty project JSON-only fields with managed structured records.
6. Decide which shared UI labels, accessibility text, and form-topic presets should be global settings versus page content, then expose them consistently.

## Working Rule

Every public field identified here must end in one of three states: editable through an admin resource/page, explicitly defined as global settings, or documented as immutable product UI. It must not silently remain in a public Blade template or fallback array.
