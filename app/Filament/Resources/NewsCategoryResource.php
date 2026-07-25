<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsCategoryResource\Pages;
use App\Models\News\NewsCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NewsCategoryResource extends Resource
{
    protected static ?string $model = NewsCategory::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('admin.news_category.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.news_category.plural_model');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof NewsCategory ? self::localizedName($record) : self::getModelLabel();
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.news');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.news_categories');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('admin.news_category.sections.content'))
                ->description(__('admin.news_category.help.content'))
                ->schema([
                    Select::make('type')
                        ->label(__('admin.news_category.fields.type'))
                        ->required()
                        ->options([
                            'news' => __('admin.news_article.types.news'),
                            'announcement' => __('admin.news_article.types.announcement'),
                        ])
                        ->default('news'),
                    Toggle::make('is_enabled')
                        ->label(__('admin.news_category.fields.enabled'))
                        ->helperText(__('admin.news_category.help.enabled'))
                        ->default(true),
                    Repeater::make('translations')
                        ->label(__('admin.news_category.fields.translations'))
                        ->relationship()
                        ->itemLabel(fn (array $state): string => __('admin.news_category.translation_item', [
                            'language' => __('admin.locales.'.($state['locale'] ?? 'ar')),
                        ]))
                        ->schema([
                            Select::make('locale')
                                ->label(__('admin.news_category.fields.language'))
                                ->required()
                                ->options([
                                    'ar' => __('admin.locales.ar'),
                                    'en' => __('admin.locales.en'),
                                ])
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->live(),
                            TextInput::make('name')
                                ->label(__('admin.news_category.fields.name'))
                                ->required()
                                ->maxLength(255)
                                ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                            Textarea::make('description')
                                ->label(__('admin.news_category.fields.description'))
                                ->rows(3)
                                ->columnSpanFull()
                                ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                        ])
                        ->columns(2)
                        ->minItems(2)
                        ->maxItems(2)
                        ->default([
                            ['locale' => 'ar'],
                            ['locale' => 'en'],
                        ])
                        ->reorderable(false)
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('admin.news_category.sections.advanced'))
                ->description(__('admin.news_category.help.advanced'))
                ->collapsed()
                ->schema([
                    TextInput::make('slug')
                        ->label(__('admin.news_category.fields.slug'))
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    TextInput::make('sort_order')
                        ->label(__('admin.news_category.fields.sort_order'))
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('localized_name')
                    ->label(__('admin.news_category.table.name'))
                    ->getStateUsing(fn (NewsCategory $record): string => self::localizedName($record))
                    ->description(fn (NewsCategory $record): string => self::usesArabicFallback($record) ? __('admin.news_category.table.arabic_fallback') : '')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $translationQuery): Builder => $translationQuery->where('name', 'like', "%{$search}%")
                    )),
                TextColumn::make('type')
                    ->label(__('admin.news_category.table.type'))
                    ->formatStateUsing(fn (string $state): string => __('admin.news_article.types.'.$state))
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_enabled')->label(__('admin.news_category.table.enabled'))->boolean(),
                TextColumn::make('updated_at')->label(__('admin.news_category.table.updated_at'))->dateTime(__('admin.formats.date_time'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.news_category.filters.type'))
                    ->options([
                        'news' => __('admin.news_article.types.news'),
                        'announcement' => __('admin.news_article.types.announcement'),
                    ]),
                TernaryFilter::make('is_enabled')->label(__('admin.news_category.filters.enabled')),
            ])
            ->actions([Tables\Actions\EditAction::make()->label(__('admin.actions.edit'))])
            ->bulkActions([])
            ->emptyStateHeading(__('admin.news_category.empty.heading'))
            ->emptyStateDescription(__('admin.news_category.empty.description'))
            ->emptyStateIcon('heroicon-o-tag')
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsCategories::route('/'),
            'create' => Pages\CreateNewsCategory::route('/create'),
            'edit' => Pages\EditNewsCategory::route('/{record}/edit'),
        ];
    }

    private static function localizedName(NewsCategory $category): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $name = $category->translations->firstWhere('locale', $locale)?->name;
        $arabicName = $category->translations->firstWhere('locale', 'ar')?->name;

        return filled($name) ? trim((string) $name) : (filled($arabicName) ? trim((string) $arabicName) : $category->slug);
    }

    private static function usesArabicFallback(NewsCategory $category): bool
    {
        return app()->getLocale() === 'en'
            && blank($category->translations->firstWhere('locale', 'en')?->name)
            && filled($category->translations->firstWhere('locale', 'ar')?->name);
    }
}
