<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('content:publish-scheduled')->everyMinute()->withoutOverlapping();

// The sitemap is served from pre-generated files in public/. Publishing marks
// them stale; this picks that up without the publish request paying for a
// full regeneration, and skips the work entirely when nothing changed.
Schedule::command('sitemap:generate')->everyFiveMinutes()->withoutOverlapping();

// The search index is kept live by model observers, but indirect changes — a
// faculty being disabled, or an importer run with model events off — can leave
// it behind. A nightly rebuild reconciles it; it is idempotent by design.
Schedule::command('search:index')->dailyAt('03:10')->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Legacy Import Commands
|--------------------------------------------------------------------------
|
| Legacy import report/audit/export commands live in app/Console/Commands.
| Keep this file limited to scheduling and tiny framework-provided commands.
|
*/
