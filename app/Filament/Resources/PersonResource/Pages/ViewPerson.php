<?php

declare(strict_types=1);

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Resources\PersonResource;
use App\Models\Person\Person;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPerson extends ViewRecord
{
    protected static string $resource = PersonResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        /** @var Person $record */
        $record = $this->record;
        $translation = $record->translations->firstWhere('locale', 'ar')
            ?? $record->translations->firstWhere('locale', 'en')
            ?? $record->translations->first();

        return $infolist->schema([
            Section::make(__('admin_resources.person.basic_information'))->schema([
                TextEntry::make('slug'),
                TextEntry::make('category'),
                TextEntry::make('title'),
                TextEntry::make('position'),
                TextEntry::make('translations.name')->label(__('admin_resources.person.name'))->state($translation?->name ?? '-'),
                TextEntry::make('translations.role')->label(__('admin_resources.person.role'))->state($translation?->role ?? '-'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('office_location')->label(__('admin_resources.person.office')),
                TextEntry::make('faculty_scope_slug')->label(__('admin_resources.person.faculty_scope')),
            ])->columns(2),
            Section::make(__('admin_resources.person.biography'))->schema([
                TextEntry::make('bio')->label(__('admin_resources.person.bio'))->state($translation?->bio ?? '-')->prose(),
                TextEntry::make('quote')->label(__('admin_resources.person.quote'))->state($translation?->quote ?? '-')->prose(),
            ]),
        ]);
    }
}
