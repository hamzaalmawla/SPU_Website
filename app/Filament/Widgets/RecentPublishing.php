<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Contracts\Dashboard\AdminDashboardServiceInterface;
use App\Filament\Resources\AuditLogResource;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/** The latest pages published or taken down, and by whom. */
final class RecentPublishing extends Widget
{
    protected static ?int $sort = 7;

    protected static string $view = 'filament.widgets.recent-publishing';

    public static function canView(): bool
    {
        return Gate::allows('audit_log.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'rows' => $user instanceof Authenticatable ? app(AdminDashboardServiceInterface::class)->recentPublishing($user, 8) : collect(),
            'auditUrl' => AuditLogResource::canViewAny() ? AuditLogResource::getUrl() : null,
        ];
    }
}
