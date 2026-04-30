<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
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
     * @return array<int, \Filament\Forms\Components\Component>
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
            'bottom_stats' => self::bottomStatsFields($prefix),
            'footer' => self::footerFields($prefix),
        };
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function heroFields(string $prefix): array
    {
        return [
            Section::make('Hero Content')->schema([
                FileUpload::make("{$prefix}.background_image")
                    ->label('Background Image')
                    ->image()
                    ->directory('homepage/hero')
                    ->maxSize(5120),
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
                TextInput::make("{$prefix}.primary_cta_url")
                    ->label('Primary CTA URL')
                    ->maxLength(2048),
                TextInput::make("{$prefix}.secondary_cta_label")
                    ->label('Secondary CTA Label')
                    ->maxLength(100),
                TextInput::make("{$prefix}.secondary_cta_url")
                    ->label('Secondary CTA URL')
                    ->maxLength(2048),
            ]),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function heroStatsFields(string $prefix): array
    {
        return [
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
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                ])
                ->columns(3)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
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
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
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
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                    TextInput::make('metric')
                        ->label('Metric')
                        ->maxLength(100),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function chooseYourPathFields(string $prefix): array
    {
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
                    TextInput::make('icon')
                        ->label('Icon Path')
                        ->maxLength(500),
                    Repeater::make('links')
                        ->label('Quick Links')
                        ->schema([
                            TextInput::make('label')
                                ->label('Link Label')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->defaultItems(0)
                        ->collapsible(),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function universityNewsFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.articles")
                ->label('News Cards')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/news'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('excerpt')
                        ->label('Excerpt')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('publish_date')
                        ->label('Publish Date')
                        ->maxLength(50),
                    TextInput::make('category')
                        ->label('Category')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function researchStudiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.research_items")
                ->label('Research Cards')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/research'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('excerpt')
                        ->label('Excerpt')
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('publish_date')
                        ->label('Publish Date')
                        ->maxLength(50),
                    TextInput::make('category')
                        ->label('Category')
                        ->maxLength(100),
                    TextInput::make('authors')
                        ->label('Authors')
                        ->maxLength(500),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function eventsActivitiesFields(string $prefix): array
    {
        return [
            Section::make('Section Header')->schema([
                TextInput::make("{$prefix}.section_title")
                    ->label('Section Title')
                    ->maxLength(255),
            ]),
            Repeater::make("{$prefix}.events")
                ->label('Event Cards')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/events'),
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
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
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
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->directory('homepage/medical'),
                    TextInput::make('cta_label')
                        ->label('CTA Label')
                        ->maxLength(100),
                    TextInput::make('cta_url')
                        ->label('CTA URL')
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function bottomStatsFields(string $prefix): array
    {
        return [
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
                ->defaultItems(0),
        ];
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    public static function footerFields(string $prefix): array
    {
        return [
            Section::make('Brand & Contact')->schema([
                FileUpload::make("{$prefix}.logo")
                    ->label('Footer Logo')
                    ->image()
                    ->directory('homepage/footer'),
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
                    TextInput::make('url')
                        ->label('URL')
                        ->required()
                        ->maxLength(2048),
                    TextInput::make('icon')
                        ->label('Icon')
                        ->maxLength(100),
                ])
                ->columns(3)
                ->collapsible()
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
                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(2048),
                        ])
                        ->columns(2)
                        ->defaultItems(0),
                ])
                ->collapsible()
                ->defaultItems(0),
            Repeater::make("{$prefix}.content.legal_links")
                ->label('Legal Links')
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('url')
                        ->label('URL')
                        ->required()
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->collapsible()
                ->defaultItems(0),
            Section::make('Copyright')->schema([
                TextInput::make("{$prefix}.copyright_text")
                    ->label('Copyright Text')
                    ->maxLength(500),
            ]),
        ];
    }
}
