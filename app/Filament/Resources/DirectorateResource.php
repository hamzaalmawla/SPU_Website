<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesThroughPermissions;
use App\Filament\Resources\DirectorateResource\Pages;
use App\Filament\Support\CouncilEditor;
use App\Filament\Support\FormPicker;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\StatusBadge;
use App\Models\Content\Directorate;
use App\Support\DirectorateContentBlocks;
use App\Support\DirectorateManagerFields;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DirectorateResource extends Resource
{
    use AuthorizesThroughPermissions;

    protected static ?string $model = Directorate::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 14;

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof Directorate) {
            return self::getModelLabel();
        }

        $translation = $record->translations->first();

        return filled($translation?->title) ? trim((string) $translation->title) : $record->slug;
    }

    protected static function permissionComponent(): string
    {
        return 'directorates';
    }

    public static function getModelLabel(): string
    {
        return __('admin_resources.directorate.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_resources.directorate.plural_model');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.site_sections');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.directorates');
    }

    public static function getEloquentQuery(): EloquentBuilder
    {
        return parent::getEloquentQuery()->with('translations');
    }

    public static function form(Form $form): Form
    {
        $managerIsStaff = static fn (Get $get): bool => (bool) $get('manager_is_staff');

        return $form->schema([
            Section::make(__('admin_resources.directorate.directorate'))->schema([
                TextInput::make('slug')->required()->maxLength(255)->alphaDash(),
                MediaPicker::icon('icon', 'Icon'),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('location')->maxLength(255),
                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_enabled')->default(true),
            ])->columns(2),
            // The page's main section. Most managers are not teaching staff, so
            // their details are typed here (name and education per language,
            // below); a manager who has a staff profile is picked from the list.
            Section::make(__('admin_resources.directorate.manager_section'))
                ->description(__('admin_resources.directorate.manager_section_help'))
                ->schema([
                    Toggle::make('manager_is_staff')
                        ->label(__('admin_resources.directorate.manager_is_staff'))
                        ->helperText(__('admin_resources.directorate.manager_is_staff_help'))
                        ->live()
                        ->afterStateHydrated(fn (Toggle $component, Get $get): mixed => $component->state(is_numeric($get('manager_member_id'))))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Select::make('manager_member_id')
                        ->label(__('admin_resources.directorate.manager_profile'))
                        ->options(fn (): array => CouncilEditor::staffOptions())
                        ->searchable()
                        ->live()
                        ->visible($managerIsStaff)
                        ->required($managerIsStaff)
                        ->columnSpanFull(),
                    Placeholder::make('manager_profile_preview')
                        ->hiddenLabel()
                        ->content(fn (Get $get): HtmlString => CouncilEditor::staffPreview($get('manager_member_id')))
                        ->visible(fn (Get $get): bool => (bool) $get('manager_is_staff') && is_numeric($get('manager_member_id')))
                        ->columnSpanFull(),
                    TextInput::make('manager_email')
                        ->label(__('admin_resources.directorate.manager_email'))
                        ->helperText(fn (Get $get): ?string => $get('manager_is_staff') ? __('admin_resources.directorate.manager_email_staff_help') : null)
                        ->email()
                        ->maxLength(255),
                    TextInput::make('manager_phone')->label(__('admin_resources.directorate.manager_phone'))->tel()->maxLength(50),
                    MediaPicker::image('manager_photo', __('admin_resources.directorate.manager_photo'))
                        ->visible(fn (Get $get): bool => ! $get('manager_is_staff')),
                ])->columns(2),
            Repeater::make('translations')->schema([
                Select::make('locale')->required()->options(['ar' => __('admin_resources.directorate.arabic'), 'en' => __('admin_resources.directorate.english')])->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('summary')->rows(3),
                Textarea::make('description')->rows(5),
                TagsInput::make('services_json')
                    ->label(__('admin_resources.directorate.services'))
                    ->helperText(__('admin_resources.directorate.services_help'))
                    ->columnSpanFull(),
                TextInput::make('manager_name')
                    ->label(__('admin_resources.directorate.manager_name'))
                    ->helperText(__('admin_resources.directorate.manager_name_help'))
                    ->visible(fn (Get $get): bool => ! $get('../../manager_is_staff'))
                    ->maxLength(255),
                Textarea::make('manager_education_json')
                    ->label(__('admin_resources.directorate.manager_education'))
                    ->helperText(__('admin_resources.directorate.manager_education_help'))
                    ->rows(3)
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? implode("\n", array_filter($state, 'is_string')) : (is_string($state) ? $state : ''))
                    ->dehydrateStateUsing(fn (mixed $state): array => DirectorateManagerFields::education($state)),
                Repeater::make('task_groups_json')
                    ->label(__('admin_resources.directorate.task_groups'))
                    ->helperText(__('admin_resources.directorate.task_groups_help'))
                    ->schema([
                        TextInput::make('title')->label(__('admin_resources.directorate.task_group_title'))->required()->maxLength(255),
                        Textarea::make('summary')->label(__('admin_resources.directorate.task_group_summary'))->rows(2),
                        Repeater::make('items')
                            ->label(__('admin_resources.directorate.tasks'))
                            ->schema([
                                TextInput::make('title')->label(__('admin_resources.directorate.task_title'))->required()->maxLength(255),
                                Textarea::make('body')->label(__('admin_resources.directorate.task_body'))->rows(2),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? (string) $state['title'] : null)
                            ->addActionLabel(__('admin_resources.directorate.add_task'))
                            ->defaultItems(0),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null) ? (string) $state['title'] : null)
                    ->addActionLabel(__('admin_resources.directorate.add_task_group'))
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])->columns(2)->default([['locale' => 'ar'], ['locale' => 'en']])->minItems(2)->maxItems(2)->columnSpanFull(),
            Section::make(__('admin_resources.directorate.blocks_section'))
                ->description(__('admin_resources.directorate.blocks_section_help'))
                ->schema([self::contentBlocks()]),
        ]);
    }

    /**
     * The page body, built freely from blocks in any order: each block holds
     * its Arabic and English text side by side (English left empty shows the
     * Arabic). Stored as App\Support\DirectorateContentBlocks.
     */
    private static function contentBlocks(): Builder
    {
        $opensForm = static fn (Get $get): bool => $get('link') === DirectorateContentBlocks::LINK_FORM;

        return Builder::make('content_blocks_json')
            ->hiddenLabel()
            ->blocks([
                Block::make('heading')
                    ->label(fn (?array $state): string => self::blockLabel('heading', $state['text_ar'] ?? $state['text_en'] ?? null))
                    ->icon('heroicon-o-bookmark')
                    ->schema([
                        TextInput::make('text_ar')->label(__('admin_resources.directorate.block_heading_ar'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('text_en')->label(__('admin_resources.directorate.block_heading_en'))->maxLength(255)->extraInputAttributes(['dir' => 'ltr']),
                    ])
                    ->columns(2),
                Block::make('text')
                    ->label(fn (?array $state): string => self::blockLabel('text', $state['body_ar'] ?? $state['body_en'] ?? null))
                    ->icon('heroicon-o-bars-3-bottom-left')
                    ->schema([
                        Textarea::make('body_ar')->label(__('admin_resources.directorate.block_text_ar'))->helperText(__('admin_resources.directorate.block_text_help'))->required()->rows(6)->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('body_en')->label(__('admin_resources.directorate.block_text_en'))->rows(6)->extraInputAttributes(['dir' => 'ltr']),
                    ])
                    ->columns(2),
                Block::make('image')
                    ->label(fn (?array $state): string => self::blockLabel('image', $state['caption_ar'] ?? $state['alt_ar'] ?? null))
                    ->icon('heroicon-o-photo')
                    ->schema([
                        MediaPicker::image('image', __('admin_resources.directorate.block_image'), true)->columnSpanFull(),
                        TextInput::make('alt_ar')->label(__('admin_resources.directorate.block_image_alt_ar'))->helperText(__('admin_resources.directorate.block_image_alt_help'))->maxLength(255),
                        TextInput::make('alt_en')->label(__('admin_resources.directorate.block_image_alt_en'))->maxLength(255),
                        TextInput::make('caption_ar')->label(__('admin_resources.directorate.block_image_caption_ar'))->maxLength(500),
                        TextInput::make('caption_en')->label(__('admin_resources.directorate.block_image_caption_en'))->maxLength(500),
                    ])
                    ->columns(2),
                Block::make('button')
                    ->label(fn (?array $state): string => self::blockLabel('button', $state['label_ar'] ?? $state['label_en'] ?? null))
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->schema([
                        TextInput::make('label_ar')->label(__('admin_resources.directorate.block_button_label_ar'))->required()->maxLength(120),
                        TextInput::make('label_en')->label(__('admin_resources.directorate.block_button_label_en'))->maxLength(120),
                        Radio::make('link')
                            ->label(__('admin_resources.directorate.block_button_link'))
                            ->options([
                                DirectorateContentBlocks::LINK_URL => __('admin_resources.directorate.block_button_link_url'),
                                DirectorateContentBlocks::LINK_FORM => __('admin_resources.directorate.block_button_link_form'),
                            ])
                            ->default(DirectorateContentBlocks::LINK_URL)
                            ->inline()
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('url')
                            ->label(__('admin_resources.directorate.block_button_url'))
                            ->helperText(__('admin_resources.directorate.block_button_url_help'))
                            ->placeholder('https://')
                            ->maxLength(2048)
                            ->visible(fn (Get $get): bool => ! $opensForm($get))
                            ->required(fn (Get $get): bool => ! $opensForm($get))
                            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                if (is_string($value) && trim($value) !== '' && DirectorateContentBlocks::safeUrl($value) === null) {
                                    $fail(__('admin_resources.directorate.block_button_url_invalid'));
                                }
                            })
                            ->columnSpanFull(),
                        FormPicker::make('formId')
                            ->label(__('admin_resources.directorate.block_button_form'))
                            ->visible($opensForm)
                            ->required($opensForm)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->addActionLabel(__('admin_resources.directorate.add_block'))
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->default([])
            ->columnSpanFull();
    }

    private static function blockLabel(string $type, mixed $text): string
    {
        $label = __('admin_resources.directorate.block_'.$type);
        $text = is_string($text) ? trim(preg_replace('/\s+/u', ' ', $text) ?? '') : '';

        return $text !== '' ? $label.' · '.Str::limit($text, 60) : $label;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slug')->searchable()->sortable(),
            TextColumn::make('translations.title')
                ->label(__('admin_resources.directorate.titles'))
                ->searchable(query: fn (EloquentBuilder $query, string $search): EloquentBuilder => $query->whereHas(
                    'translations',
                    fn (EloquentBuilder $q): EloquentBuilder => $q->where('title', 'like', "%{$search}%")
                ))
                ->listWithLineBreaks()
                ->limit(40),
            IconColumn::make('is_enabled')->boolean(),
            StatusBadge::publication(TextColumn::make('publication_status'))->sortable(),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDirectorates::route('/'), 'create' => Pages\CreateDirectorate::route('/create'), 'edit' => Pages\EditDirectorate::route('/{record}/edit')];
    }
}
