<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * For resources whose model has no policy: Filament would otherwise allow
 * every action once the list opens. Each action maps to the component's
 * permission (`directorates.view`, `directorates.create`, ...).
 */
trait AuthorizesThroughPermissions
{
    abstract protected static function permissionComponent(): string;

    public static function canAccess(): bool
    {
        return Gate::allows(static::permissionComponent().'.view');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return Gate::allows(static::permissionComponent().'.create');
    }

    public static function canEdit(Model $record): bool
    {
        return Gate::allows(static::permissionComponent().'.update');
    }

    public static function canDelete(Model $record): bool
    {
        return Gate::allows(static::permissionComponent().'.delete');
    }

    public static function canDeleteAny(): bool
    {
        return Gate::allows(static::permissionComponent().'.delete');
    }
}
