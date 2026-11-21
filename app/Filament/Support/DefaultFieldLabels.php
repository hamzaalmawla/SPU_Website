<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\Str;

/**
 * Translated default labels for admin components that do not set one.
 *
 * Filament derives a missing label from the component name ("sort_order"
 * becomes "Sort order"), which is English in an Arabic-first panel. This
 * looks the same name up in `admin_resources.fields` first. A component
 * with its own ->label() is untouched, and a name with no entry keeps
 * Filament's own fallback.
 */
final class DefaultFieldLabels
{
    private const GROUP = 'admin_resources.fields.';

    public static function register(): void
    {
        Field::configureUsing(static function (Field $field): void {
            $field->label(static fn (): ?string => self::lookup(
                (string) Str::of($field->getName())->afterLast('.'),
            ));
        });

        Column::configureUsing(static function (Column $column): void {
            $column->label(static fn (): ?string => self::lookup(
                (string) Str::of($column->getName())->beforeLast('.')->afterLast('.'),
            ));
        });

        Entry::configureUsing(static function (Entry $entry): void {
            $entry->label(static fn (): ?string => self::lookup(
                (string) Str::of($entry->getName())->before('.'),
            ));
        });

        BaseFilter::configureUsing(static function (BaseFilter $filter): void {
            $filter->label(static fn (): ?string => self::lookup(
                (string) Str::of($filter->getName())->before('.'),
            ));
        });
    }

    private static function lookup(string $name): ?string
    {
        $key = self::GROUP.Str::snake($name);

        return $name !== '' && trans()->has($key) ? (string) __($key) : null;
    }
}
