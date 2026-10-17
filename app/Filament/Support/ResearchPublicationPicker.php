<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Content\ResearchCardDTO;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

/**
 * An ordered, searchable pick of published research publications, stored by slug.
 *
 * Only published publications are offered, so the stored slugs point at the
 * canonical research pages instead of copying their content.
 */
final class ResearchPublicationPicker
{
    public static function make(string $name, string $label, string $itemLabel, int $maxItems): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                Select::make('publication_slug')
                    ->label($itemLabel)
                    ->options(fn (): array => [])
                    ->searchable()
                    ->preload(false)
                    ->required()
                    ->distinct()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->getSearchResultsUsing(fn (string $search): array => self::options($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::optionLabel($value)),
            ])
            ->maxItems($maxItems)
            ->defaultItems(0)
            ->reorderable()
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => self::optionLabel($state['publication_slug'] ?? null));
    }

    /** @return array<string, string> */
    private static function options(string $search): array
    {
        return app(ResearchPageServiceInterface::class)
            ->getHomepagePublicationCards(app()->getLocale(), [], $search, 50)
            ->mapWithKeys(fn (ResearchCardDTO $card): array => [$card->slug => $card->title])
            ->all();
    }

    private static function optionLabel(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return app(ResearchPageServiceInterface::class)
            ->getHomepagePublicationCards(app()->getLocale(), [$value], null, 1)
            ->first()?->title ?? $value;
    }
}
