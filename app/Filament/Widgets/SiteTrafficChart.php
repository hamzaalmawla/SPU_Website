<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\SiteStats\SiteStatsReportInterface;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** Visitors and page views per day over the last 14, 30 or 90 days. */
final class SiteTrafficChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return Gate::allows('site_stats.view');
    }

    public function getHeading(): ?string
    {
        return __('admin_dashboard.chart.heading');
    }

    public function getDescription(): ?string
    {
        $traffic = app(SiteStatsReportInterface::class)->traffic($this->days());
        $engaged = $traffic->periodVisitors > 0 ? (int) round($traffic->periodEngagedVisitors / $traffic->periodVisitors * 100) : 0;

        return __('admin_dashboard.chart.summary', [
            'visitors' => number_format($traffic->periodVisitors),
            'views' => number_format($traffic->periodPageViews),
            'percent' => $engaged,
            'chatbot' => number_format($traffic->periodChatbotVisitors),
        ]);
    }

    /** @return array<string, string> */
    protected function getFilters(): ?array
    {
        return __('admin_dashboard.chart.filters');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $series = app(SiteStatsReportInterface::class)->traffic($this->days())->days;

        return [
            'datasets' => [
                [
                    'label' => __('admin_dashboard.chart.visitors'),
                    'data' => array_column($series, 'visitors'),
                    'borderColor' => '#4f63d2',
                    'backgroundColor' => 'rgba(79, 99, 210, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => __('admin_dashboard.chart.page_views'),
                    'data' => array_column($series, 'pageViews'),
                    'borderColor' => '#caa949',
                    'backgroundColor' => 'rgba(202, 169, 73, 0.0)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(static fn (array $day): string => Carbon::parse($day['day'])->format('d/m'), $series),
        ];
    }

    private function days(): int
    {
        return in_array($this->filter, ['14', '30', '90'], true) ? (int) $this->filter : 30;
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'interaction' => ['intersect' => false, 'mode' => 'index'],
        ];
    }
}
