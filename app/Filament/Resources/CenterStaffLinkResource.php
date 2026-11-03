<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\Filament\Resources\CenterStaffLinkResource\Pages;
use App\Models\Staff\StaffIntakeLink;
use Illuminate\Database\Eloquent\Builder;

/**
 * Links sent to a center's staff (config/centers.php): the faculty staff
 * links, with the center in place of the faculty. Approved details show on
 * the center's staff page. Needs center_staff_intake (super admins for now).
 */
class CenterStaffLinkResource extends StaffIntakeLinkResource
{
    protected static ?string $slug = 'center-staff-links';

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('staff_intake.center.link');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff_intake.center.links');
    }

    public static function getNavigationLabel(): string
    {
        return __('staff_intake.center.navigation_links');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.centers');
    }

    public static function canAccess(): bool
    {
        return app(StaffIntakeLinkServiceInterface::class)->canUseCenters((int) auth()->id());
    }

    public static function getEloquentQuery(): Builder
    {
        return StaffIntakeLink::query()->with(['creator'])->whereNotNull('center_slug');
    }

    public static function unitLabel(): string
    {
        return __('staff_intake.center.center');
    }

    public static function unitName(StaffIntakeLink $record): string
    {
        return (string) (app(CenterCatalogServiceInterface::class)->centerName((string) $record->center_slug, app()->getLocale()) ?? $record->center_slug);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCenterStaffLinks::route('/'),
            'view' => Pages\ViewCenterStaffLink::route('/{record}'),
        ];
    }
}
