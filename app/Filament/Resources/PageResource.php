<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
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

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return in_array($user->role_slug, ['super_admin', 'editor', 'faculty_editor'], true);
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
                            \App\Models\PageTranslation::query()
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
                    ->relationship('parent', 'slug')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
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
                        ->relationship('parent', 'slug')
                        ->searchable()
                        ->preload()
                        ->placeholder('None (top-level)'),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash(),

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
                        ->default('draft'),
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

                TextInput::make("{$prefix}cta_url")
                    ->label('CTA URL')
                    ->url()
                    ->maxLength(2048),
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
            ->schema(self::translationFields('ar'));
    }

    private static function englishTranslationTab(): Tab
    {
        return Tab::make('English (EN)')
            ->icon('heroicon-o-language')
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

                TextInput::make("{$prefix}og_image")
                    ->label('OG Image URL')
                    ->url()
                    ->maxLength(2048),
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
            ->schema(self::seoFields('ar'));
    }

    private static function englishSeoTab(): Tab
    {
        return Tab::make('English SEO')
            ->icon('heroicon-o-magnifying-glass')
            ->schema(self::seoFields('en'));
    }
}
