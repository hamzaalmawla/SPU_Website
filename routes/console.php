<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune')->dailyAt('02:30')->withoutOverlapping();

// Visitor hashes only count a visitor once a day; the dashboard keeps daily totals.
Schedule::command('site-stats:prune')->dailyAt('02:35')->withoutOverlapping();

// Page views are queued by public/stats-hit.php (no framework boot per view) and
// written here once a minute.
Schedule::command('site-stats:collect')->everyMinute()->withoutOverlapping();

// Abandoned admin uploads stay in public media-tmp/ until removed.
Schedule::command('media:prune-temp')->dailyAt('02:45')->withoutOverlapping();
Schedule::command('content:publish-scheduled')->everyMinute()->withoutOverlapping();

// The sitemap is served from pre-generated files in public/. Publishing marks
// them stale; this picks that up without the publish request paying for a
// full regeneration, and skips the work entirely when nothing changed.
Schedule::command('sitemap:generate')->everyFiveMinutes()->withoutOverlapping();

// The search index is kept live by model observers, but indirect changes — a
// faculty being disabled, or an importer run with model events off — can leave
// it behind. A nightly rebuild reconciles it; it is idempotent by design.
Schedule::command('search:index')->dailyAt('03:10')->withoutOverlapping();

// News and uploads published between deploys need their web-sized variants too.
// Existing variants are reused, so a run with nothing new only reads a manifest.
Schedule::command('media:generate-legacy-derivatives')->hourlyAt(20)->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Legacy Import Commands
|--------------------------------------------------------------------------
|
| Legacy import report/audit/export commands live in app/Console/Commands.
| Keep this file limited to scheduling and tiny framework-provided commands.
|
*/
