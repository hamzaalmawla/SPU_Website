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
 * Only published publications are offered, imported ones included, so the
 * stored slugs point at the canonical research pages instead of copying their
 * content. The list opens on the newest publications and searching reaches
 * every one of them.
 */
final class ResearchPublicationPicker
{
    private const BROWSE_LIMIT = 50;

    public static function make(string $name, string $label, string $itemLabel, int $maxItems, int $minItems = 0): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                Select::make('publication_slug')
                    ->label($itemLabel)
                    ->helperText(__('admin.research_publication.help.picker'))
                    ->options(fn (): array => self::options(''))
                    ->searchable()
                    ->preload(false)
                    ->required()
                    ->distinct()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->getSearchResultsUsing(fn (string $search): array => self::options($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::optionLabel($value)),
            ])
            ->minItems($minItems)
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
            ->getHomepagePublicationCards(app()->getLocale(), [], $search, self::BROWSE_LIMIT)
            ->mapWithKeys(fn (ResearchCardDTO $card): array => [$card->slug => self::label($card)])
            ->all();
    }

    private static function optionLabel(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $card = app(ResearchPageServiceInterface::class)
            ->getHomepagePublicationCards(app()->getLocale(), [$value], null, 1)
            ->first();

        return $card instanceof ResearchCardDTO ? self::label($card) : $value;
    }

    private static function label(ResearchCardDTO $card): string
    {
        $year = is_string($card->publishedAt) ? substr($card->publishedAt, 0, 4) : '';

        return preg_match('/^\d{4}$/', $year) === 1 ? $card->title.' ('.$year.')' : $card->title;
    }
}
