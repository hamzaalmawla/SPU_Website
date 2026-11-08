<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Council\CouncilStaffServiceInterface;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The council page editor, the same for every council: per language the
 * introduction, the headings above the members and the functions; then the
 * members once for both languages. The form state is
 * `content.{ar|en}.{hero|council|functions}` and `members` (one row per person);
 * the saved CMS content keeps each language's members as its own list, in the
 * same order and with the same ids.
 */
final class CouncilEditor
{
    public static function contentTabs(): Tabs
    {
        return Tabs::make('council_locales')
            ->tabs([
                Tab::make(__('admin_councils.locales.ar'))
                    ->extraAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                    ->schema(self::localeSections('ar')),
                Tab::make(__('admin_councils.locales.en'))
                    ->extraAttributes(['dir' => 'ltr', 'lang' => 'en'])
                    ->schema(self::localeSections('en')),
            ])
            ->persistTabInQueryString('locale')
            ->columnSpanFull();
    }

    /**
     * The members, one row for both languages. Each is the council head or a
     * member. A teaching staff member is picked from the staff list and the page
     * shows their profile; anyone else is typed in, with no profile link.
     */
    public static function membersSection(): Section
    {
        $isStaff = static fn (Get $get): bool => (bool) $get('isStaff');

        return Section::make(__('admin_councils.sections.members'))
            ->description(__('admin_councils.sections.members_help'))
            ->schema([
                Repeater::make('members')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('id')->default(fn (): string => self::newMemberId()),
                        Select::make('role')
                            ->label(__('admin_councils.fields.role'))
                            ->options([
                                'head' => __('admin_councils.roles.head'),
                                'member' => __('admin_councils.roles.member'),
                            ])
                            ->default('member')
                            ->selectablePlaceholder(false)
                            ->required(),
                        Checkbox::make('isStaff')
                            ->label(__('admin_councils.fields.is_staff'))
                            ->helperText(__('admin_councils.help.is_staff'))
                            ->default(false)
                            ->live(),
                        Select::make('staffId')
                            ->label(__('admin_councils.fields.staff_member'))
                            ->options(fn (): array => self::staffOptions())
                            ->searchable()
                            ->live()
                            ->required($isStaff)
                            ->visible($isStaff)
                            ->columnSpanFull(),
                        Placeholder::make('staffPreview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::staffPreview($get('staffId')))
                            ->visible(fn (Get $get): bool => (bool) $get('isStaff') && is_numeric($get('staffId')))
                            ->columnSpanFull(),
                        TextInput::make('nameAr')
                            ->label(__('admin_councils.fields.name_ar'))
                            ->required(fn (Get $get): bool => ! $get('isStaff'))
                            ->visible(fn (Get $get): bool => ! $get('isStaff'))
                            ->maxLength(180),
                        TextInput::make('nameEn')
                            ->label(__('admin_councils.fields.name_en'))
                            ->helperText(__('admin_councils.help.name_en'))
                            ->visible(fn (Get $get): bool => ! $get('isStaff'))
                            ->maxLength(180),
                        TextInput::make('positionAr')
                            ->label(__('admin_councils.fields.position_ar'))
                            ->helperText(__('admin_councils.help.position'))
                            ->maxLength(180),
                        TextInput::make('positionEn')
                            ->label(__('admin_councils.fields.position_en'))
                            ->maxLength(180),
                        MediaPicker::image('image', __('admin_councils.fields.photo'))
                            ->visible(fn (Get $get): bool => ! $get('isStaff'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => self::memberLabel($state))
                    ->addActionLabel(__('admin_councils.actions.add_member'))
                    ->columnSpanFull(),
            ])
            ->columnSpanFull();
    }

    /**
     * The form state for saved content: each language's text, and the members
     * as rows (the Arabic and English lists hold the same members in the same
     * order, so each row joins the two by position).
     *
     * @param  array<string, mixed>  $payload
     * @return array{content: array{ar: array<string, mixed>, en: array<string, mixed>}, members: list<array<string, mixed>>}
     */
    public static function formState(array $payload): array
    {
        $content = [];
        foreach (['ar', 'en'] as $locale) {
            $translation = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            $content[$locale] = [
                'hero' => is_array($translation['hero'] ?? null) ? $translation['hero'] : [],
                'council' => is_array($translation['council'] ?? null) ? array_diff_key($translation['council'], ['members' => true]) : [],
                'functions' => [
                    ...(is_array($translation['functions'] ?? null) ? $translation['functions'] : []),
                    'items' => self::listOfArrays($translation['functions']['items'] ?? []),
                ],
            ];
        }

        $ar = self::listOfArrays($payload['translations']['ar']['council']['members'] ?? []);
        $en = self::listOfArrays($payload['translations']['en']['council']['members'] ?? []);
        $rows = [];
        foreach ($ar === [] ? $en : $ar as $index => $member) {
            $english = $en[$index] ?? [];
            $id = trim((string) ($member['id'] ?? ''));
            $rows[] = [
                'id' => $id !== '' ? $id : self::newMemberId(),
                'role' => ($member['role'] ?? 'member') === 'head' ? 'head' : 'member',
                'isStaff' => filter_var($member['isStaff'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'staffId' => is_numeric($member['staffId'] ?? null) ? (int) $member['staffId'] : null,
                'nameAr' => $ar === [] ? '' : (string) ($member['name'] ?? ''),
                'nameEn' => (string) ($english['name'] ?? ''),
                'positionAr' => $ar === [] ? '' : (string) ($member['position'] ?? ''),
                'positionEn' => (string) ($english['position'] ?? ''),
                'image' => is_string($member['image'] ?? null) ? $member['image'] : null,
            ];
        }

        return ['content' => $content, 'members' => $rows];
    }

    /**
     * The CMS content for the form state. Keys the form does not edit (from
     * older content) are kept from $source.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $source
     * @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}}
     */
    public static function payload(array $state, array $source = []): array
    {
        $members = self::membersFromRows(self::listOfArrays($state['members'] ?? []));
        $payload = ['translations' => []];

        foreach (['ar', 'en'] as $locale) {
            $previous = is_array($source['translations'][$locale] ?? null) ? $source['translations'][$locale] : [];
            $edited = is_array($state['content'][$locale] ?? null) ? $state['content'][$locale] : [];
            $hero = is_array($edited['hero'] ?? null) ? $edited['hero'] : [];
            $council = is_array($edited['council'] ?? null) ? $edited['council'] : [];
            $functions = is_array($edited['functions'] ?? null) ? $edited['functions'] : [];

            $payload['translations'][$locale] = [
                ...$previous,
                'hero' => [
                    ...(is_array($previous['hero'] ?? null) ? $previous['hero'] : []),
                    'title' => trim((string) ($hero['title'] ?? '')),
                    'summary' => trim((string) ($hero['summary'] ?? '')),
                    'backgroundImage' => is_string($hero['backgroundImage'] ?? null) && trim($hero['backgroundImage']) !== '' ? trim($hero['backgroundImage']) : null,
                ],
                'council' => [
                    'title' => trim((string) ($council['title'] ?? '')),
                    'subtitle' => trim((string) ($council['subtitle'] ?? '')),
                    'headLabel' => trim((string) ($council['headLabel'] ?? '')),
                    'memberLabel' => trim((string) ($council['memberLabel'] ?? '')),
                    'profileLabel' => trim((string) ($council['profileLabel'] ?? '')),
                    'members' => $members[$locale],
                ],
                'functions' => [
                    'title' => trim((string) ($functions['title'] ?? '')),
                    'subtitle' => trim((string) ($functions['subtitle'] ?? '')),
                    'items' => array_values(array_map(static fn (array $item): array => [
                        'title' => trim((string) ($item['title'] ?? '')),
                        'description' => trim((string) ($item['description'] ?? '')),
                    ], self::listOfArrays($functions['items'] ?? []))),
                ],
            ];
        }

        return $payload;
    }

    /** @return array<int, Section> */
    private static function localeSections(string $locale): array
    {
        $prefix = 'content.'.$locale;
        $attributes = ['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale];

        return [
            Section::make(__('admin_councils.sections.introduction'))->schema([
                TextInput::make($prefix.'.hero.title')->label(__('admin_councils.fields.title'))->required()->maxLength(180)->columnSpanFull(),
                Textarea::make($prefix.'.hero.summary')->label(__('admin_councils.fields.summary'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.hero.backgroundImage', __('admin_councils.fields.background_image'))->columnSpanFull(),
            ])->columns(2)->extraAttributes($attributes),
            Section::make(__('admin_councils.sections.headings'))
                ->description(__('admin_councils.sections.headings_help'))
                ->schema([
                    TextInput::make($prefix.'.council.title')->label(__('admin_councils.fields.members_title'))->required()->maxLength(180),
                    TextInput::make($prefix.'.council.subtitle')->label(__('admin_councils.fields.members_subtitle'))->maxLength(255),
                    TextInput::make($prefix.'.council.headLabel')->label(__('admin_councils.fields.head_label'))->required()->maxLength(80),
                    TextInput::make($prefix.'.council.memberLabel')->label(__('admin_councils.fields.member_label'))->required()->maxLength(80),
                    TextInput::make($prefix.'.council.profileLabel')->label(__('admin_councils.fields.profile_label'))->required()->maxLength(80),
                ])->columns(2)->collapsible()->extraAttributes($attributes),
            Section::make(__('admin_councils.sections.functions'))->schema([
                TextInput::make($prefix.'.functions.title')->label(__('admin_councils.fields.functions_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.functions.subtitle')->label(__('admin_councils.fields.functions_subtitle'))->maxLength(255),
                Repeater::make($prefix.'.functions.items')
                    ->label(__('admin_councils.fields.functions'))
                    ->schema([
                        TextInput::make('title')->label(__('admin_councils.fields.function_title'))->required()->maxLength(180),
                        Textarea::make('description')->label(__('admin_councils.fields.function_description'))->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => is_string($state['title'] ?? null) && $state['title'] !== '' ? $state['title'] : null)
                    ->addActionLabel(__('admin_councils.actions.add_function'))
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->extraAttributes($attributes),
        ];
    }

    /**
     * The rows as each language's members. An English name left empty shows
     * the Arabic one.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{ar: list<array<string, mixed>>, en: list<array<string, mixed>>}
     */
    private static function membersFromRows(array $rows): array
    {
        $members = ['ar' => [], 'en' => []];

        foreach ($rows as $row) {
            $isStaff = (bool) ($row['isStaff'] ?? false);
            $id = trim((string) ($row['id'] ?? ''));
            $nameAr = $isStaff ? '' : trim((string) ($row['nameAr'] ?? ''));
            $nameEn = $isStaff ? '' : trim((string) ($row['nameEn'] ?? ''));
            $image = $isStaff ? null : (is_string($row['image'] ?? null) && trim($row['image']) !== '' ? trim($row['image']) : null);
            $shared = [
                'id' => $id !== '' ? $id : self::newMemberId(),
                'role' => ($row['role'] ?? 'member') === 'head' ? 'head' : 'member',
                'isStaff' => $isStaff,
                'staffId' => $isStaff && is_numeric($row['staffId'] ?? null) ? (int) $row['staffId'] : null,
                'image' => $image,
            ];

            $members['ar'][] = [...$shared, 'name' => $nameAr, 'position' => trim((string) ($row['positionAr'] ?? ''))];
            $members['en'][] = [...$shared, 'name' => $nameEn !== '' ? $nameEn : $nameAr, 'position' => trim((string) ($row['positionEn'] ?? ''))];
        }

        return $members;
    }

    /** @return array<int, string> */
    private static function staffOptions(): array
    {
        // Read once per request: every member row's label asks for it.
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $key = 'council-editor.staff-options.'.$locale;
        $options = request()->attributes->get($key);
        if (! is_array($options)) {
            $options = app(CouncilStaffServiceInterface::class)->staffOptions($locale);
            request()->attributes->set($key, $options);
        }

        return $options;
    }

    /** What the picked staff member's profile shows, so the editor sees who they picked. */
    private static function staffPreview(mixed $staffId): HtmlString
    {
        $id = is_numeric($staffId) ? (int) $staffId : 0;
        $card = app(CouncilStaffServiceInterface::class)->staffCards([$id], app()->getLocale() === 'ar' ? 'ar' : 'en', false)[$id] ?? null;
        if ($card === null) {
            return new HtmlString('<p class="text-sm text-danger-600">'.e(__('admin_councils.help.staff_missing')).'</p>');
        }

        $photo = is_string($card['image']) && $card['image'] !== ''
            ? '<img src="'.e($card['image']).'" alt="" style="width:56px;height:56px;border-radius:9999px;object-fit:cover">'
            : '<span style="display:inline-flex;width:56px;height:56px;border-radius:9999px;background:#e5e7eb"></span>';
        $lines = array_filter([
            '<strong>'.e($card['name']).'</strong>',
            $card['rank'] !== '' ? e($card['rank']) : null,
            $card['email'] !== '' ? e($card['email']) : null,
            '<a href="'.e($card['url']).'" target="_blank" rel="noopener" class="text-primary-600 underline">'.e(__('admin_councils.help.staff_profile')).'</a>',
            $card['isPublic'] ? null : '<span class="text-warning-600">'.e(__('admin_councils.help.staff_not_public')).'</span>',
        ]);

        return new HtmlString('<div style="display:flex;gap:12px;align-items:center">'.$photo.'<div class="text-sm" style="display:flex;flex-direction:column;gap:2px">'.implode('', array_map(static fn (string $line): string => '<span>'.$line.'</span>', $lines)).'</div></div>');
    }

    /** @param array<string, mixed> $state */
    private static function memberLabel(array $state): ?string
    {
        $role = ($state['role'] ?? 'member') === 'head'
            ? __('admin_councils.roles.head')
            : __('admin_councils.roles.member');
        if ((bool) ($state['isStaff'] ?? false)) {
            $id = is_numeric($state['staffId'] ?? null) ? (int) $state['staffId'] : 0;
            $name = $id > 0 ? (self::staffOptions()[$id] ?? null) : null;
        } else {
            $name = trim((string) ($state['nameAr'] ?? '')) ?: (trim((string) ($state['nameEn'] ?? '')) ?: null);
        }

        return trim(implode(' · ', array_filter([$role, $name]))) ?: null;
    }

    private static function newMemberId(): string
    {
        return 'member-'.Str::lower(Str::random(8));
    }

    /** @return list<array<string, mixed>> */
    private static function listOfArrays(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
