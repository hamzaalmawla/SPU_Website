<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\Contracts\SiteStats\SiteStatsReportInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/** Today's visitors, page views, people on the site and people who asked the AI assistant. */
final class SiteTrafficOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('site_stats.view');
    }

    protected function getHeading(): ?string
    {
        return __('admin_dashboard.traffic.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin_dashboard.traffic.description');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $traffic = app(SiteStatsReportInterface::class)->traffic(14);
        $lastTwoWeeks = array_slice($traffic->days, -14);

        return [
            $this->compared(
                Stat::make(__('admin_dashboard.traffic.visitors_today'), number_format($traffic->visitorsToday)),
                $traffic->visitorsToday,
                $traffic->visitorsYesterday,
            )->chart(array_column($lastTwoWeeks, 'visitors')),
            $this->compared(
                Stat::make(__('admin_dashboard.traffic.page_views_today'), number_format($traffic->pageViewsToday)),
                $traffic->pageViewsToday,
                $traffic->pageViewsYesterday,
            )->chart(array_column($lastTwoWeeks, 'pageViews')),
            Stat::make(__('admin_dashboard.traffic.online_now'), number_format($traffic->onlineNow))
                ->description(__('admin_dashboard.traffic.online_hint', ['minutes' => (int) config('site_stats.online_minutes', 5)]))
                ->descriptionIcon('heroicon-m-signal')
                ->color($traffic->onlineNow > 0 ? 'success' : 'gray'),
            Stat::make(__('admin_dashboard.traffic.chatbot_today'), number_format($traffic->chatbotVisitorsToday))
                ->description(__('admin_dashboard.traffic.chatbot_hint', ['questions' => number_format(app(ChatbotUsageServiceInterface::class)->questionsToday())]))
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),
        ];
    }

    private function compared(Stat $stat, int $today, int $yesterday): Stat
    {
        if ($today === $yesterday) {
            return $stat->description(__('admin_dashboard.traffic.same_as_yesterday'))->color('gray');
        }

        if ($yesterday === 0) {
            return $stat->description(__('admin_dashboard.traffic.yesterday', ['count' => 0]))->color('gray');
        }

        $change = (int) round(($today - $yesterday) / $yesterday * 100);

        return $stat
            ->description(__('admin_dashboard.traffic.vs_yesterday', ['change' => ($change > 0 ? '+' : '').$change.'%', 'count' => number_format($yesterday)]))
            ->descriptionIcon($today > $yesterday ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($today > $yesterday ? 'success' : 'warning');
    }
}
