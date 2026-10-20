<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Staff\StaffIntakeReviewServiceInterface;
use App\Filament\Resources\StaffIntakeRequestResource\Pages;
use App\Models\Staff\StaffIntakeRequest;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** What staff sent through intake links, waiting for a faculty editor's review. */
class StaffIntakeRequestResource extends Resource
{
    protected static ?string $model = StaffIntakeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 31;

    public static function getModelLabel(): string
    {
        return __('staff_intake.admin.request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff_intake.admin.requests');
    }

    public static function getNavigationLabel(): string
    {
        return __('staff_intake.admin.navigation_requests');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(StaffIntakeReviewServiceInterface::class)->pendingCount((int) auth()->id());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return self::facultyIds() !== [];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof StaffIntakeRequest ? (string) $record->submitter_name : self::getModelLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('faculty.translations')
            ->whereIn('faculty_id', self::facultyIds());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label(__('staff_intake.admin.columns.reference'))->searchable()->copyable(),
                TextColumn::make('submitter_name')->label(__('staff_intake.admin.columns.name'))->searchable()->wrap(),
                TextColumn::make('faculty')
                    ->label(__('staff_intake.admin.columns.faculty'))
                    ->state(fn (StaffIntakeRequest $record): string => (string) ($record->faculty?->translations->firstWhere('locale', app()->getLocale())?->name
                        ?? $record->faculty?->translations->first()?->name ?? '')),
                TextColumn::make('type')->label(__('staff_intake.admin.columns.type'))->formatStateUsing(fn (string $state): string => __('staff_intake.admin.types.'.$state))->badge()
                    ->color(fn (string $state): string => $state === StaffIntakeRequest::TYPE_CREATE ? 'info' : 'gray'),
                IconColumn::make('possible_duplicate')->label(__('staff_intake.admin.columns.possible_duplicate'))->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')->trueColor('danger')->falseIcon('heroicon-o-minus')->falseColor('gray'),
                TextColumn::make('status')->label(__('staff_intake.admin.columns.status'))->formatStateUsing(fn (string $state): string => __('staff_intake.admin.request_status.'.$state))->badge()
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('created_at')->label(__('staff_intake.admin.columns.submitted_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('staff_intake.admin.columns.status'))
                    ->options(collect([StaffIntakeRequest::STATUS_PENDING, StaffIntakeRequest::STATUS_APPROVED, StaffIntakeRequest::STATUS_REJECTED, StaffIntakeRequest::STATUS_SUPERSEDED])
                        ->mapWithKeys(fn (string $status): array => [$status => __('staff_intake.admin.request_status.'.$status)])
                        ->all())
                    ->default(StaffIntakeRequest::STATUS_PENDING),
                SelectFilter::make('type')
                    ->label(__('staff_intake.admin.columns.type'))
                    ->options([
                        StaffIntakeRequest::TYPE_UPDATE => __('staff_intake.admin.types.update'),
                        StaffIntakeRequest::TYPE_CREATE => __('staff_intake.admin.types.create'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('staff_intake.admin.actions.review')),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffIntakeRequests::route('/'),
            'view' => Pages\ViewStaffIntakeRequest::route('/{record}'),
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            StaffIntakeRequest::STATUS_PENDING => 'warning',
            StaffIntakeRequest::STATUS_APPROVED => 'success',
            StaffIntakeRequest::STATUS_REJECTED => 'danger',
            default => 'gray',
        };
    }

    /** @return list<int> */
    private static function facultyIds(): array
    {
        return app(StaffIntakeReviewServiceInterface::class)->reviewableFacultyIds((int) auth()->id());
    }
}
