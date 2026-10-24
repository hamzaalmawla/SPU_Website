<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AchievementCategoryResource\Pages;
use App\Models\Achievement\AchievementCategory;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class AchievementCategoryResource extends Resource
{
    protected static ?string $model = AchievementCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('admin.achievement_category.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.achievement_category.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.achievement_category.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', AchievementCategory::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.achievement_category.content'))->schema([
                TextInput::make('slug')->label(__('admin.achievement_category.slug'))->required()->alphaDash()->maxLength(100)->unique(ignoreRecord: true),
                Toggle::make('is_active')->label(__('admin.achievement_category.active'))->default(true),
                TextInput::make('sort_order')->label(__('admin.achievement.sort_order'))->numeric()->default(0),
                Repeater::make('translations')->relationship()->label(__('admin.achievement.translations'))->schema([
                    Hidden::make('locale')->required(),
                    TextInput::make('name')->label(__('admin.achievement_category.name'))->required()->maxLength(255)
                        ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                ])->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->deletable(false)->reorderable(false)->columns(2)->columnSpanFull(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('admin.achievement_category.name'))->getStateUsing(fn (AchievementCategory $record): string => (string) ($record->translations->firstWhere('locale', app()->getLocale())?->name ?? $record->translations->firstWhere('locale', 'ar')?->name ?? $record->slug))->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('translations', fn (Builder $q): Builder => $q->where('name', 'like', "%{$search}%"))),
            TextColumn::make('slug')->label(__('admin.achievement_category.slug')),
            IconColumn::make('is_active')->label(__('admin.achievement_category.active'))->boolean(),
            TextColumn::make('sort_order')->label(__('admin.achievement.sort_order'))->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([])->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAchievementCategories::route('/'), 'create' => Pages\CreateAchievementCategory::route('/create'), 'edit' => Pages\EditAchievementCategory::route('/{record}/edit')];
    }
}
