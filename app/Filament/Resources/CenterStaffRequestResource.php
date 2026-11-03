<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\Filament\Resources\CenterStaffRequestResource\Pages;
use App\Models\Staff\StaffIntakeRequest;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a center's staff sent through their link, waiting for review. The
 * same review screen as the faculty requests; approving puts the profile in
 * the center (no faculty), so it shows on the center's staff page.
 */
class CenterStaffRequestResource extends StaffIntakeRequestResource
{
    protected static ?string $slug = 'center-staff-requests';

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('staff_intake.center.request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff_intake.center.requests');
    }

    public static function getNavigationLabel(): string
    {
        return __('staff_intake.center.navigation_requests');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.centers');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(StaffIntakeReviewServiceInterface::class)->pendingCenterCount((int) auth()->id());

        return $count > 0 ? (string) $count : null;
    }

    public static function canAccess(): bool
    {
        return app(StaffIntakeLinkServiceInterface::class)->canUseCenters((int) auth()->id());
    }

    public static function getEloquentQuery(): Builder
    {
        return StaffIntakeRequest::query()
            ->with(['appliedFacultyMember.translations'])
            ->whereNotNull('center_slug');
    }

    public static function unitLabel(): string
    {
        return __('staff_intake.center.center');
    }

    public static function unitName(StaffIntakeRequest $record): string
    {
        return (string) (app(CenterCatalogServiceInterface::class)->centerName((string) $record->center_slug, app()->getLocale()) ?? $record->center_slug);
    }

    public static function unitFilter(): SelectFilter
    {
        return SelectFilter::make('center_slug')
            ->label(static::unitLabel())
            ->options(fn (): array => app(CenterCatalogServiceInterface::class)->centerOptions(app()->getLocale()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCenterStaffRequests::route('/'),
            'view' => Pages\ViewCenterStaffRequest::route('/{record}'),
        ];
    }
}
