<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Textarea;

/**
 * A plain JSON editor for array-cast columns that hold nested content.
 *
 * Filament's KeyValue field only round-trips flat string pairs: nested
 * sections, cards and lists either break the form or are lost on save. This
 * shows the stored value as pretty-printed JSON, accepts only a JSON object
 * or array back, and saves it exactly as written.
 */
final class JsonEditor
{
    public static function make(string $name): Textarea
    {
        return Textarea::make($name)
            ->rows(16)
            ->autosize()
            ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false', 'class' => 'font-mono text-xs'])
            ->formatStateUsing(static fn (mixed $state): string => self::encode($state))
            ->dehydrateStateUsing(static fn (mixed $state): array => self::decode($state))
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && ! is_array(json_decode($value, true))) {
                    $fail(__('admin_resources.json_editor.invalid_json'));
                }
            });
    }

    private static function encode(mixed $state): string
    {
        if (is_string($state)) {
            return $state;
        }

        if (! is_array($state) || $state === []) {
            return '';
        }

        return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<mixed>
     */
    private static function decode(mixed $state): array
    {
        if (is_array($state)) {
            return $state;
        }

        if (! is_string($state) || trim($state) === '') {
            return [];
        }

        $decoded = json_decode($state, true);

        return is_array($decoded) ? $decoded : [];
    }
}
