<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Staff\StaffIntakeLinkServiceInterface;
use App\DTOs\Staff\StaffIntakeLinkCreatedDTO;
use App\Filament\Resources\StaffIntakeLinkResource\Pages;
use App\Filament\Resources\StaffIntakeLinkResource\RelationManagers\AccessLogsRelationManager;
use App\Models\Staff\StaffIntakeLink;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The links sent to each faculty's academic staff so they can send in their
 * own profile details. A faculty editor sees and makes links for their own
 * faculties only.
 */
class StaffIntakeLinkResource extends Resource
{
    protected static ?string $model = StaffIntakeLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('staff_intake.admin.link');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff_intake.admin.links');
    }

    public static function getNavigationLabel(): string
    {
        return __('staff_intake.admin.navigation_links');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['faculty.translations', 'creator'])
            ->whereIn('faculty_id', self::facultyIds());
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('faculty')->label(__('staff_intake.admin.columns.faculty'))->state(fn (StaffIntakeLink $record): string => self::facultyName($record)),
            TextEntry::make('label')->label(__('staff_intake.admin.columns.label'))->placeholder('—'),
            TextEntry::make('status')->label(__('staff_intake.admin.columns.status'))->state(fn (StaffIntakeLink $record): string => __('staff_intake.admin.link_status.'.self::status($record)))->badge()->color(fn (StaffIntakeLink $record): string => self::status($record) === 'active' ? 'success' : 'gray'),
            TextEntry::make('submissions')->label(__('staff_intake.admin.columns.submissions'))->state(fn (StaffIntakeLink $record): string => self::submissions($record)),
            TextEntry::make('expires_at')->label(__('staff_intake.admin.columns.expires_at'))->dateTime()->placeholder('—'),
            TextEntry::make('creator.name')->label(__('staff_intake.admin.columns.created_by'))->placeholder('—'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('faculty')->label(__('staff_intake.admin.columns.faculty'))->state(fn (StaffIntakeLink $record): string => self::facultyName($record)),
                TextColumn::make('label')->label(__('staff_intake.admin.columns.label'))->placeholder('—')->searchable()->wrap(),
                TextColumn::make('status')
                    ->label(__('staff_intake.admin.columns.status'))
                    ->state(fn (StaffIntakeLink $record): string => __('staff_intake.admin.link_status.'.self::status($record)))
                    ->badge()
                    ->color(fn (StaffIntakeLink $record): string => self::status($record) === 'active' ? 'success' : 'gray'),
                TextColumn::make('submissions')->label(__('staff_intake.admin.columns.submissions'))->state(fn (StaffIntakeLink $record): string => self::submissions($record)),
                TextColumn::make('expires_at')->label(__('staff_intake.admin.columns.expires_at'))->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('creator.name')->label(__('staff_intake.admin.columns.created_by'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('staff_intake.admin.columns.created_at'))->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('show_link')
                    ->label(__('staff_intake.admin.actions.show_link'))
                    ->icon('heroicon-o-clipboard-document')
                    ->visible(fn (StaffIntakeLink $record): bool => $record->revoked_at === null && self::canManage($record))
                    ->action(function (StaffIntakeLink $record): void {
                        $address = app(StaffIntakeLinkServiceInterface::class)->address((int) $record->getKey(), (int) auth()->id());

                        if ($address === null) {
                            Notification::make()->title(__('staff_intake.admin.notifications.link_unknown'))->warning()->persistent()->send();

                            return;
                        }

                        self::notifyLink($address, __('staff_intake.admin.notifications.link_shown'));
                    }),
                Tables\Actions\ViewAction::make()->label(__('staff_intake.admin.actions.access_log')),
                Tables\Actions\Action::make('rotate')
                    ->label(__('staff_intake.admin.actions.rotate'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(__('staff_intake.admin.actions.rotate_confirm'))
                    ->visible(fn (StaffIntakeLink $record): bool => $record->revoked_at === null && self::canManage($record))
                    ->action(function (StaffIntakeLink $record): void {
                        self::notifyLink(
                            app(StaffIntakeLinkServiceInterface::class)->rotate((int) $record->getKey(), (int) auth()->id()),
                            __('staff_intake.admin.notifications.link_rotated'),
                        );
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label(__('staff_intake.admin.actions.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('staff_intake.admin.actions.revoke_confirm'))
                    ->visible(fn (StaffIntakeLink $record): bool => $record->revoked_at === null && self::canManage($record))
                    ->action(function (StaffIntakeLink $record): void {
                        app(StaffIntakeLinkServiceInterface::class)->revoke((int) $record->getKey(), (int) auth()->id());
                        Notification::make()->title(__('staff_intake.admin.notifications.link_revoked'))->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [AccessLogsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffIntakeLinks::route('/'),
            'view' => Pages\ViewStaffIntakeLink::route('/{record}'),
        ];
    }

    /** Shows a new link's addresses once, in a notification that stays until closed. */
    public static function notifyLink(StaffIntakeLinkCreatedDTO $created, string $title): void
    {
        Notification::make()
            ->title($title)
            ->body(nl2br(e(__('staff_intake.admin.notifications.link_body', ['ar' => $created->urlAr, 'en' => $created->urlEn]))))
            ->success()
            ->persistent()
            ->send();
    }

    /** @return list<int> */
    private static function facultyIds(): array
    {
        return array_map('intval', array_keys(app(StaffIntakeLinkServiceInterface::class)->facultyOptions((int) auth()->id())));
    }

    private static function canManage(StaffIntakeLink $record): bool
    {
        return app(StaffIntakeLinkServiceInterface::class)->canManage((int) $record->getKey(), (int) auth()->id());
    }

    private static function facultyName(StaffIntakeLink $record): string
    {
        $translations = $record->faculty?->translations;

        return (string) ($translations?->firstWhere('locale', app()->getLocale())?->name ?? $translations?->first()?->name ?? $record->faculty?->slug ?? '');
    }

    private static function status(StaffIntakeLink $record): string
    {
        return match (true) {
            $record->revoked_at !== null => 'revoked',
            $record->expires_at !== null && $record->expires_at->isPast() => 'expired',
            $record->max_submissions !== null && $record->submissions_count >= $record->max_submissions => 'full',
            default => 'active',
        };
    }

    private static function submissions(StaffIntakeLink $record): string
    {
        return $record->submissions_count.($record->max_submissions !== null ? ' / '.$record->max_submissions : '');
    }
}
