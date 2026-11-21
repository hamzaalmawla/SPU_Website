<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\SiteStats\SiteStatsReportInterface;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/** The most viewed public pages of the last 7 days. */
final class TopPages extends Widget
{
    protected static ?int $sort = 4;

    protected static string $view = 'filament.widgets.top-pages';

    public static function canView(): bool
    {
        return Gate::allows('site_stats.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['pages' => app(SiteStatsReportInterface::class)->topPages(7, 10)];
    }
}
