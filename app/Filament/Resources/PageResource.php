<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\MediaPicker;
use App\Models\Page\Page;
use App\Models\User\User;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Filament resource for managing bilingual landing pages.
 *
 * All business logic is delegated to PageServiceInterface.
 * This resource provides only the UI layer.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return __('admin.page_resource.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.page_resource.plural_model');
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Page) {
            return self::getModelLabel();
        }

        return self::localizedTitle($record);
    }

    public static function getPublicationStatusLabel(?string $status): string
    {
        return __('admin.page_resource.statuses.'.self::normalizedPublicationStatus($status));
    }

    public static function getPublicationStatusColor(?string $status): string
    {
        return match (self::normalizedPublicationStatus($status)) {
            'published' => 'success',
            'scheduled' => 'warning',
            default => 'gray',
        };
    }

    public static function getPublicationStatusIcon(?string $status): string
    {
        return match (self::normalizedPublicationStatus($status)) {
            'published' => 'heroicon-o-check-circle',
            'scheduled' => 'heroicon-o-clock',
            default => 'heroicon-o-document',
        };
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Page::class);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.pages');
    }

    public static function getEloquentQuery(): Builder
    {
        return self::scopeQueryToCurrentUser(parent::getEloquentQuery())
            ->with(['translations', 'parent']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('page_tabs')
                ->tabs([
                    self::metadataTab(),
                    self::arabicTranslationTab(),
                    self::englishTranslationTab(),
                    self::arabicSeoTab(),
                    self::englishSeoTab(),
                ])
                ->persistTabInQueryString('tab')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('localized_title')
                    ->label(__('admin.page_resource.table.title'))
                    ->getStateUsing(fn (Page $record): string => self::localizedTitle($record))
                    ->description(fn (Page $record): string => self::titleDescription($record))
                    ->wrap()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::sortByLocalizedTitle($query, $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::searchByLocalizedTitle($query, $search)),

                TextColumn::make('slug')
                    ->label(__('admin.page_resource.table.slug'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('admin.page_resource.table.status'))
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::getPublicationStatusLabel(is_string($state) ? $state : null))
                    ->color(fn (Page $record): string => self::getPublicationStatusColor(is_string($record->status) ? $record->status : null)),

                TextColumn::make('parent.slug')
                    ->label(__('admin.page_resource.table.parent'))
                    ->placeholder(__('admin.page_resource.table.no_parent')),

                IconColumn::make('is_enabled')
                    ->label(__('admin.page_resource.table.enabled'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('admin.page_resource.table.updated'))
                    ->dateTime(__('admin.page_resource.formats.date_time'))
                    ->dateTimeTooltip(__('admin.page_resource.formats.date_time_with_timezone'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.page_resource.table.status'))
                    ->options([
                        'draft' => __('admin.page_resource.statuses.draft'),
                        'published' => __('admin.page_resource.statuses.published'),
                        'scheduled' => __('admin.page_resource.statuses.scheduled'),
                    ]),

                SelectFilter::make('locale')
                    ->label(__('admin.page_resource.table.locale'))
                    ->options([
                        'ar' => __('admin.page_resource.locale_names.ar'),
                        'en' => __('admin.page_resource.locale_names.en'),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('translations', function (Builder $query) use ($data): void {
                                $query->where('locale', $data['value']);
                            });
                        }
                    }),

                SelectFilter::make('parent_id')
                    ->label(__('admin.page_resource.table.parent'))
                    ->relationship(
                        'parent',
                        'slug',
                        modifyQueryUsing: fn (Builder $query): Builder => self::scopeQueryToCurrentUser($query),
                    )
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('admin.page_resource.actions.view')),
                Tables\Actions\EditAction::make()
                    ->label(__('admin.page_resource.actions.edit')),
            ])
            ->bulkActions([])
            ->searchPlaceholder(__('admin.page_resource.table.search_placeholder'))
            ->emptyStateHeading(fn (Table $table): string => self::tableHasSearch($table)
                ? __('admin.page_resource.empty.search_heading')
                : __('admin.page_resource.empty.heading'))
            ->emptyStateDescription(fn (Table $table): string => self::tableHasSearch($table)
                ? __('admin.page_resource.empty.search_description')
                : __('admin.page_resource.empty.description'))
            ->defaultSort('updated_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'view' => Pages\ViewPage::route('/{record}'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }

    // ──────────────────────────────────────────────
    // Form Tab Builders
    // ──────────────────────────────────────────────

    private static function metadataTab(): Tab
    {
        return Tab::make(__('admin.page_resource.tabs.page_settings'))
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make(__('admin.page_resource.sections.page_settings'))->schema([
                    Select::make('parent_id')
                        ->label(__('admin.page_resource.fields.parent_page'))
                        ->relationship(
                            'parent',
                            'slug',
                            modifyQueryUsing: fn (Builder $query, ?Page $record = null): Builder => self::scopeParentQueryToCurrentUser($query, $record),
                        )
                        ->searchable()
                        ->placeholder(__('admin.page_resource.placeholders.no_parent')),
                ]),

                Section::make(__('admin.page_resource.sections.visibility'))->schema([
                    Toggle::make('is_enabled')
                        ->label(__('admin.page_resource.fields.enabled'))
                        ->default(true),

                    Toggle::make('show_in_nav')
                        ->label(__('admin.page_resource.fields.show_in_navigation'))
                        ->default(true),

                    Toggle::make('show_in_breadcrumbs')
                        ->label(__('admin.page_resource.fields.show_in_breadcrumbs'))
                        ->default(true),
                ])->columns(3),

                Section::make(__('admin.page_resource.sections.advanced_settings'))
                    ->collapsed()
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('admin.page_resource.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->alphaDash(),

                        TextInput::make('faculty_scope_slug')
                            ->label(__('admin.page_resource.fields.faculty_scope_slug'))
                            ->helperText(__('admin.page_resource.help.faculty_scope_slug'))
                            ->maxLength(255)
                            ->alphaDash()
                            ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),

                        Select::make('template')
                            ->label(__('admin.page_resource.fields.template'))
                            ->options([
                                'default' => __('admin.page_resource.templates.default'),
                                'landing' => __('admin.page_resource.templates.landing'),
                                'faculty' => __('admin.page_resource.templates.faculty'),
                                'department' => __('admin.page_resource.templates.department'),
                            ])
                            ->required()
                            ->default('default'),
                    ]),
            ]);
    }

    private static function translationFields(string $locale): array
    {
        $prefix = $locale === 'ar' ? 'ar_' : 'en_';

        return [
            Section::make(__('admin.page_resource.sections.content'))->schema([
                TextInput::make("{$prefix}title")
                    ->label(self::localizedFieldLabel('title', $locale))
                    ->required()
                    ->maxLength(255),

                TextInput::make("{$prefix}headline")
                    ->label(self::localizedFieldLabel('headline', $locale))
                    ->maxLength(255),

                TextInput::make("{$prefix}subheadline")
                    ->label(self::localizedFieldLabel('subheadline', $locale))
                    ->maxLength(500),

                Textarea::make("{$prefix}hero_content")
                    ->label(self::localizedFieldLabel('hero_content', $locale))
                    ->rows(3)
                    ->maxLength(2000),

                RichEditor::make("{$prefix}body")
                    ->label(self::localizedFieldLabel('body', $locale))
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin.page_resource.sections.call_to_action'))->schema([
                TextInput::make("{$prefix}cta_label")
                    ->label(self::localizedFieldLabel('cta_label', $locale))
                    ->maxLength(100),

                TextInput::make("{$prefix}cta_url")
                    ->label(self::localizedFieldLabel('cta_url', $locale))
                    ->url()
                    ->maxLength(2048),
            ])->columns(2),

            Section::make(__('admin.page_resource.sections.additional_content'))->schema([
                Textarea::make("{$prefix}sidebar_content")
                    ->label(self::localizedFieldLabel('sidebar_content', $locale))
                    ->rows(4),

                Textarea::make("{$prefix}excerpt")
                    ->label(self::localizedFieldLabel('excerpt', $locale))
                    ->rows(3)
                    ->maxLength(500),
            ]),
        ];
    }

    private static function arabicTranslationTab(): Tab
    {
        return Tab::make(__('admin.page_resource.tabs.content_ar'))
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'rtl'])
            ->schema(self::translationFields('ar'));
    }

    private static function englishTranslationTab(): Tab
    {
        return Tab::make(__('admin.page_resource.tabs.content_en'))
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema(self::translationFields('en'));
    }

    private static function seoFields(string $locale): array
    {
        $prefix = $locale === 'ar' ? 'ar_seo_' : 'en_seo_';

        return [
            Section::make(__('admin.page_resource.sections.meta_tags'))->schema([
                TextInput::make("{$prefix}meta_title")
                    ->label(self::localizedFieldLabel('meta_title', $locale))
                    ->maxLength(70),

                Textarea::make("{$prefix}meta_description")
                    ->label(self::localizedFieldLabel('meta_description', $locale))
                    ->rows(3)
                    ->maxLength(160),
            ]),

            Section::make(__('admin.page_resource.sections.open_graph'))->schema([
                TextInput::make("{$prefix}og_title")
                    ->label(self::localizedFieldLabel('og_title', $locale))
                    ->maxLength(70),

                Textarea::make("{$prefix}og_description")
                    ->label(self::localizedFieldLabel('og_description', $locale))
                    ->rows(3)
                    ->maxLength(200),

                MediaPicker::image("{$prefix}og_image", self::localizedFieldLabel('og_image', $locale)),
            ]),

            Section::make(__('admin.page_resource.sections.advanced_seo'))
                ->collapsed()
                ->schema([
                    TextInput::make("{$prefix}canonical_url")
                        ->label(self::localizedFieldLabel('canonical_url', $locale))
                        ->url()
                        ->maxLength(2048),

                    TextInput::make("{$prefix}robots")
                        ->label(self::localizedFieldLabel('robots', $locale))
                        ->placeholder(__('admin.page_resource.placeholders.robots'))
                        ->maxLength(100),
                ]),
        ];
    }

    private static function arabicSeoTab(): Tab
    {
        return Tab::make(__('admin.page_resource.tabs.seo_ar'))
            ->icon('heroicon-o-magnifying-glass')
            ->extraAttributes(['dir' => 'rtl'])
            ->schema(self::seoFields('ar'));
    }

    private static function englishSeoTab(): Tab
    {
        return Tab::make(__('admin.page_resource.tabs.seo_en'))
            ->icon('heroicon-o-magnifying-glass')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema(self::seoFields('en'));
    }

    private static function localizedFieldLabel(string $field, string $locale): string
    {
        return __('admin.page_resource.localized_field', [
            'field' => __('admin.page_resource.fields.'.$field),
            'locale' => __('admin.page_resource.locale_adjectives.'.$locale),
        ]);
    }

    private static function localizedTitle(Page $page): string
    {
        return self::titleForLocale($page, self::adminLocale())
            ?? self::titleForLocale($page, 'ar')
            ?? $page->slug;
    }

    private static function titleDescription(Page $page): string
    {
        $description = [__('admin.page_resource.table.slug_value', ['slug' => $page->slug])];

        if (self::usesArabicTitleFallback($page)) {
            $description[] = __('admin.page_resource.table.arabic_fallback');
        }

        return implode(' | ', $description);
    }

    private static function titleForLocale(Page $page, string $locale): ?string
    {
        $title = $page->translations->firstWhere('locale', $locale)?->title;

        return is_string($title) && trim($title) !== '' ? trim($title) : null;
    }

    private static function usesArabicTitleFallback(Page $page): bool
    {
        return self::adminLocale() !== 'ar'
            && self::titleForLocale($page, self::adminLocale()) === null
            && self::titleForLocale($page, 'ar') !== null;
    }

    private static function sortByLocalizedTitle(Builder $query, string $direction): Builder
    {
        $activeTitle = DB::table('page_translations')
            ->select('title')
            ->whereColumn('page_translations.page_id', 'pages.id')
            ->where('locale', self::adminLocale())
            ->limit(1);

        $arabicTitle = DB::table('page_translations')
            ->select('title')
            ->whereColumn('page_translations.page_id', 'pages.id')
            ->where('locale', 'ar')
            ->limit(1);

        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(
            "COALESCE(NULLIF(TRIM(({$activeTitle->toSql()})), ''), NULLIF(TRIM(({$arabicTitle->toSql()})), ''), pages.slug) {$direction}",
            [...$activeTitle->getBindings(), ...$arabicTitle->getBindings()],
        );
    }

    private static function searchByLocalizedTitle(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        if (self::adminLocale() === 'ar') {
            return self::whereTitleMatchesLocale($query, 'ar', $search);
        }

        return $query->where(function (Builder $query) use ($search): void {
            self::whereTitleMatchesLocale($query, 'en', $search)
                ->orWhere(function (Builder $query) use ($search): void {
                    self::whereWithoutNonBlankEnglishTitle($query);
                    self::whereTitleMatchesLocale($query, 'ar', $search);
                });
        });
    }

    private static function whereTitleMatchesLocale(Builder $query, string $locale, string $search): Builder
    {
        return $query->whereHas('translations', function (Builder $query) use ($locale, $search): void {
            $query
                ->where('locale', $locale)
                ->where('title', 'like', "%{$search}%");
        });
    }

    private static function whereWithoutNonBlankEnglishTitle(Builder $query): Builder
    {
        return $query->whereDoesntHave('translations', function (Builder $query): void {
            $query
                ->where('locale', 'en')
                ->whereRaw("TRIM(title) <> ''");
        });
    }

    private static function tableHasSearch(Table $table): bool
    {
        $livewire = $table->getLivewire();

        return method_exists($livewire, 'hasTableSearch') && $livewire->hasTableSearch();
    }

    private static function adminLocale(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'ar';
    }

    private static function normalizedPublicationStatus(?string $status): string
    {
        return in_array($status, ['draft', 'published', 'scheduled'], true) ? $status : 'draft';
    }

    private static function scopeQueryToCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($user->role_slug, ['super_admin', 'editor'], true)) {
            return $query;
        }

        if ($user->role_slug !== 'faculty_editor') {
            return $query->whereRaw('1 = 0');
        }

        $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';

        if ($scope === '' || ! Schema::hasColumn('pages', 'faculty_scope_slug')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('faculty_scope_slug', $scope);
    }

    private static function scopeParentQueryToCurrentUser(Builder $query, ?Page $record): Builder
    {
        $query = self::scopeQueryToCurrentUser($query);
        $excludedIds = self::invalidParentIds($record);

        if ($excludedIds === []) {
            return $query;
        }

        return $query->whereNotIn('id', $excludedIds);
    }

    /** @return list<int> */
    private static function invalidParentIds(?Page $record): array
    {
        if (! $record instanceof Page || ! $record->exists) {
            return [];
        }

        $excludedIds = [(int) $record->getKey()];
        $frontier = $excludedIds;

        while ($frontier !== []) {
            $children = Page::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $excludedIds));

            if ($children === []) {
                break;
            }

            $excludedIds = array_values(array_unique(array_merge($excludedIds, $children)));
            $frontier = $children;
        }

        return $excludedIds;
    }
}
