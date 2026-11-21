# Sunday Local Demo Runbook

## Saturday Preparation

Run these commands from the project root:

```powershell
php artisan migrate:status
npm run build
php artisan launch:validate
```

Expected state:

- all migrations show `Ran`
- Vite completes successfully
- launch validation reports zero failures
- `public/build/manifest.json` exists
- `public/storage` already exists
- the configured super-admin account is unlocked

Do not reseed or publish imported migration records before the meeting.

## Start The Demo

Use one PowerShell terminal:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-demo.ps1
```

The launcher disables debug output for the process, clears stale Laravel caches, validates the site, checks the production build, and starts the server.

To verify readiness without starting a persistent server:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-demo.ps1 -CheckOnly
```

Open:

- Arabic: `http://127.0.0.1:8000/ar`
- English: `http://127.0.0.1:8000/en`
- Admin: `http://127.0.0.1:8000/admin/login`

Use the `ADMIN_EMAIL` and `ADMIN_PASSWORD` values already configured in the local `.env`. Never display the `.env` file during the meeting.

## Suggested Presentation

1. Open the Arabic homepage and demonstrate navigation, sliders, statistics, news, research, events, facilities, and footer content.
2. Switch to English from the page header and show that locale and direction change without losing context.
3. Open About, Facilities, Admissions, Research, Campus Life, E-Services, News, Contact, and Virtual Tour.
4. On News, show the paginated public news archive at `/news/articles`, the paginated announcements archive at `/news/announcements`, and one bilingual detail page. Both lanes have a reviewed featured record.
5. Demonstrate responsive behavior using the browser device toolbar, not by resizing repeatedly during the presentation.
6. Open Admissions and one dynamic application or registration form, but do not submit personal test data in front of attendees.
7. Open `/admin/login`, sign in, and demonstrate Manage Homepage, Manage About, Manage Admissions, Manage Events, Media Library, and audit history.
8. Show preview and bilingual editing without publishing temporary meeting edits.

Avoid opening bulk News Articles, Faculty Members, Council Members, or FAQs unless explicitly explaining the migration review process. Those resources contain intentionally disabled legacy records awaiting editorial decisions.

## Protected Migration State

- `2,093` complete-text legacy records are public: `1,090` news and `1,003` announcements.
- `192` records remain disabled drafts because they have no usable Arabic article body.
- `957` imported records rely on the explicitly approved Arabic-source presentation fallback because the old English title was `Under Construction`; no English rows were synthesized.
- `9,565` attachment references remain private until their source files are retrieved and verified from cPanel; unresolved links and empty attachment sections do not render.
- `239` imported faculty profiles remain disabled drafts.
- `3` imported council members remain disabled.
- `43` imported FAQs remain disabled and unfeatured.
- `4,904` visible legacy alumni and `1,066` visible legacy honor-student records are public after explicit approval.
- `36` hidden source records remain disabled; English lists show the same public profiles and retain Arabic names where no English source name exists.
- All `35` repeated alumni/honor demo placeholders are disabled.
- Legacy student section buckets are not presented as semester labels because no authoritative first/second-semester mapping exists.
- Do not bulk publish, enable, or delete these records during the meeting.

## Meeting Laptop

- Connect the charger and disable sleep for the meeting duration.
- Close Windows Update prompts, VPN software, and unrelated development terminals.
- Use a modern Chromium browser with extensions disabled or an incognito window.
- Keep this runbook and one terminal available, but keep `.env`, database tools, and private migration exports closed.
- Test the projector resolution and browser zoom before attendees arrive.

## Quick Recovery

If a page appears stale:

```powershell
php artisan optimize:clear
```

If port `8000` is occupied:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-demo.ps1 -Port 8001
```

If the server stops, rerun the launcher. Do not migrate, reseed, import, or restore data during the meeting.
