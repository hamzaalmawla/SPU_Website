<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Enums\ContactMessageStatus;
use App\Models\Contact\ContactMessage;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', ContactMessage::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.contact');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.contact_messages');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
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
        return Gate::allows('delete', $record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('contact_messages.sections.message'))->schema([
                TextInput::make('reference_number')->label(__('contact_messages.fields.reference'))->disabled(),
                TextInput::make('name')->disabled(),
                TextInput::make('email')->disabled(),
                TextInput::make('subject')->disabled(),
                TextInput::make('locale')->disabled(),
                Textarea::make('message')->rows(8)->disabled()->columnSpanFull(),
            ])->columns(2),

            Section::make(__('contact_messages.sections.workflow'))->schema([
                TextInput::make('status')->label(__('contact_messages.fields.status'))->disabled(),
                TextInput::make('assignedTo.name')->label(__('contact_messages.fields.assigned_to'))->disabled(),
                Textarea::make('internal_notes')->label(__('contact_messages.fields.internal_notes'))->disabled()->rows(4)->columnSpanFull(),
                TextInput::make('email_delivery_status')->label(__('contact_messages.fields.email_delivery'))->disabled(),
                TextInput::make('ip_address')->label('IP Address')->disabled(),
                TextInput::make('user_agent')->label('User Agent')->disabled()->columnSpanFull(),
                TextInput::make('created_at')->label(__('contact_messages.fields.submitted'))->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('reference_number')
                    ->label(__('contact_messages.fields.reference'))
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('locale')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (string $state): string => __('contact_messages.statuses.'.$state))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'new' ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('read_at')
                    ->label(__('form_submissions.columns.read'))
                    ->state(fn (ContactMessage $record): string => $record->read_at ? __('form_submissions.values.read') : __('form_submissions.values.unread'))
                    ->badge()
                    ->color(fn (ContactMessage $record): string => $record->read_at ? 'gray' : 'danger'),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options(['ar' => 'Arabic', 'en' => 'English']),
                SelectFilter::make('status')
                    ->options(collect(ContactMessageStatus::cases())->mapWithKeys(fn (ContactMessageStatus $status): array => [$status->value => __('contact_messages.statuses.'.$status->value)])->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
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
            'index' => Pages\ListContactMessages::route('/'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
