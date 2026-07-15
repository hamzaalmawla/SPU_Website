<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PersonResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Person\Person;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.about');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.people');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profile')->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                Select::make('category')->required()->options([
                    'rector' => 'Rector',
                    'vice_president' => 'Vice President',
                    'dean' => 'Dean',
                    'director' => 'Director',
                    'council' => 'Council Member',
                ]),
                TextInput::make('title')->maxLength(255)->placeholder('Prof., Dr., etc.'),
                TextInput::make('position')->maxLength(255)->placeholder('Rector, Dean, etc.'),
                TextInput::make('faculty_scope_slug')->maxLength(255),
                MediaPicker::image('image', 'Profile Image'),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('office_location')->maxLength(255),
                TextInput::make('profile_url')->url()->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            Section::make('Social Links')->schema([
                TextInput::make('social_links.linkedin')->label('LinkedIn URL')->url()->maxLength(255),
                TextInput::make('social_links.scholar')->label('Google Scholar URL')->url()->maxLength(255),
                TextInput::make('social_links.orcid')->label('ORCID URL')->url()->maxLength(255),
                TextInput::make('social_links.researchgate')->label('ResearchGate URL')->url()->maxLength(255),
                TextInput::make('social_links.twitter')->label('Twitter/X URL')->url()->maxLength(255),
            ])->columns(2)->collapsible(),
            Repeater::make('translations')->schema([
                Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English'])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('role')->required()->maxLength(255),
                Textarea::make('bio')->rows(4),
                Textarea::make('quote')->rows(3),
            ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
            Section::make('Education')->schema([
                Repeater::make('educations')->schema([
                    TextInput::make('id')->hidden(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    Toggle::make('is_enabled')->default(true),
                    Repeater::make('translations')->schema([
                        Select::make('locale')->required()->options(['ar' => 'Arabic', 'en' => 'English'])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('degree')->required()->maxLength(255),
                        TextInput::make('institution')->maxLength(255),
                        TextInput::make('field_of_study')->maxLength(255),
                        TextInput::make('year_start')->numeric()->minValue(1900)->maxValue(2100),
                        TextInput::make('year_end')->numeric()->minValue(1900)->maxValue(2100)->gte('year_start'),
                        Textarea::make('description')->rows(2),
                    ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
                ])->columns(2)->defaultItems(0)->addActionLabel('Add Education')->columnSpanFull(),
            ])->collapsible()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('category')->badge()->sortable(),
            TextColumn::make('translations.name')->label('Names')->listWithLineBreaks()->limit(40),
            IconColumn::make('is_enabled')->boolean(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\ViewAction::make(),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
            'view' => Pages\ViewPerson::route('/{record}'),
        ];
    }
}
