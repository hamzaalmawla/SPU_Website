<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('content:publish-scheduled')->everyMinute()->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Legacy Import Commands
|--------------------------------------------------------------------------
|
| Legacy import report/audit/export commands live in app/Console/Commands.
| Keep this file limited to scheduling and tiny framework-provided commands.
|
*/
