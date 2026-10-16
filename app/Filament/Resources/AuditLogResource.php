<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Shared\AuditServiceInterface;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Filament\Support\JsonEditor;
use App\Models\Shared\AuditLog;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only Filament resource for viewing audit log entries (super_admin only).
 *
 * No create, edit, or delete actions.
 * This resource provides only the UI layer.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 11;

    private static function auditService(): AuditServiceInterface
    {
        return app(AuditServiceInterface::class);
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view-audit-log');
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.audit_log.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.audit_log.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.audit_logs');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin_resources.audit_log.audit_entry'))->schema([
                TextInput::make('action')
                    ->label(__('admin_resources.audit_log.action'))
                    ->disabled(),

                TextInput::make('entity_type')
                    ->label(__('admin_resources.audit_log.entity_type'))
                    ->disabled(),

                TextInput::make('entity_id')
                    ->label(__('admin_resources.audit_log.entity_id'))
                    ->disabled(),

                TextInput::make('user.name')
                    ->label(__('admin_resources.audit_log.user'))
                    ->disabled(),

                TextInput::make('ip_address')
                    ->label(__('admin_resources.audit_log.ip_address'))
                    ->disabled(),

                TextInput::make('user_agent')
                    ->label(__('admin_resources.audit_log.user_agent'))
                    ->disabled(),

                TextInput::make('created_at')
                    ->label(__('admin_resources.audit_log.timestamp'))
                    ->disabled(),
            ])->columns(2),

            Section::make(__('admin_resources.audit_log.metadata'))->schema([
                // Metadata nests (changed_fields, payload snapshots); KeyValue
                // only renders flat string pairs.
                JsonEditor::make('metadata')
                    ->label(__('admin_resources.audit_log.full_metadata'))
                    ->disabled()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->label(__('admin_resources.audit_log.action'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'login') => 'info',
                        str_contains($state, 'locked') => 'danger',
                        str_contains($state, 'publish') => 'success',
                        str_contains($state, 'delete') || str_contains($state, 'unpublish') => 'danger',
                        str_contains($state, 'update') || str_contains($state, 'save') => 'warning',
                        str_contains($state, 'create') => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('entity_type')
                    ->label(__('admin_resources.audit_log.entity_type'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label(__('admin_resources.audit_log.user'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'user',
                        fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder(__('admin_resources.audit_log.system'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin_resources.audit_log.timestamp'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('admin_resources.audit_log.action'))
                    ->options(fn (): array => self::auditService()->distinctActions()),

                SelectFilter::make('entity_type')
                    ->label(__('admin_resources.audit_log.entity_type'))
                    ->options(fn (): array => self::auditService()->distinctEntityTypes()),

                SelectFilter::make('user_id')
                    ->label(__('admin_resources.audit_log.user'))
                    ->relationship('user', 'name')
                    ->searchable(),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')
                            ->label(__('admin_resources.audit_log.from')),
                        DatePicker::make('until')
                            ->label(__('admin_resources.audit_log.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}
