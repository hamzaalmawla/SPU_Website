<?php

declare(strict_types=1);

namespace App\Filament\Support;

use BackedEnum;
use Filament\Tables\Columns\TextColumn;

/**
 * One look for publication states in admin tables: a translated word, a
 * colour and an icon, so a state never depends on colour alone.
 */
final class StatusBadge
{
    private const KNOWN = ['draft', 'published', 'scheduled', 'archived', 'superseded'];

    public static function publication(TextColumn $column): TextColumn
    {
        return $column
            ->badge()
            ->formatStateUsing(static function (mixed $state): string {
                $value = self::value($state);

                return in_array($value, self::KNOWN, true)
                    ? (string) __('admin.status_badges.'.$value)
                    : $value;
            })
            ->color(static fn (mixed $state): string => match (self::value($state)) {
                'published' => 'success',
                'scheduled' => 'warning',
                default => 'gray',
            })
            ->icon(static fn (mixed $state): ?string => match (self::value($state)) {
                'published' => 'heroicon-m-check-circle',
                'scheduled' => 'heroicon-m-clock',
                'draft' => 'heroicon-m-pencil-square',
                'archived' => 'heroicon-m-archive-box',
                'superseded' => 'heroicon-m-arrow-uturn-left',
                default => null,
            });
    }

    private static function value(mixed $state): string
    {
        return $state instanceof BackedEnum ? (string) $state->value : (string) $state;
    }
}
