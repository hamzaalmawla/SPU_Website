<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\DTOs\Content\ArticleCardDTO;
use App\DTOs\Content\ResearchCardDTO;
use App\Filament\Components\PageUrlSelect;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Extracted form field schemas for the 11 fixed homepage sections.
 *
 * Each static method returns an array of Filament form components
 * for a given section, using the provided $prefix for field naming.
 */
final class HomepageFormSchema
{
    /**
     * Dispatch to the correct section field builder by key.
     *
     * @return array<int, Component>
     */
    public static function fieldsForSection(string $sectionKey, string $prefix): array
    {
        return match ($sectionKey) {
            'hero' => self::heroFields($prefix),
            'hero_stats' => self::heroStatsFields($prefix),
            'academic_faculties' => self::academicFacultiesFields($prefix),
            'achievements_highlights' => self::achievementsHighlightsFields($prefix),
            'choose_your_path' => self::chooseYourPathFields($prefix),
            'university_news' => self::universityNewsFields($prefix),
            'research_studies' => self::researchStudiesFields($prefix),
            'events_activities' => self::eventsActivitiesFields($prefix),
            'medical_facilities_services' => self::medicalFacilitiesFields($prefix),
            'bottom_stats' => self::heroStatsFields($prefix),
            'footer' => self::footerFields($prefix),
        };
    }

    /** @return array<int, Component> */
    public static function selectionFieldsForSection(string $sectionKey, string $prefix): array
    {
        return match ($sectionKey) {
            'university_news' => [
                Section::make(__('admin.homepage_selection.news_heading'))
                    ->description(__('admin.homepage_selection.news_help'))
                    ->schema([
                        Repeater::make("{$prefix}.article_ids")
                            ->label(__('admin.homepage_selection.selected_news'))
                            ->schema([
                                Select::make('article_id')
                                    ->label(__('admin.homepage_selection.news_article'))
                                    ->options(fn (): array => self::newsOptions(''))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->getSearchResultsUsing(fn (string $search): array => self::newsOptions($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::newsOptionLabel($value)),
                            ])
                            ->minItems(1)
                            ->maxItems(8)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => self::newsOptionLabel($state['article_id'] ?? null)),
                    ]),
            ],
            'research_studies' => [
                Section::make(__('admin.homepage_selection.research_heading'))
                    ->description(__('admin.homepage_selection.research_help'))
                    ->schema([
                        Repeater::make("{$prefix}.publication_slugs")
                            ->label(__('admin.homepage_selection.selected_research'))
                            ->schema([
                                Select::make('publication_slug')
                                    ->label(__('admin.homepage_selection.research_publication'))
                                    ->options(fn (): array => self::researchOptions(''))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->getSearchResultsUsing(fn (string $search): array => self::researchOptions($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::researchOptionLabel($value)),
                            ])
                            ->minItems(1)
                            ->maxItems(10)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => self::researchOptionLabel($state['publication_slug'] ?? null)),
                    ]),
            ],
            default => [],
        };
    }

    /** @return array<int, Component> */
    public static function heroFields(string $prefix): array
    {
        return [
            Section::make('Hero Content')->schema([
                self::mediaField("{$prefix}.background_image", 'Background Image'),
                Repeater::make("{$prefix}.content.images")
                    ->label('Hero Carousel Images')
                    ->schema([
                        self::mediaField('path', 'Image', true),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed()
                    ->defaultItems(0),
                TextInput::make("{$prefix}.video_url")
                    ->label('Video URL')
                    ->maxLength(2048),
                TextInput::make("{$prefix}.headline")
                    ->label('Headline')
                    ->maxLength(255),
                Textarea::make("{$prefix}.subheadline")
                    ->label('Subheadline')
                    ->rows(3)
                    ->maxLength(500),
            ]),
            Section::make('Call to Action')->schema([
                TextInput::make("{$prefix}.primary_cta_label")
                    ->label('Primary CTA Label')
                    ->maxLength(100),
                PageUrlSelect::make("{$prefix}.primary_cta_url", 'Primary CTA URL', self::localeFromPrefix($prefix)),
                TextInput::make("{$prefix}.secondary_cta_label")
                    ->label('Secondary CTA Label')
                    ->maxLength(100),
                PageUrlSelect::make("{$prefix}.secondary_cta_url", 'Secondary CTA URL', self::localeFromPrefix($prefix)),
            ]),
        ];
    }

    /** @return array<int, Component> */
    public static function heroStatsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->required()
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.stats")
                ->label('Statistics')
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->maxLength(20),
                    self::mediaField('icon', 'Icon'),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function academicFacultiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                TextInput::make("{$prefix}.subtitle")
                    ->label('Subtitle')
                    ->maxLength(500),
                ...self::sectionActionFields($prefix),
            ]),
            Repeater::make("{$prefix}.featured_items")
                ->label('Faculty Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    self::mediaField('image', 'Image'),
                    self::mediaField('icon', 'Icon'),
                    TextInput::make('accent')
                        ->label('Accent Color')
                        ->maxLength(30),
                    TextInput::make('metric')
                        ->label('Metric')
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->required()
                        ->maxLength(100),
                    PageUrlSelect::make('cta_url', 'CTA URL', self::localeFromPrefix($prefix), true),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function achievementsHighlightsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                TextInput::make("{$prefix}.subtitle")
                    ->label('Subtitle')
                    ->maxLength(500),
            ]),
            Repeater::make("{$prefix}.featured_items")
                ->label('Highlight Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('text')
                        ->label('Text')
                        ->rows(2)
                        ->maxLength(500),
                    self::mediaField('image', 'Image'),
                    self::mediaField('icon', 'Icon'),
                    TextInput::make('metric')
                        ->label('Metric')
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->required()
                        ->maxLength(100),
                    PageUrlSelect::make('cta_url', 'CTA URL', self::localeFromPrefix($prefix), true),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function chooseYourPathFields(string $prefix): array
    {
        $locale = str_ends_with($prefix, '.ar') ? 'ar' : 'en';

        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.path_items")
                ->label('Path Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    self::mediaField('icon', 'Icon'),
                    Repeater::make('links')
                        ->label('Quick Links')
                        ->schema([
                            TextInput::make('label')
                                ->label('Link Label')
                                ->required()
                                ->maxLength(255),
                            PageUrlSelect::make('url', 'Link Page', $locale)
                                ->placeholder('None (text-only link)')
                                ->helperText('Search and pick an internal page (top-level or subpage). Leave empty for a text-only link.'),
                        ])
                        ->defaultItems(0)
                        ->collapsible()
                        ->collapsed(),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function universityNewsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                ...self::sectionActionFields($prefix),
            ]),
        ];
    }

    /** @return array<int, Component> */
    public static function researchStudiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                ...self::sectionActionFields($prefix),
            ]),
        ];
    }

    /** @return array<int|string, string> */
    private static function newsOptions(string $search): array
    {
        return app(NewsServiceInterface::class)
            ->getHomepageArticleCards(app()->getLocale(), [], $search, 50)
            ->mapWithKeys(fn (ArticleCardDTO $card): array => [$card->id => $card->title])
            ->all();
    }

    private static function newsOptionLabel(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return app(NewsServiceInterface::class)
            ->getHomepageArticleCards(app()->getLocale(), [(int) $value], null, 1)
            ->first()?->title;
    }

    /** @return array<string, string> */
    private static function researchOptions(string $search): array
    {
        return app(ResearchPageServiceInterface::class)
            ->getHomepagePublicationCards(app()->getLocale(), [], $search, 50)
            ->mapWithKeys(fn (ResearchCardDTO $card): array => [$card->slug => $card->title])
            ->all();
    }

    private static function researchOptionLabel(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return app(ResearchPageServiceInterface::class)
            ->getHomepagePublicationCards(app()->getLocale(), [$value], null, 1)
            ->first()?->title;
    }

    /** @return array<int, Component> */
    public static function eventsActivitiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
                TextInput::make("{$prefix}.content.event_cta_label")
                    ->label('Event CTA Label')
                    ->maxLength(100),
            ]),
            Repeater::make("{$prefix}.events")
                ->label('Event Cards')
                ->schema([
                    self::mediaField('image', 'Image'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('date')
                        ->label('Date')
                        ->maxLength(50),
                    TextInput::make('time')
                        ->label('Time')
                        ->maxLength(50),
                    TextInput::make('location')
                        ->label('Location')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    PageUrlSelect::make('cta_url', 'CTA URL', self::localeFromPrefix($prefix)),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function medicalFacilitiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.items")
                ->label('Service Cards')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength(500),
                    self::mediaField('image', 'Image'),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    PageUrlSelect::make('cta_url', 'CTA URL', self::localeFromPrefix($prefix)),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.stats")
                ->label('Facility Statistics')
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->maxLength(20),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function bottomStatsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->required()
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.stats")
                ->label('Statistics')
                ->schema([
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label('Prefix')
                        ->maxLength(20),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, Component> */
    public static function footerFields(string $prefix): array
    {
        return [
            Section::make('Brand & Contact')->schema([
                TextInput::make("{$prefix}.brand_title")
                    ->label('Brand Title')
                    ->required()
                    ->maxLength(255),
                self::mediaField("{$prefix}.logo", 'Footer Logo'),
                TextInput::make("{$prefix}.content.contact_phone")
                    ->label('Contact Phone')
                    ->tel()
                    ->maxLength(50),
                TextInput::make("{$prefix}.content.contact_email")
                    ->label('Contact Email')
                    ->email()
                    ->maxLength(255),
                Textarea::make("{$prefix}.content.contact_address")
                    ->label('Contact Address')
                    ->rows(2)
                    ->maxLength(500),
            ]),
            Repeater::make("{$prefix}.social_links")
                ->label('Social Links')
                ->schema([
                    TextInput::make('platform')
                        ->label('Platform')
                        ->required()
                        ->maxLength(50),
                            PageUrlSelect::make('url', 'URL', self::localeFromPrefix($prefix), true),
                    self::mediaField('icon', 'Icon'),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.contact_links")
                ->label('Contact Links')
                ->schema([
                    TextInput::make('type')
                        ->label('Type')
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('value')
                        ->label('Value')
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.footer_columns")
                ->label('Navigation Groups')
                ->schema([
                    TextInput::make('title')
                        ->label('Group Title')
                        ->required()
                        ->maxLength(255),
                    Repeater::make('links')
                        ->label('Links')
                        ->schema([
                            TextInput::make('label')
                                ->label('Label')
                                ->required()
                                ->maxLength(255),
                            PageUrlSelect::make('url', 'URL', self::localeFromPrefix($prefix), true),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->collapsed()
                        ->defaultItems(0),
                ])
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.content.legal_links")
                ->label('Legal Links')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    PageUrlSelect::make('url', 'URL', self::localeFromPrefix($prefix), true),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Section::make('Copyright')->schema([
                TextInput::make("{$prefix}.copyright_text")
                    ->label('Copyright Text')
                    ->maxLength(500),
            ]),
        ];
    }

    /** @return array<int, Component> */
    private static function sectionActionFields(string $prefix): array
    {
        return [
            TextInput::make("{$prefix}.section_cta_label")
                ->label('Section CTA Label')
                ->maxLength(100),
            PageUrlSelect::make("{$prefix}.section_cta_url", 'Section CTA URL', self::localeFromPrefix($prefix)),
        ];
    }

    private static function localeFromPrefix(string $prefix): string
    {
        $locale = last(explode('.', $prefix));

        return in_array($locale, ['ar', 'en'], true) ? $locale : app()->getLocale();
    }

    private static function mediaField(string $name, string $label, bool $required = false): Component
    {
        return MediaPicker::lightImage($name, $label, $required);
    }
}
