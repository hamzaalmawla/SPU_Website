<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\News\NewsArticleCmsServiceInterface;
use App\Filament\Resources\NewsArticleResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
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
use Illuminate\Support\Facades\Gate;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return __('admin.news_article.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.news_article.plural_model');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof NewsArticle ? self::localizedTitle($record) : self::getModelLabel();
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', NewsArticle::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.news');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.news_articles');
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeQueryToCurrentUser(parent::getEloquentQuery())
            ->with(['translations', 'category.translations', 'coverMedia']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('news_editor')
                ->tabs([
                    Tab::make(__('admin.news_article.tabs.content'))
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Section::make(__('admin.news_article.sections.basics'))
                                ->description(__('admin.news_article.help.basics'))
                                ->schema([
                                    Radio::make('news_category_id')
                                        ->label(__('admin.news_article.fields.category'))
                                        ->options(fn (): array => self::editorialTypeOptions())
                                        ->required()
                                        ->inline(),
                                    MediaPicker::assetImage('cover_media_id', __('admin.news_article.fields.cover_image')),
                                ])
                                ->columns(2),
                            Section::make(__('admin.news_article.sections.languages'))
                                ->description(__('admin.news_article.help.languages'))
                                ->schema([
                                    Repeater::make('translations')
                                        ->hiddenLabel()
                                        ->itemLabel(fn (array $state): string => __('admin.news_article.translation_item', [
                                            'language' => __('admin.locales.'.($state['locale'] ?? 'ar')),
                                        ]))
                                        ->schema([
                                            Hidden::make('locale')->required(),
                                            TextInput::make('title')
                                                ->label(__('admin.news_article.fields.title'))
                                                ->required()
                                                ->maxLength(255)
                                                ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                                            Textarea::make('excerpt')
                                                ->label(__('admin.news_article.fields.summary'))
                                                ->rows(3)
                                                ->columnSpanFull()
                                                ->extraInputAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                                            RichEditor::make('body')
                                                ->label(__('admin.news_article.fields.body'))
                                                ->columnSpanFull()
                                                ->extraAttributes(fn (Get $get): array => ['dir' => $get('locale') === 'en' ? 'ltr' : 'rtl']),
                                        ])
                                        ->columns(2)
                                        ->minItems(2)
                                        ->maxItems(2)
                                        ->deletable(false)
                                        ->default([
                                            ['locale' => 'ar'],
                                            ['locale' => 'en'],
                                        ])
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    Tab::make(__('admin.news_article.tabs.publishing'))
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Section::make(__('admin.news_article.sections.visibility'))
                                ->description(__('admin.news_article.help.private_draft'))
                                ->schema([
                                    Toggle::make('is_enabled')
                                        ->label(__('admin.news_article.fields.enabled'))
                                        ->helperText(__('admin.news_article.help.enabled'))
                                        ->default(true),
                                    Toggle::make('is_featured')
                                        ->label(__('admin.news_article.fields.featured'))
                                        ->helperText(__('admin.news_article.help.featured'))
                                        ->default(false),
                                ])
                                ->columns(2),
                        ]),
                    Tab::make(__('admin.news_article.tabs.attachments'))
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Repeater::make('attachments')
                                ->label(__('admin.news_article.sections.attachments'))
                                ->schema([
                                    Hidden::make('id'),
                                    MediaPicker::assetAny('media_asset_id', __('admin.news_article.fields.media')),
                                    Radio::make('kind')
                                        ->label(__('admin.news_article.fields.attachment_type'))
                                        ->required()
                                        ->options([
                                            'image' => __('admin.news_article.attachment_types.image'),
                                            'file' => __('admin.news_article.attachment_types.file'),
                                            'video' => __('admin.news_article.attachment_types.video'),
                                        ])
                                        ->inline()
                                        ->default('file'),
                                    TextInput::make('label_ar')->label(__('admin.news_article.fields.attachment_label_ar'))->maxLength(255),
                                    TextInput::make('label_en')->label(__('admin.news_article.fields.attachment_label_en'))->maxLength(255),
                                    TextInput::make('legacy_path')->maxLength(255)->hidden(),
                                    Hidden::make('legacy_source_table'),
                                    Hidden::make('legacy_source_id'),
                                    TextInput::make('sort_order')->numeric()->default(0)->hidden(),
                                ])
                                ->columns(2)
                                ->collapsible()
                                ->columnSpanFull(),
                        ]),
                    Tab::make(__('admin.news_article.tabs.advanced'))
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Section::make(__('admin.news_article.sections.technical'))
                                ->collapsed()
                                ->schema([
                                    TextInput::make('slug')
                                        ->label(__('admin.news_article.fields.slug'))
                                        ->maxLength(80)
                                        ->alphaDash()
                                        ->unique(ignoreRecord: true)
                                        ->helperText(__('admin.news_article.help.slug')),
                                    TextInput::make('sort_order')
                                        ->label(__('admin.news_article.fields.sort_order'))
                                        ->numeric()
                                        ->default(0),
                                    TextInput::make('faculty_scope_slug')
                                        ->label(__('admin.news_article.fields.faculty_scope'))
                                        ->maxLength(255)
                                        ->alphaDash()
                                        ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),
                                ])
                                ->columns(2),
                            Section::make(__('admin.news_article.sections.seo'))
                                ->collapsed()
                                ->schema([
                                    Repeater::make('seoMeta')
                                        ->hiddenLabel()
                                        ->schema([
                                            Select::make('locale')->required()->options([
                                                'ar' => __('admin.locales.ar'),
                                                'en' => __('admin.locales.en'),
                                            ]),
                                            TextInput::make('meta_title')->label(__('admin.news_article.fields.meta_title'))->maxLength(255),
                                            Textarea::make('meta_description')->label(__('admin.news_article.fields.meta_description'))->rows(2),
                                            TextInput::make('og_title')->label(__('admin.news_article.fields.og_title'))->maxLength(255),
                                            Textarea::make('og_description')->label(__('admin.news_article.fields.og_description'))->rows(2),
                                            MediaPicker::assetImage('og_image_media_id', __('admin.news_article.fields.og_image')),
                                            MediaPicker::image('og_image_url', __('admin.news_article.fields.legacy_og_image')),
                                            TextInput::make('robots')->label(__('admin.news_article.fields.robots'))->default('index,follow')->maxLength(255),
                                        ])
                                        ->columns(2)
                                        ->maxItems(2)
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->persistTabInQueryString('editor')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('localized_title')
                    ->label(__('admin.news_article.table.title'))
                    ->getStateUsing(fn (NewsArticle $record): string => self::localizedTitle($record))
                    ->description(fn (NewsArticle $record): string => self::usesArabicFallback($record) ? __('admin.news_article.table.arabic_fallback') : '')
                    ->limit(70)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $translationQuery): Builder => $translationQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('excerpt', 'like', "%{$search}%")
                    )),
                TextColumn::make('category_name')
                    ->label(__('admin.news_article.table.category'))
                    ->getStateUsing(fn (NewsArticle $record): string => self::editorialTypeLabel($record->category))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('admin.news_article.table.status'))
                    ->formatStateUsing(fn (string $state): string => __('admin.news_article.statuses.'.$state))
                    ->badge()->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                IconColumn::make('is_enabled')->label(__('admin.news_article.table.enabled'))->boolean(),
                IconColumn::make('is_featured')->label(__('admin.news_article.table.featured'))->boolean(),
                TextColumn::make('scheduled_at')
                    ->label(__('admin.news_article.table.scheduled_at'))
                    ->dateTime(__('admin.formats.date_time'))
                    ->placeholder(__('admin.news_article.table.not_scheduled'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('updated_at')->label(__('admin.news_article.table.updated_at'))->dateTime(__('admin.formats.date_time'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_type')
                    ->label(__('admin.news_article.filters.type'))
                    ->options(['news' => __('admin.news_article.types.news'), 'announcement' => __('admin.news_article.types.announcement')])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('category', fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', $data['value']))
                        : $query),
                SelectFilter::make('status')->label(__('admin.news_article.filters.status'))->options(self::translatedStatuses()),
                TernaryFilter::make('is_enabled')->label(__('admin.news_article.filters.enabled')),
                TernaryFilter::make('is_featured')->label(__('admin.news_article.filters.featured')),
            ])
            ->actions([Tables\Actions\EditAction::make()->label(__('admin.actions.edit'))])
            ->bulkActions([])
            ->emptyStateHeading(__('admin.news_article.empty.heading'))
            ->emptyStateDescription(__('admin.news_article.empty.description'))
            ->emptyStateIcon('heroicon-o-newspaper')
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit' => Pages\EditNewsArticle::route('/{record}/edit'),
        ];
    }

    private static function scopeQueryToCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->role_slug !== 'faculty_editor') {
            return $query;
        }

        $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';

        return $scope === '' ? $query->whereRaw('1 = 0') : $query->where('faculty_scope_slug', $scope);
    }

    /** @return array<string, string> */
    private static function translatedStatuses(): array
    {
        return [
            'draft' => __('admin.news_article.statuses.draft'),
            'published' => __('admin.news_article.statuses.published'),
            'scheduled' => __('admin.news_article.statuses.scheduled'),
            'archived' => __('admin.news_article.statuses.archived'),
        ];
    }

    private static function localizedTitle(NewsArticle $article): string
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $title = $article->translations->firstWhere('locale', $locale)?->title;
        $arabicTitle = $article->translations->firstWhere('locale', 'ar')?->title;

        return filled($title) ? trim((string) $title) : (filled($arabicTitle) ? trim((string) $arabicTitle) : $article->slug);
    }

    private static function usesArabicFallback(NewsArticle $article): bool
    {
        return app()->getLocale() === 'en'
            && blank($article->translations->firstWhere('locale', 'en')?->title)
            && filled($article->translations->firstWhere('locale', 'ar')?->title);
    }

    private static function editorialTypeLabel(mixed $category): string
    {
        if (! $category instanceof NewsCategory || ! in_array($category->type, ['news', 'announcement'], true)) {
            return __('admin.news_article.table.no_category');
        }

        return __('admin.news_article.types.'.$category->type);
    }

    /** @return array<int, string> */
    private static function editorialTypeOptions(): array
    {
        $options = [];

        foreach (app(NewsArticleCmsServiceInterface::class)->editorialTypeOptions() as $categoryId => $type) {
            $options[$categoryId] = __('admin.news_article.types.'.$type);
        }

        return $options;
    }
}
