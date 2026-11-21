<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaffIntakeLinkResource\RelationManagers;

use App\Models\Staff\StaffIntakeAccessLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Who opened the link, which profile they picked, and what they changed. */
class AccessLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'accessLogs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('staff_intake.admin.actions.access_log');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('facultyMember.translations'))
            ->columns([
                TextColumn::make('created_at')->label(__('staff_intake.admin.columns.time'))->dateTime()->sortable(),
                TextColumn::make('event')->label(__('staff_intake.admin.columns.event'))->formatStateUsing(fn (string $state): string => __('staff_intake.admin.events.'.$state))->badge(),
                TextColumn::make('submitter_name')->label(__('staff_intake.admin.columns.name'))->placeholder('—')->searchable(),
                TextColumn::make('profile')
                    ->label(__('staff_intake.admin.columns.profile'))
                    ->state(fn (StaffIntakeAccessLog $record): ?string => $record->facultyMember?->translations->firstWhere('locale', app()->getLocale())?->full_name
                        ?? $record->facultyMember?->translations->first()?->full_name)
                    ->placeholder('—'),
                TextColumn::make('changed_fields')
                    ->label(__('staff_intake.admin.columns.changed_fields'))
                    ->state(fn (StaffIntakeAccessLog $record): ?string => is_array($record->changed_fields) && $record->changed_fields !== []
                        ? implode('، ', array_map(fn (mixed $field): string => __('staff_intake.fields.'.$field), $record->changed_fields))
                        : null)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('ip_address')->label(__('staff_intake.admin.columns.ip'))->placeholder('—')->searchable(),
                TextColumn::make('user_agent')->label(__('staff_intake.admin.columns.user_agent'))->placeholder('—')->limit(40)->tooltip(fn (StaffIntakeAccessLog $record): ?string => $record->user_agent),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('staff_intake.admin.columns.event'))
                    ->options(collect(StaffIntakeAccessLog::EVENTS)->mapWithKeys(fn (string $event): array => [$event => __('staff_intake.admin.events.'.$event)])->all()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
