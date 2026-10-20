<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Page\PageServiceInterface;
use App\Filament\Components\PageUrlSelect;
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
use Illuminate\Support\Facades\Gate;

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

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Page) {
            return self::getModelLabel();
        }

        $translation = $record->translations->first();

        return filled($translation?->title) ? trim((string) $translation->title) : $record->slug;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Page::class);
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.page.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.page.plural_model');
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
                TextColumn::make('translations_title')
                    ->label(__('admin_resources.page.title'))
                    ->getStateUsing(function (Page $record): string {
                        $translation = $record->translations->first();

                        return $translation?->title ?? $record->slug;
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('translations', function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('slug')
                    ->label(__('admin_resources.page.slug'))
                    ->limit(40),

                TextColumn::make('status')
                    ->label(__('admin_resources.page.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('parent.slug')
                    ->label(__('admin_resources.page.parent'))
                    ->placeholder('—'),

                IconColumn::make('is_enabled')
                    ->label(__('admin_resources.page.enabled'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('admin_resources.page.updated'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => __('admin_resources.page.draft'),
                        'published' => __('admin_resources.page.published'),
                        'scheduled' => __('admin_resources.page.scheduled'),
                    ]),

                SelectFilter::make('locale')
                    ->label(__('admin_resources.page.locale'))
                    ->options([
                        'ar' => __('admin_resources.page.arabic'),
                        'en' => __('admin_resources.page.english'),
                    ])
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('translations', function ($q) use ($data) {
                                $q->where('locale', $data['value']);
                            });
                        }
                    }),

                SelectFilter::make('parent_id')
                    ->label(__('admin_resources.page.parent'))
                    ->relationship(
                        'parent',
                        'slug',
                        modifyQueryUsing: fn (Builder $query): Builder => self::scopeQueryToCurrentUser($query),
                    )
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
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
        return Tab::make('Metadata')->label(__('admin_resources.page.metadata'))
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make(__('admin_resources.page.page_settings'))->schema([
                    Select::make('parent_id')
                        ->label(__('admin_resources.page.parent_page'))
                        ->relationship(
                            'parent',
                            'slug',
                            modifyQueryUsing: fn (Builder $query, ?Page $record = null): Builder => self::scopeParentQueryToCurrentUser($query, $record),
                        )
                        ->searchable()
                        ->placeholder(__('admin_resources.page.none_top_level')),

                    TextInput::make('slug')
                        ->label(__('admin_resources.page.slug'))
                        ->required()
                        ->maxLength(255)
                        ->alphaDash(),

                    TextInput::make('faculty_scope_slug')
                        ->label(__('admin_resources.page.faculty_scope_slug'))
                        ->helperText(__('admin_resources.page.optional_when_set_faculty_editors_only_access_pages_matching'))
                        ->maxLength(255)
                        ->alphaDash()
                        ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),

                    Select::make('template')
                        ->label(__('admin_resources.page.template'))
                        ->options([
                            'default' => __('admin_resources.page.default'),
                            'landing' => __('admin_resources.page.landing_page'),
                            'faculty' => __('admin_resources.page.faculty'),
                            'department' => __('admin_resources.page.department'),
                        ])
                        ->required()
                        ->default('default'),

                    Select::make('status')
                        ->label(__('admin_resources.page.status'))
                        ->options([
                            'draft' => __('admin_resources.page.draft'),
                            'published' => __('admin_resources.page.published'),
                            'scheduled' => __('admin_resources.page.scheduled'),
                        ])
                        ->required()
                        ->default('draft')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText(__('admin_resources.page.use_the_publish_and_schedule_actions_to_change_public_state')),
                ]),

                Section::make(__('admin_resources.page.visibility'))->schema([
                    Toggle::make('is_enabled')
                        ->label(__('admin_resources.page.enabled'))
                        ->default(true),

                    Toggle::make('show_in_nav')
                        ->label(__('admin_resources.page.show_in_navigation'))
                        ->default(true),

                    Toggle::make('show_in_breadcrumbs')
                        ->label(__('admin_resources.page.show_in_breadcrumbs'))
                        ->default(true),
                ])->columns(3),
            ]);
    }

    private static function translationFields(string $locale): array
    {
        $prefix = $locale === 'ar' ? 'ar_' : 'en_';

        return [
            Section::make(__('admin_resources.page.content'))->schema([
                TextInput::make("{$prefix}title")
                    ->label(__('admin_resources.page.title'))
                    ->required()
                    ->maxLength(255),

                TextInput::make("{$prefix}headline")
                    ->label(__('admin_resources.page.headline'))
                    ->maxLength(255),

                TextInput::make("{$prefix}subheadline")
                    ->label(__('admin_resources.page.subheadline'))
                    ->maxLength(500),

                Textarea::make("{$prefix}hero_content")
                    ->label(__('admin_resources.page.hero_content'))
                    ->rows(3)
                    ->maxLength(2000),

                RichEditor::make("{$prefix}body")
                    ->label(__('admin_resources.page.body'))
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_resources.page.call_to_action'))->schema([
                TextInput::make("{$prefix}cta_label")
                    ->label(__('admin_resources.page.cta_label'))
                    ->maxLength(100),

                PageUrlSelect::make("{$prefix}cta_url", __('admin_resources.page.cta_url'), $locale),
            ])->columns(2),

            Section::make(__('admin_resources.page.additional'))->schema([
                Textarea::make("{$prefix}sidebar_content")
                    ->label(__('admin_resources.page.sidebar_content'))
                    ->rows(4),

                Textarea::make("{$prefix}excerpt")
                    ->label(__('admin_resources.page.excerpt'))
                    ->rows(3)
                    ->maxLength(500),
            ]),
        ];
    }

    private static function arabicTranslationTab(): Tab
    {
        return Tab::make('العربية (AR)')
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'rtl'])
            ->schema(self::translationFields('ar'));
    }

    private static function englishTranslationTab(): Tab
    {
        return Tab::make('English (EN)')->label(__('admin_resources.page.english_en'))
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema(self::translationFields('en'));
    }

    private static function seoFields(string $locale): array
    {
        $prefix = $locale === 'ar' ? 'ar_seo_' : 'en_seo_';

        return [
            Section::make(__('admin_resources.page.meta_tags'))->schema([
                TextInput::make("{$prefix}meta_title")
                    ->label(__('admin_resources.page.meta_title'))
                    ->maxLength(70),

                Textarea::make("{$prefix}meta_description")
                    ->label(__('admin_resources.page.meta_description'))
                    ->rows(3)
                    ->maxLength(160),
            ]),

            Section::make(__('admin_resources.page.open_graph'))->schema([
                TextInput::make("{$prefix}og_title")
                    ->label(__('admin_resources.page.og_title'))
                    ->maxLength(70),

                Textarea::make("{$prefix}og_description")
                    ->label(__('admin_resources.page.og_description'))
                    ->rows(3)
                    ->maxLength(200),

                MediaPicker::image("{$prefix}og_image", __('admin_resources.page.og_image')),
            ]),

            Section::make(__('admin_resources.page.advanced'))->schema([
                TextInput::make("{$prefix}canonical_url")
                    ->label(__('admin_resources.page.canonical_url'))
                    ->url()
                    ->maxLength(2048),

                TextInput::make("{$prefix}robots")
                    ->label(__('admin_resources.page.robots_directive'))
                    ->placeholder('index, follow')
                    ->maxLength(100),
            ]),
        ];
    }

    private static function arabicSeoTab(): Tab
    {
        return Tab::make('Arabic SEO')->label(__('admin_resources.page.arabic_seo'))
            ->icon('heroicon-o-magnifying-glass')
            ->extraAttributes(['dir' => 'rtl'])
            ->schema(self::seoFields('ar'));
    }

    private static function englishSeoTab(): Tab
    {
        return Tab::make('English SEO')->label(__('admin_resources.page.english_seo'))
            ->icon('heroicon-o-magnifying-glass')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema(self::seoFields('en'));
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

        $scopes = $user->isLimitedEditor() ? app(AdminAccessServiceInterface::class)->facultyScopeValues($user) : [];

        if ($scopes === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('faculty_scope_slug', $scopes);
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

        return app(PageServiceInterface::class)->invalidParentIds((int) $record->getKey());
    }
}
