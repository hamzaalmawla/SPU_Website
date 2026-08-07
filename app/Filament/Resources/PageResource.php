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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Filament\Components\PageUrlSelect;

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
                TextColumn::make('translations_title')
                    ->label('Title')
                    ->getStateUsing(function (Page $record): string {
                        $translation = $record->translations->first();

                        return $translation?->title ?? $record->slug;
                    })
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderBy(
                            DB::table('page_translations')
                                ->select('title')
                                ->whereColumn('page_translations.page_id', 'pages.id')
                                ->limit(1),
                            $direction,
                        );
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('translations', function ($q) use ($search) {
                            $q->where('title', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->limit(40),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('parent.slug')
                    ->label('Parent')
                    ->placeholder('—'),

                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                    ]),

                SelectFilter::make('locale')
                    ->label('Locale')
                    ->options([
                        'ar' => 'Arabic',
                        'en' => 'English',
                    ])
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('translations', function ($q) use ($data) {
                                $q->where('locale', $data['value']);
                            });
                        }
                    }),

                SelectFilter::make('parent_id')
                    ->label('Parent')
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
        return Tab::make('Metadata')
            ->icon('heroicon-o-cog-6-tooth')
            ->schema([
                Section::make('Page Settings')->schema([
                    Select::make('parent_id')
                        ->label('Parent Page')
                        ->relationship(
                            'parent',
                            'slug',
                            modifyQueryUsing: fn (Builder $query, ?Page $record = null): Builder => self::scopeParentQueryToCurrentUser($query, $record),
                        )
                        ->searchable()
                        ->placeholder('None (top-level)'),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash(),

                    TextInput::make('faculty_scope_slug')
                        ->label('Faculty Scope Slug')
                        ->helperText('Optional. When set, faculty editors only access pages matching their scope.')
                        ->maxLength(255)
                        ->alphaDash()
                        ->visible(fn (): bool => in_array(auth()->user()?->role_slug, ['super_admin', 'editor'], true)),

                    Select::make('template')
                        ->label('Template')
                        ->options([
                            'default' => 'Default',
                            'landing' => 'Landing Page',
                            'faculty' => 'Faculty',
                            'department' => 'Department',
                        ])
                        ->required()
                        ->default('default'),

                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                            'scheduled' => 'Scheduled',
                        ])
                        ->required()
                        ->default('draft')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Use the publish and schedule actions to change public state.'),
                ]),

                Section::make('Visibility')->schema([
                    Toggle::make('is_enabled')
                        ->label('Enabled')
                        ->default(true),

                    Toggle::make('show_in_nav')
                        ->label('Show in Navigation')
                        ->default(true),

                    Toggle::make('show_in_breadcrumbs')
                        ->label('Show in Breadcrumbs')
                        ->default(true),
                ])->columns(3),
            ]);
    }

    private static function translationFields(string $locale): array
    {
        $prefix = $locale === 'ar' ? 'ar_' : 'en_';

        return [
            Section::make('Content')->schema([
                TextInput::make("{$prefix}title")
                    ->label('Title')
                    ->required()
                    ->maxLength(255),

                TextInput::make("{$prefix}headline")
                    ->label('Headline')
                    ->maxLength(255),

                TextInput::make("{$prefix}subheadline")
                    ->label('Subheadline')
                    ->maxLength(500),

                Textarea::make("{$prefix}hero_content")
                    ->label('Hero Content')
                    ->rows(3)
                    ->maxLength(2000),

                RichEditor::make("{$prefix}body")
                    ->label('Body')
                    ->columnSpanFull(),
            ]),

            Section::make('Call to Action')->schema([
                TextInput::make("{$prefix}cta_label")
                    ->label('CTA Label')
                    ->maxLength(100),

                PageUrlSelect::make("{$prefix}cta_url", 'CTA URL', $locale),
            ])->columns(2),

            Section::make('Additional')->schema([
                Textarea::make("{$prefix}sidebar_content")
                    ->label('Sidebar Content')
                    ->rows(4),

                Textarea::make("{$prefix}excerpt")
                    ->label('Excerpt')
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
        return Tab::make('English (EN)')
            ->icon('heroicon-o-language')
            ->extraAttributes(['dir' => 'ltr'])
            ->schema(self::translationFields('en'));
    }

    private static function seoFields(string $locale): array
    {
        $prefix = $locale === 'ar' ? 'ar_seo_' : 'en_seo_';

        return [
            Section::make('Meta Tags')->schema([
                TextInput::make("{$prefix}meta_title")
                    ->label('Meta Title')
                    ->maxLength(70),

                Textarea::make("{$prefix}meta_description")
                    ->label('Meta Description')
                    ->rows(3)
                    ->maxLength(160),
            ]),

            Section::make('Open Graph')->schema([
                TextInput::make("{$prefix}og_title")
                    ->label('OG Title')
                    ->maxLength(70),

                Textarea::make("{$prefix}og_description")
                    ->label('OG Description')
                    ->rows(3)
                    ->maxLength(200),

                MediaPicker::image("{$prefix}og_image", 'OG Image'),
            ]),

            Section::make('Advanced')->schema([
                TextInput::make("{$prefix}canonical_url")
                    ->label('Canonical URL')
                    ->url()
                    ->maxLength(2048),

                TextInput::make("{$prefix}robots")
                    ->label('Robots Directive')
                    ->placeholder('index, follow')
                    ->maxLength(100),
            ]),
        ];
    }

    private static function arabicSeoTab(): Tab
    {
        return Tab::make('Arabic SEO')
            ->icon('heroicon-o-magnifying-glass')
            ->extraAttributes(['dir' => 'rtl'])
            ->schema(self::seoFields('ar'));
    }

    private static function englishSeoTab(): Tab
    {
        return Tab::make('English SEO')
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
