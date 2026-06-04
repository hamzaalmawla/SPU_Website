<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Components\KeyValue;
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

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 11;

    private static function auditService(): \App\Contracts\AuditServiceInterface
    {
        return app(\App\Contracts\AuditServiceInterface::class);
    }

    public static function canAccess(): bool
    {
        return Gate::allows('view-audit-log');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Audit Entry')->schema([
                TextInput::make('action')
                    ->label('Action')
                    ->disabled(),

                TextInput::make('entity_type')
                    ->label('Entity Type')
                    ->disabled(),

                TextInput::make('entity_id')
                    ->label('Entity ID')
                    ->disabled(),

                TextInput::make('user.name')
                    ->label('User')
                    ->disabled(),

                TextInput::make('ip_address')
                    ->label('IP Address')
                    ->disabled(),

                TextInput::make('user_agent')
                    ->label('User Agent')
                    ->disabled(),

                TextInput::make('created_at')
                    ->label('Timestamp')
                    ->disabled(),
            ])->columns(2),

            Section::make('Metadata')->schema([
                KeyValue::make('metadata')
                    ->label('Full Metadata')
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
                    ->label('Action')
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
                    ->label('Entity Type')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('System')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options(fn (): array => self::auditService()->distinctActions()),

                SelectFilter::make('entity_type')
                    ->label('Entity Type')
                    ->options(fn (): array => self::auditService()->distinctEntityTypes()),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable(),

                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until'),
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
