<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\SiteStats\SiteStatsReportInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/** The site search terms visitors used most, and those that found nothing (30 days). */
final class SiteSearches extends Widget
{
    protected static ?int $sort = 6;

    protected static string $view = 'filament.widgets.site-searches';

    public static function canView(): bool
    {
        return Gate::allows('site_stats.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = app(SiteStatsReportInterface::class);

        return [
            'top' => $report->topSearches(30, 8),
            'missing' => $report->topSearches(30, 8, withoutResults: true),
        ];
    }
}
