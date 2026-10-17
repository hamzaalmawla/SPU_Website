<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Research\ResearchPageServiceInterface;
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
            'research_studies' => [
                Section::make(__('admin.homepage_selection.research_heading'))
                    ->description(__('admin.homepage_selection.research_help'))
                    ->schema([
                        Repeater::make("{$prefix}.publication_slugs")
                            ->label(__('admin.homepage_selection.selected_research'))
                            ->schema([
                                Select::make('publication_slug')
                                    ->label(__('admin.homepage_selection.research_publication'))
                                    ->options(fn (): array => [])
                                    ->searchable()
                                    ->preload(false)
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
            Section::make(__('admin_pages.homepage_form.hero_content'))->schema([
                self::mediaField("{$prefix}.background_image", __('admin_pages.homepage_form.background_image')),
                Repeater::make("{$prefix}.content.images")
                    ->label(__('admin_pages.homepage_form.hero_carousel_images'))
                    ->schema([
                        self::mediaField('path', __('admin_pages.homepage_form.image'), true),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed()
                    ->defaultItems(0),
                TextInput::make("{$prefix}.video_url")
                    ->label(__('admin_pages.homepage_form.video_url'))
                    ->maxLength(2048),
                TextInput::make("{$prefix}.headline")
                    ->label(__('admin_pages.homepage_form.headline'))
                    ->maxLength(255),
                Textarea::make("{$prefix}.subheadline")
                    ->label(__('admin_pages.homepage_form.subheadline'))
                    ->rows(3)
                    ->maxLength(500),
            ]),
            Section::make(__('admin_pages.homepage_form.call_to_action'))->schema([
                TextInput::make("{$prefix}.primary_cta_label")
                    ->label(__('admin_pages.homepage_form.primary_cta_label'))
                    ->maxLength(100),
                PageUrlSelect::make("{$prefix}.primary_cta_url", __('admin_pages.homepage_form.primary_cta_url'), self::localeFromPrefix($prefix)),
                TextInput::make("{$prefix}.secondary_cta_label")
                    ->label(__('admin_pages.homepage_form.secondary_cta_label'))
                    ->maxLength(100),
                PageUrlSelect::make("{$prefix}.secondary_cta_url", __('admin_pages.homepage_form.secondary_cta_url'), self::localeFromPrefix($prefix)),
            ]),
        ];
    }

    /** @return array<int, Component> */
    public static function heroStatsFields(string $prefix): array
    {
        return [
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->required()
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.stats")
                ->label(__('admin_pages.homepage_form.statistics'))
                ->schema([
                    TextInput::make('value')
                        ->label(__('admin_pages.homepage_form.value'))
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label(__('admin_pages.homepage_form.label'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label(__('admin_pages.homepage_form.suffix'))
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label(__('admin_pages.homepage_form.prefix'))
                        ->maxLength(20),
                    // The grey line under the label on the public card.
                    TextInput::make('helperText')
                        ->label(__('admin_pages.homepage_form.description'))
                        ->helperText(__('admin_pages.homepage_form.short_grey_line_shown_under_the_label'))
                        ->maxLength(255)
                        ->columnSpan(2),
                    self::mediaField('icon', __('admin_pages.homepage_form.icon')),
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
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
                TextInput::make("{$prefix}.subtitle")
                    ->label(__('admin_pages.homepage_form.subtitle'))
                    ->maxLength(500),
                ...self::sectionActionFields($prefix),
            ]),
            Repeater::make("{$prefix}.featured_items")
                ->label(__('admin_pages.homepage_form.faculty_cards'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin_pages.homepage_form.title'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(__('admin_pages.homepage_form.description'))
                        ->rows(2)
                        ->maxLength(500),
                    self::mediaField('image', __('admin_pages.homepage_form.image')),
                    self::mediaField('icon', __('admin_pages.homepage_form.icon')),
                    TextInput::make('accent')
                        ->label(__('admin_pages.homepage_form.accent_color'))
                        ->maxLength(30),
                    TextInput::make('metric')
                        ->label(__('admin_pages.homepage_form.metric'))
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label(__('admin_pages.homepage_form.cta_label'))
                        ->required()
                        ->maxLength(100),
                    PageUrlSelect::make('cta_url', __('admin_pages.homepage_form.cta_url'), self::localeFromPrefix($prefix), true),
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
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
                TextInput::make("{$prefix}.subtitle")
                    ->label(__('admin_pages.homepage_form.subtitle'))
                    ->maxLength(500),
                ...self::sectionActionFields($prefix),
            ]),
        ];
    }

    /** @return array<int, Component> */
    public static function chooseYourPathFields(string $prefix): array
    {
        $locale = str_ends_with($prefix, '.ar') ? 'ar' : 'en';

        return [
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.path_items")
                ->label(__('admin_pages.homepage_form.path_cards'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin_pages.homepage_form.title'))
                        ->required()
                        ->maxLength(255),
                    self::mediaField('icon', __('admin_pages.homepage_form.icon')),
                    Repeater::make('links')
                        ->label(__('admin_pages.homepage_form.quick_links'))
                        ->schema([
                            TextInput::make('label')
                                ->label(__('admin_pages.homepage_form.link_label'))
                                ->required()
                                ->maxLength(255),
                            PageUrlSelect::make('url', __('admin_pages.homepage_form.link_page'), $locale)
                                ->placeholder(__('admin_pages.homepage_form.none_text_only_link'))
                                ->helperText(__('admin_pages.homepage_form.search_and_pick_an_internal_page_top_level_or_subpage_leave')),
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
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
                ...self::sectionActionFields($prefix),
            ]),
        ];
    }

    /** @return array<int, Component> */
    public static function researchStudiesFields(string $prefix): array
    {
        return [
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
                ...self::sectionActionFields($prefix),
            ]),
        ];
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
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
                TextInput::make("{$prefix}.content.event_cta_label")
                    ->label(__('admin_pages.homepage_form.event_cta_label'))
                    ->maxLength(100),
            ]),
            Repeater::make("{$prefix}.events")
                ->label(__('admin_pages.homepage_form.event_cards'))
                ->schema([
                    self::mediaField('image', __('admin_pages.homepage_form.image')),
                    TextInput::make('title')
                        ->label(__('admin_pages.homepage_form.title'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('date')
                        ->label(__('admin_pages.homepage_form.date'))
                        ->maxLength(50),
                    TextInput::make('time')
                        ->label(__('admin_pages.homepage_form.time'))
                        ->maxLength(50),
                    TextInput::make('location')
                        ->label(__('admin_pages.homepage_form.location'))
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(__('admin_pages.homepage_form.description'))
                        ->rows(2)
                        ->maxLength(500),
                    PageUrlSelect::make('cta_url', __('admin_pages.homepage_form.cta_url'), self::localeFromPrefix($prefix)),
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
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.items")
                ->label(__('admin_pages.homepage_form.service_cards'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin_pages.homepage_form.title'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(__('admin_pages.homepage_form.description'))
                        ->rows(2)
                        ->maxLength(500),
                    self::mediaField('image', __('admin_pages.homepage_form.image')),
                    TextInput::make('cta_label')
                        ->label(__('admin_pages.homepage_form.cta_label'))
                        ->maxLength(100),
                    PageUrlSelect::make('cta_url', __('admin_pages.homepage_form.cta_url'), self::localeFromPrefix($prefix)),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.stats")
                ->label(__('admin_pages.homepage_form.facility_statistics'))
                ->helperText(__('admin_pages.homepage_form.shown_in_one_row_on_the_homepage_up_to_6_fit_side_by_side'))
                ->maxItems(6)
                ->schema([
                    TextInput::make('value')
                        ->label(__('admin_pages.homepage_form.value'))
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label(__('admin_pages.homepage_form.label'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label(__('admin_pages.homepage_form.suffix'))
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label(__('admin_pages.homepage_form.prefix'))
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
            Section::make(__('admin_pages.homepage_form.section_header'))->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label(__('admin_pages.homepage_form.section_title'))
                    ->required()
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.stats")
                ->label(__('admin_pages.homepage_form.statistics'))
                ->schema([
                    TextInput::make('value')
                        ->label(__('admin_pages.homepage_form.value'))
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label(__('admin_pages.homepage_form.label'))
                        ->required()
                        ->maxLength(100),
                    TextInput::make('suffix')
                        ->label(__('admin_pages.homepage_form.suffix'))
                        ->maxLength(20),
                    TextInput::make('prefix')
                        ->label(__('admin_pages.homepage_form.prefix'))
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
            Section::make(__('admin_pages.homepage_form.brand_contact'))->schema([
                TextInput::make("{$prefix}.brand_title")
                    ->label(__('admin_pages.homepage_form.brand_title'))
                    ->required()
                    ->maxLength(255),
                self::mediaField("{$prefix}.logo", __('admin_pages.homepage_form.footer_logo')),
                TextInput::make("{$prefix}.content.contact_phone")
                    ->label(__('admin_pages.homepage_form.contact_phone'))
                    ->tel()
                    ->maxLength(50),
                TextInput::make("{$prefix}.content.contact_email")
                    ->label(__('admin_pages.homepage_form.contact_email'))
                    ->email()
                    ->maxLength(255),
                Textarea::make("{$prefix}.content.contact_address")
                    ->label(__('admin_pages.homepage_form.contact_address'))
                    ->rows(2)
                    ->maxLength(500),
            ]),
            Repeater::make("{$prefix}.social_links")
                ->label(__('admin_pages.homepage_form.social_links'))
                ->schema([
                    TextInput::make('platform')
                        ->label(__('admin_pages.homepage_form.platform'))
                        ->required()
                        ->maxLength(50),
                    PageUrlSelect::make('url', __('admin_pages.homepage_form.url'), self::localeFromPrefix($prefix), true),
                    self::mediaField('icon', __('admin_pages.homepage_form.icon')),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.contact_links")
                ->label(__('admin_pages.homepage_form.contact_links'))
                ->schema([
                    TextInput::make('type')
                        ->label(__('admin_pages.homepage_form.type'))
                        ->required()
                        ->maxLength(50),
                    TextInput::make('label')
                        ->label(__('admin_pages.homepage_form.label'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('value')
                        ->label(__('admin_pages.homepage_form.value'))
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Repeater::make("{$prefix}.footer_columns")
                ->label(__('admin_pages.homepage_form.navigation_groups'))
                ->schema([
                    TextInput::make('title')
                        ->label(__('admin_pages.homepage_form.group_title'))
                        ->required()
                        ->maxLength(255),
                    Repeater::make('links')
                        ->label(__('admin_pages.homepage_form.links'))
                        ->schema([
                            TextInput::make('label')
                                ->label(__('admin_pages.homepage_form.label'))
                                ->required()
                                ->maxLength(255),
                            PageUrlSelect::make('url', __('admin_pages.homepage_form.url'), self::localeFromPrefix($prefix), true),
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
                ->label(__('admin_pages.homepage_form.legal_links'))
                ->schema([
                    TextInput::make('label')
                        ->label(__('admin_pages.homepage_form.label'))
                        ->required()
                        ->maxLength(255),
                    PageUrlSelect::make('url', __('admin_pages.homepage_form.url'), self::localeFromPrefix($prefix), true),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->defaultItems(0),
            Section::make(__('admin_pages.homepage_form.copyright'))->schema([
                TextInput::make("{$prefix}.copyright_text")
                    ->label(__('admin_pages.homepage_form.copyright_text'))
                    ->maxLength(500),
            ]),
        ];
    }

    /** @return array<int, Component> */
    private static function sectionActionFields(string $prefix): array
    {
        return [
            TextInput::make("{$prefix}.section_cta_label")
                ->label(__('admin_pages.homepage_form.section_cta_label'))
                ->maxLength(100),
            PageUrlSelect::make("{$prefix}.section_cta_url", __('admin_pages.homepage_form.section_cta_url'), self::localeFromPrefix($prefix)),
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
