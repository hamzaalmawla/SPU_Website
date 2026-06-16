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
 * No create or delete actions — soft-delete is handled via lock.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-users');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('User Details')->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Select::make('role_slug')
                    ->label('Role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'editor' => 'Editor',
                        'faculty_editor' => 'Faculty Editor',
                    ])
                    ->required(),

                TextInput::make('faculty_scope_slug')
                    ->label('Faculty Scope Slug')
                    ->maxLength(255)
                    ->placeholder('Only for faculty_editor role')
                    ->helperText('Restricts content access to a specific faculty.'),
            ])->columns(2),

            Section::make('Account Status')->schema([
                Toggle::make('is_locked')
                    ->label('Account Locked')
                    ->helperText('Locked accounts cannot log in.'),
            ]),

            Section::make('Password Reset')->schema([
                TextInput::make('password')
                    ->label('New Password')
                    ->password()
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role_slug')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'editor' => 'warning',
                        'faculty_editor' => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
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
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
