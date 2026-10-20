<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Models\User\User;
use Filament\Forms\Components\Select;

/**
 * The faculty picker a user with a faculty limit sees when it holds more than one
 * faculty: only their own faculties are offered. With a single faculty the
 * record is filed under it automatically, so the field stays hidden.
 */
final class GrantedFacultySelect
{
    public static function make(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (): array => self::options())
            ->required(fn (): bool => self::options() !== [])
            ->visible(fn (): bool => count(self::options()) > 1);
    }

    /** @return array<string, string> */
    private static function options(): array
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasFacultyLimit()) {
            return [];
        }

        $access = app(AdminAccessServiceInterface::class);

        return array_intersect_key($access->facultyOptions(), array_flip($access->limitedFaculties($user)));
    }
}
