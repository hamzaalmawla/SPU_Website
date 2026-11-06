<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\SiteStats\SiteStatsReportInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/** Where visitors came from, their devices and site language (30 days), and referring sites (7 days). */
final class TrafficSources extends Widget
{
    protected static ?int $sort = 5;

    protected static string $view = 'filament.widgets.traffic-sources';

    public static function canView(): bool
    {
        return Gate::allows('site_stats.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = app(SiteStatsReportInterface::class);
        $traffic = $report->traffic(30);

        return [
            'total' => $traffic->periodVisitors,
            'groups' => [
                'channels' => $traffic->sources,
                'devices' => $traffic->devices,
                'languages' => $traffic->languages,
            ],
            'referrers' => $report->topReferrers(7, 6),
        ];
    }
}
