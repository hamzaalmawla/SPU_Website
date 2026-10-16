<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * Filament resource for managing admin user accounts (super_admin only).
 *
 * All business logic is delegated to AuthServiceInterface.
 * This resource provides only the UI layer.
 * No delete action — soft-delete is handled via lock.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-users');
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.user.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.user.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.users');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin_resources.user.user_details'))->schema([
                TextInput::make('name')
                    ->label(__('admin_resources.user.name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label(__('admin_resources.user.email'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('role_slug')
                    ->label(__('admin_resources.user.role'))
                    ->options([
                        'super_admin' => __('admin_resources.user.super_admin'),
                        'editor' => __('admin_resources.user.editor'),
                        'faculty_editor' => __('admin_resources.user.faculty_editor'),
                    ])
                    ->required(),

                TextInput::make('faculty_scope_slug')
                    ->label(__('admin_resources.user.faculty_scope_slug'))
                    ->maxLength(255)
                    ->placeholder(__('admin_resources.user.only_for_faculty_editor_role'))
                    ->helperText(__('admin_resources.user.restricts_content_access_to_a_specific_faculty')),
            ])->columns(2),

            Section::make(__('admin_resources.user.account_status'))->schema([
                Toggle::make('is_locked')
                    ->label(__('admin_resources.user.account_locked'))
                    // Show the live state: an automatic lock that has run out
                    // still has is_locked set until the next sign-in clears it.
                    ->afterStateHydrated(static function (Toggle $component, ?User $record): void {
                        if ($record instanceof User) {
                            $component->state($record->isAccountLocked());
                        }
                    })
                    ->helperText(static function (?User $record): string {
                        if (! $record instanceof User || ! $record->isAccountLocked()) {
                            return __('admin_resources.user.locked_accounts_cannot_log_in');
                        }

                        return $record->locked_until !== null
                            ? __('admin_resources.user.locked_until', [
                                'time' => $record->locked_until->timezone((string) config('app.timezone'))->translatedFormat(__('admin.formats.date_time')),
                            ])
                            : __('admin_resources.user.locked_manually');
                    }),

                Toggle::make('two_factor_enabled')
                    ->label(__('admin_resources.user.two_factor_authentication'))
                    ->helperText(__('admin_resources.user.turn_off_two_factor_authentication_for_this_account_it_must'))
                    ->disabled(fn (bool $state): bool => ! $state)
                    ->dehydrated(),
            ]),

            Section::make(__('admin_resources.user.password_reset'))->schema([
                TextInput::make('password')
                    ->label(__('admin_resources.user.new_password'))
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(__('admin_resources.user.leave_blank_to_keep_the_current_password')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin_resources.user.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('admin_resources.user.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role_slug')
                    ->label(__('admin_resources.user.role'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'editor' => 'warning',
                        'faculty_editor' => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('is_locked')
                    ->label(__('admin_resources.user.locked'))
                    ->getStateUsing(static fn (User $record): bool => $record->isAccountLocked())
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('last_login_at')
                    ->label(__('admin_resources.user.last_login'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('admin_resources.user.never')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
