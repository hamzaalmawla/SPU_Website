<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\PermissionCatalogInterface;
use App\Contracts\Auth\RoleAdminServiceInterface;
use App\Filament\Resources\RoleResource\Pages;
use App\Models\User\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Super-admin screen for roles: named sets of permissions, one checkbox list
 * per permission group. Writes go through RoleAdminServiceInterface.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Gate::allows('manage-users');
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess() && $record->getAttribute('slug') !== 'super_admin';
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('admin_permissions.role.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_permissions.role.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_permissions.role.plural_model');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('admin_permissions.role.name'))
                    ->required()
                    ->maxLength(100),
                TextInput::make('slug')
                    ->label(__('admin_permissions.role.slug'))
                    ->helperText(__('admin_permissions.role.slug_help'))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->regex('/^[a-z][a-z0-9_]{1,49}$/')
                    ->disabledOn('edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
            ])->columns(2),

            Section::make(__('admin_permissions.role.permissions'))
                ->description(__('admin_permissions.role.permissions_help'))
                ->schema(self::permissionFields()),
        ]);
    }

    /**
     * One checkbox list per permission group (`permissions_site_pages`, ...).
     *
     * @return list<CheckboxList>
     */
    public static function permissionFields(): array
    {
        $catalog = app(PermissionCatalogInterface::class);
        $fields = [];

        foreach ($catalog->groups() as $group => $components) {
            $options = [];

            foreach ($components as $component => $actions) {
                foreach ($actions as $action) {
                    $options[$component.'.'.$action] = $catalog->label($component.'.'.$action);
                }
            }

            $fields[] = CheckboxList::make('permissions_'.$group)
                ->label(__('admin_permissions.groups.'.$group))
                ->options($options)
                ->columns(3)
                ->gridDirection('row')
                ->bulkToggleable();
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function permissionsFromForm(array $data): array
    {
        $permissions = [];

        foreach (array_keys(app(PermissionCatalogInterface::class)->groups()) as $group) {
            foreach ((array) ($data['permissions_'.$group] ?? []) as $permission) {
                if (is_string($permission)) {
                    $permissions[] = $permission;
                }
            }
        }

        return $permissions;
    }

    /**
     * Splits a role's permissions into the per-group form fields.
     *
     * @param  list<string>  $permissions
     * @return array<string, list<string>>
     */
    public static function permissionsToForm(array $permissions): array
    {
        $data = [];

        foreach (app(PermissionCatalogInterface::class)->groups() as $group => $components) {
            $data['permissions_'.$group] = array_values(array_filter(
                $permissions,
                static fn (string $permission): bool => array_key_exists(explode('.', $permission)[0], $components),
            ));
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin_permissions.role.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('admin_permissions.role.slug'))->badge(),
                TextColumn::make('users_count')
                    ->label(__('admin_permissions.role.users_count'))
                    ->getStateUsing(static fn (Role $record): int => app(RoleAdminServiceInterface::class)->usersWithRole((int) $record->getKey())),
                TextColumn::make('permissions_count')
                    ->label(__('admin_permissions.role.permissions_count'))
                    ->getStateUsing(static fn (Role $record): string => $record->slug === 'super_admin'
                        ? __('admin_permissions.role.all_permissions')
                        : (string) count(app(RoleAdminServiceInterface::class)->permissionsForRole((int) $record->getKey()))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('delete')
                    ->label(__('filament-actions::delete.single.label'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(static fn (Role $record): bool => static::canDelete($record))
                    ->action(static function (Role $record): void {
                        try {
                            app(RoleAdminServiceInterface::class)->deleteRole((int) $record->getKey(), (int) auth()->id());
                            Notification::make()->title(__('admin_permissions.role.deleted'))->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
