<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Center\CenterPageServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Content\ProfileAdminServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Components\PageUrlSelect;
use App\Filament\Concerns\LimitsCmsTargetsToGrants;
use App\Filament\Resources\FacultyMemberResource;
use App\Filament\Support\MediaPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Edits the centers section: the /centers page and, for each center, its page,
 * its staff page and its course list (shown as the required and elective
 * course pages). Each is its own CMS page with draft, preview and publish.
 */
class ManageCenters extends Page implements HasForms
{
    use InteractsWithForms;
    use LimitsCmsTargetsToGrants;

    private const FACULTIES = [
        'medicine',
        'dentistry',
        'pharmacy',
        'artificial-intelligence',
        'building-construction-engineering',
        'petroleum',
        'business-administration',
    ];

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $slug = 'manage-centers';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-centers';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private CenterCatalogServiceInterface $catalog;

    private CenterPageServiceInterface $pages;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        CenterCatalogServiceInterface $catalog,
        CenterPageServiceInterface $pages,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->catalog = $catalog;
        $this->pages = $pages;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return static::canAccessCmsArea('centers', 'centers');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.centers');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.centers');
    }

    public function getTitle(): string
    {
        return __('admin_centers.title');
    }

    public function mount(): void
    {
        $requested = request()->query('target');
        $options = $this->targetOptions();
        $target = is_string($requested) && array_key_exists($requested, $options)
            ? $requested
            : $this->firstGrantedTarget('centers.landing', $options);

        $this->loadTarget($target);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    Select::make('target_key')
                        ->label(__('admin_centers.page'))
                        ->options(fn (): array => $this->targetOptions())
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                    Placeholder::make('publish_state')
                        ->hiddenLabel()
                        ->content(fn (): string => __('admin_centers.notifications.not_published_yet'))
                        ->visible(fn (): bool => ! $this->catalog->hasPublished($this->currentTargetKey())),
                ]),

                Section::make(__('admin_centers.sections.images'))
                    ->schema([
                        MediaPicker::image('heroImage', __('admin_centers.fields.hero_image')),
                        Repeater::make('gallery')
                            ->label(__('admin_centers.fields.gallery'))
                            ->schema([MediaPicker::image('image', __('admin_centers.fields.image'), true)])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->visible(fn (): bool => $this->part() === 'page'),
                    ])
                    ->visible(fn (): bool => in_array($this->part(), ['landing', 'page'], true))
                    ->collapsible(),

                Section::make(__('admin_centers.sections.manager'))
                    ->schema([
                        Select::make('managerMemberId')
                            ->label(__('admin_centers.fields.manager'))
                            ->helperText(__('admin_centers.fields.manager_help'))
                            ->options(fn (): array => $this->staffOptions())
                            ->searchable()
                            ->native(false),
                    ])
                    ->visible(fn (): bool => $this->part() === 'page'),

                Section::make(__('admin_centers.sections.staff'))
                    ->schema([
                        Placeholder::make('staff_help')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(e(__('admin_centers.sections.staff_help'))
                                .(FacultyMemberResource::canCreate()
                                    ? ' <a class="font-semibold text-primary-600 underline" href="'.e(FacultyMemberResource::getUrl('create')).'">'.e(__('admin_centers.actions.add_staff')).'</a>'
                                    : ''))),
                        Placeholder::make('staff_list')
                            ->hiddenLabel()
                            ->content(fn (): string => implode(' · ', $this->staffOptions()) ?: '—'),
                    ])
                    ->visible(fn (): bool => in_array($this->part(), ['page', 'staff'], true)),

                Section::make(__('admin_centers.sections.courses'))
                    ->description(__('admin_centers.sections.courses_help'))
                    ->schema([$this->coursesRepeater()])
                    ->visible(fn (): bool => $this->part() === 'courses'),

                Tabs::make('center_locales')
                    ->tabs([
                        Tab::make(__('admin.locales.ar'))->extraAttributes(['dir' => 'rtl'])->schema($this->localeFields('ar')),
                        Tab::make(__('admin.locales.en'))->extraAttributes(['dir' => 'ltr'])->schema($this->localeFields('en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('admin_centers.actions.save'))->icon('heroicon-o-check')->color('gray')->action(fn () => $this->save()),
            Action::make('preview_ar')->label(__('admin_centers.actions.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(fn () => $this->openPreview('ar')),
            Action::make('preview_en')->label(__('admin_centers.actions.preview_en'))->icon('heroicon-o-eye')->color('info')->action(fn () => $this->openPreview('en')),
            Action::make('preview_elective_en')->label(__('admin_centers.actions.preview_elective_en'))->icon('heroicon-o-eye')->color('info')
                ->visible(fn (): bool => $this->part() === 'courses')
                ->action(fn () => $this->openPreview('en', 'elective-courses')),
            Action::make('publish')->label(__('admin_centers.actions.publish'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()
                ->visible(fn (): bool => static::canPublishCmsArea('centers'))
                ->action(fn () => $this->publish()),
            Action::make('schedule')->label(__('admin_centers.actions.schedule'))->icon('heroicon-o-clock')->color('warning')
                ->visible(fn (): bool => static::canPublishCmsArea('centers'))
                ->form([DateTimePicker::make('publish_at')->label(__('admin_centers.actions.publish_at'))->required()->minDate(now())->native(false)])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label(__('admin_centers.actions.unpublish'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => static::canPublishCmsArea('centers'))
                ->action(fn () => $this->unpublish()),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertCenterTarget($targetKey);
        $userId = (int) auth()->id();
        $draft = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $userId);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId);
        $payload = is_array($draft) ? $draft : $this->catalog->content($targetKey);
        $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
        $centerSlug = (string) ($this->catalog->parseTarget($targetKey)['center'] ?? '');

        $this->form->fill([
            'target_key' => $targetKey,
            'heroImage' => (string) ($payload['heroImage'] ?? ''),
            'gallery' => array_map(static fn (mixed $image): array => ['image' => (string) $image], array_values(array_filter(is_array($payload['gallery'] ?? null) ? $payload['gallery'] : [], 'is_string'))),
            'managerMemberId' => is_numeric($payload['managerMemberId'] ?? null) ? (int) $payload['managerMemberId'] : null,
            'courses' => $centerSlug !== '' ? array_map(fn (array $course): array => $this->courseToForm($course), $this->catalog->courses($centerSlug, $payload)) : [],
            'ar' => $this->localeToForm(is_array($translations['ar'] ?? null) ? $translations['ar'] : []),
            'en' => $this->localeToForm(is_array($translations['en'] ?? null) ? $translations['en'] : []),
        ]);
    }

    public function save(): void
    {
        try {
            $this->saveDraft($this->currentFormData());
            Notification::make()->title(__('admin_centers.notifications.saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_centers.notifications.conflict'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_centers.notifications.failed'))->danger()->send();
        }
    }

    public function openPreview(string $locale, string $subpage = ''): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            return;
        }

        try {
            $this->saveDraft($this->currentFormData());
            $preview = $this->cmsWorkflowService->preview($this->currentTargetKey(), $locale, (int) auth()->id());
            $this->redirect($preview->previewUrl.($subpage !== '' ? '&subpage='.rawurlencode($subpage) : ''));
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_centers.notifications.conflict'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_centers.notifications.failed'))->danger()->send();
        }
    }

    public function publish(): void
    {
        try {
            $this->saveDraft($this->form->getState());
            $this->cmsWorkflowService->publish($this->currentTargetKey(), (int) auth()->id());
            Notification::make()->title(__('admin_centers.notifications.published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_centers.notifications.publish_failed'))->body(collect($e->errors())->flatten()->implode(PHP_EOL))->danger()->persistent()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_centers.notifications.conflict'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_centers.notifications.publish_failed'))->danger()->send();
        }
    }

    public function schedule(string $publishAt): void
    {
        try {
            $this->saveDraft($this->form->getState());
            $this->cmsWorkflowService->schedule($this->currentTargetKey(), new \DateTimeImmutable($publishAt), (int) auth()->id());
            Notification::make()->title(__('admin_centers.notifications.scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_centers.notifications.publish_failed'))->body(collect($e->errors())->flatten()->implode(PHP_EOL))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_centers.notifications.publish_failed'))->danger()->send();
        }
    }

    public function unpublish(): void
    {
        try {
            $done = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) auth()->id());
            $notification = Notification::make()->title($done ? __('admin_centers.notifications.unpublished') : __('admin_centers.notifications.nothing_published'));
            ($done ? $notification->success() : $notification->warning())->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_centers.notifications.failed'))->danger()->send();
        }
    }

    /**
     * The draft payload the form holds, in the shape the public pages read.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function payloadFromForm(array $state): array
    {
        $targetKey = (string) ($state['target_key'] ?? $this->currentTargetKey());
        $part = (string) ($this->catalog->parseTarget($targetKey)['part'] ?? '');
        $payload = [
            'translations' => [
                'ar' => $this->localeFromForm($part, is_array($state['ar'] ?? null) ? $state['ar'] : []),
                'en' => $this->localeFromForm($part, is_array($state['en'] ?? null) ? $state['en'] : []),
            ],
        ];

        if (in_array($part, ['landing', 'page'], true)) {
            $payload['heroImage'] = trim((string) ($state['heroImage'] ?? ''));
        }

        if ($part === 'page') {
            $payload['gallery'] = array_values(array_filter(array_map(
                static fn (mixed $item): string => is_array($item) ? trim((string) ($item['image'] ?? '')) : '',
                is_array($state['gallery'] ?? null) ? array_values($state['gallery']) : [],
            )));
            $payload['managerMemberId'] = is_numeric($state['managerMemberId'] ?? null) ? (int) $state['managerMemberId'] : null;
        }

        if ($part === 'courses') {
            $payload['courses'] = array_map(
                fn (array $course): array => $this->courseFromForm($course),
                array_values(array_filter(is_array($state['courses'] ?? null) ? $state['courses'] : [], 'is_array')),
            );
        }

        return $payload;
    }

    /** @param array<string, mixed> $state */
    private function saveDraft(array $state): void
    {
        $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($state), (int) auth()->id(), $this->draftVersion);
        $this->draftVersion = $draft->version;
    }

    /** @return array<int, mixed> */
    private function localeFields(string $locale): array
    {
        $prefix = $locale;
        $subpages = collect(CenterCatalogServiceInterface::SUBPAGES)->mapWithKeys(fn (string $slug): array => [$slug => __('admin_centers.subpages.'.$slug)])->all();

        return [
            Section::make(__('admin_centers.sections.hero'))->schema([
                TextInput::make($prefix.'.hero.title')->label(fn (): string => $this->part() === 'courses' ? __('admin_centers.fields.required_page').' › '.__('admin_centers.fields.title') : __('admin_centers.fields.title'))->required()->maxLength(180),
                Textarea::make($prefix.'.hero.summary')->label(__('admin_centers.fields.summary'))->required()->rows(2),
                TextInput::make($prefix.'.electiveHero.title')->label(__('admin_centers.fields.elective_page').' › '.__('admin_centers.fields.title'))->required(fn (): bool => $this->part() === 'courses')->maxLength(180)
                    ->visible(fn (): bool => $this->part() === 'courses'),
                Textarea::make($prefix.'.electiveHero.summary')->label(__('admin_centers.fields.elective_page').' › '.__('admin_centers.fields.summary'))->rows(2)
                    ->visible(fn (): bool => $this->part() === 'courses'),
                Textarea::make($prefix.'.body')->label(__('admin_centers.fields.body'))->rows(5)
                    ->visible(fn (): bool => $this->part() === 'staff'),
            ]),

            Section::make(__('admin_centers.sections.offers'))->schema([
                TextInput::make($prefix.'.centersTitle')->label(__('admin_centers.fields.centers_title'))->maxLength(160),
                TextInput::make($prefix.'.offers.title')->label(__('admin_centers.fields.title'))->maxLength(180),
                Textarea::make($prefix.'.offers.summary')->label(__('admin_centers.fields.summary'))->rows(2),
                Repeater::make($prefix.'.offers.items')
                    ->label(__('admin_centers.fields.offer_items'))
                    ->schema([
                        TextInput::make('title')->label(__('admin_centers.fields.title'))->required()->maxLength(160),
                        Textarea::make('summary')->label(__('admin_centers.fields.summary'))->rows(2),
                    ])
                    ->defaultItems(0)->reorderable()->collapsible()->columns(2),
            ])->visible(fn (): bool => $this->part() === 'landing'),

            Section::make(__('admin_centers.sections.extra_cards'))->description(__('admin_centers.sections.extra_cards_help'))->schema([
                Repeater::make($prefix.'.extraCards')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('title')->label(__('admin_centers.fields.title'))->required()->maxLength(160),
                        Textarea::make('summary')->label(__('admin_centers.fields.summary'))->rows(2),
                        PageUrlSelect::make('url', __('admin_centers.fields.url'), $locale, true),
                        MediaPicker::image('image', __('admin_centers.fields.image')),
                    ])
                    ->defaultItems(0)->reorderable()->collapsible(),
            ])->visible(fn (): bool => $this->part() === 'landing')->collapsed(),

            Section::make(__('admin_centers.sections.about'))->schema([
                Repeater::make($prefix.'.tabs')
                    ->label(__('admin_centers.fields.tabs'))
                    ->schema([
                        TextInput::make('label')->label(__('admin_centers.fields.tab_label'))->required()->maxLength(120),
                        Textarea::make('body')->label(__('admin_centers.fields.body'))->required()->rows(5),
                    ])
                    ->defaultItems(0)->reorderable()->collapsible(),
            ])->visible(fn (): bool => $this->part() === 'page'),

            Section::make(__('admin_centers.sections.manager'))->schema([
                TextInput::make($prefix.'.manager.title')->label(__('admin_centers.fields.manager_title'))->maxLength(120),
                Textarea::make($prefix.'.manager.message')->label(__('admin_centers.fields.manager_message'))->rows(6),
            ])->visible(fn (): bool => $this->part() === 'page'),

            Section::make(__('admin_centers.sections.stats'))->schema([
                Repeater::make($prefix.'.stats')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('value')->label(__('admin_centers.fields.value'))->required()->maxLength(40),
                        TextInput::make('label')->label(__('admin_centers.fields.label'))->required()->maxLength(120),
                    ])
                    ->defaultItems(0)->reorderable()->collapsible()->columns(2),
            ])->visible(fn (): bool => $this->part() === 'page')->collapsed(),

            Section::make(__('admin_centers.sections.highlights'))->schema([
                TextInput::make($prefix.'.highlightsTitle')->label(__('admin_centers.fields.highlights_title'))->maxLength(120),
                Repeater::make($prefix.'.highlights')
                    ->hiddenLabel()
                    ->schema([
                        Select::make('subpage')->label(__('admin_centers.fields.highlight_page'))->options($subpages)->required()->native(false),
                        TextInput::make('title')->label(__('admin_centers.fields.title'))->required()->maxLength(120),
                        Textarea::make('summary')->label(__('admin_centers.fields.summary'))->rows(2)->columnSpanFull(),
                    ])
                    ->defaultItems(0)->reorderable()->collapsible()->columns(2),
            ])->visible(fn (): bool => $this->part() === 'page')->collapsed(),

            Section::make(__('admin_centers.sections.seo'))->schema([
                TextInput::make($prefix.'.seoTitle')->label(__('admin_centers.fields.seo_title'))->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_centers.fields.seo_description'))->rows(2)->maxLength(320),
            ])->collapsed(),
        ];
    }

    private function coursesRepeater(): Repeater
    {
        $faculties = collect(self::FACULTIES)->mapWithKeys(fn (string $slug): array => [$slug => __('admin.research_workspace.options.faculties.'.$slug)])->all();
        $teacherSelect = fn (string $name, string $label): Select => Select::make($name)
            ->label($label)
            ->multiple()
            ->searchable()
            ->options(fn (): array => $this->staffOptions())
            ->getSearchResultsUsing(fn (string $search): array => $this->profileOptions($search))
            ->getOptionLabelsUsing(fn (array $values): array => $this->profileOptions('', array_map('intval', $values)));

        return Repeater::make('courses')
            ->hiddenLabel()
            ->itemLabel(fn (array $state): string => trim((string) ($state[app()->getLocale() === 'ar' ? 'titleAr' : 'titleEn'] ?? '')).(($state['required'] ?? true) ? '' : ' · '.__('admin_centers.subpages.elective-courses')))
            ->schema([
                TextInput::make('id')->label(__('admin_centers.fields.course_id'))->helperText(__('admin_centers.fields.course_id_help'))
                    ->required()->maxLength(80)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                TextInput::make('code')->label(__('admin_centers.fields.code'))->maxLength(80),
                TextInput::make('titleAr')->label(__('admin_centers.fields.title_ar'))->required()->maxLength(180)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('titleEn')->label(__('admin_centers.fields.title_en'))->required()->maxLength(180)->extraInputAttributes(['dir' => 'ltr']),
                Toggle::make('required')->label(__('admin_centers.fields.required'))->helperText(__('admin_centers.fields.required_help'))->default(true),
                TextInput::make('credits')->label(__('admin_centers.fields.credits'))->numeric()->minValue(0)->maxValue(40)->default(2),
                TextInput::make('theory')->label(__('admin_centers.fields.theory'))->numeric()->minValue(0)->maxValue(40)->default(2),
                TextInput::make('practical')->label(__('admin_centers.fields.practical'))->numeric()->minValue(0)->maxValue(40)->default(0),
                Select::make('prerequisites')->label(__('admin_centers.fields.prerequisites'))->helperText(__('admin_centers.fields.prerequisites_help'))
                    ->multiple()->native(false)
                    ->options(fn (Get $get): array => $this->courseIdOptions((string) ($get('id') ?? '')))
                    ->columnSpanFull(),
                Repeater::make('faculties')
                    ->label(__('admin_centers.fields.faculties'))
                    ->helperText(__('admin_centers.fields.faculties_help'))
                    ->schema([
                        Select::make('faculty')->label(__('admin_centers.fields.faculty'))->options($faculties)->required()->native(false),
                        TextInput::make('code')->label(__('admin_centers.fields.faculty_code'))->required()->maxLength(80),
                        TextInput::make('credits')->label(__('admin_centers.fields.faculty_credits'))->helperText(__('admin_centers.fields.faculty_credits_help'))->numeric()->minValue(0)->maxValue(40),
                    ])
                    ->defaultItems(0)->columns(3)->collapsible()->columnSpanFull(),
                $teacherSelect('theoryTeacherIds', __('admin_centers.fields.theory_teachers')),
                $teacherSelect('practicalTeacherIds', __('admin_centers.fields.practical_teachers')),
                Toggle::make('theoryEnabled')->label(__('admin_centers.fields.theory_enabled'))->default(true),
                Toggle::make('practicalEnabled')->label(__('admin_centers.fields.practical_enabled'))->default(true),
                Textarea::make('descriptionAr')->label(__('admin_centers.fields.description_ar'))->rows(3)->extraInputAttributes(['dir' => 'rtl']),
                Textarea::make('descriptionEn')->label(__('admin_centers.fields.description_en'))->rows(3)->extraInputAttributes(['dir' => 'ltr']),
                MediaPicker::pdf('specificationUrl', __('admin_centers.fields.specification')),
                Repeater::make('lessons')
                    ->label(__('admin_centers.fields.lessons'))
                    ->schema([
                        Select::make('type')->label(__('admin_centers.fields.lesson_type'))->options(__('admin_centers.lesson_types'))->default('lecture')->required()->native(false),
                        TextInput::make('titleAr')->label(__('admin_centers.fields.lesson_title_ar'))->maxLength(180),
                        TextInput::make('titleEn')->label(__('admin_centers.fields.lesson_title_en'))->maxLength(180),
                        MediaPicker::pdf('pdfUrl', __('admin_centers.fields.lesson_file')),
                    ])
                    ->defaultItems(0)->reorderable()->collapsible()->collapsed()->columns(3)->columnSpanFull(),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->reorderable()
            ->collapsible()
            ->collapsed();
    }

    /**
     * @param  array<string, mixed>  $course  a clean course from the catalog
     * @return array<string, mixed>
     */
    private function courseToForm(array $course): array
    {
        return [
            'id' => $course['id'],
            'code' => $course['code'],
            'titleAr' => $course['titleAr'],
            'titleEn' => $course['titleEn'],
            'descriptionAr' => $course['descriptionAr'],
            'descriptionEn' => $course['descriptionEn'],
            'required' => $course['required'],
            'credits' => $course['credits'],
            'theory' => $course['hours']['theory'],
            'practical' => $course['hours']['practical'],
            'prerequisites' => $course['prerequisites'],
            'faculties' => array_map(static fn (array $link): array => [
                'faculty' => $link['faculty'],
                'code' => $link['code'],
                'credits' => $link['credits'] ?? null,
            ], $course['faculties']),
            'theoryTeacherIds' => array_map('strval', $course['teachers']['theory']['memberIds']),
            'practicalTeacherIds' => array_map('strval', $course['teachers']['practical']['memberIds']),
            'theoryEnabled' => $course['teachers']['theory']['enabled'],
            'practicalEnabled' => $course['teachers']['practical']['enabled'],
            'specificationUrl' => $course['specificationUrl'],
            'lessons' => $course['lessons'],
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function courseFromForm(array $form): array
    {
        $number = static fn (mixed $value): int|float => is_numeric($value) ? (floor((float) $value) === (float) $value ? (int) $value : (float) $value) : 0;
        $ids = static fn (mixed $values): array => array_values(array_unique(array_map('intval', array_filter(is_array($values) ? $values : [], static fn (mixed $value): bool => is_numeric($value) && (int) $value > 0))));

        return [
            'id' => trim((string) ($form['id'] ?? '')),
            'code' => trim((string) ($form['code'] ?? '')),
            'titleAr' => trim((string) ($form['titleAr'] ?? '')),
            'titleEn' => trim((string) ($form['titleEn'] ?? '')),
            'descriptionAr' => trim((string) ($form['descriptionAr'] ?? '')),
            'descriptionEn' => trim((string) ($form['descriptionEn'] ?? '')),
            'required' => (bool) ($form['required'] ?? true),
            'credits' => $number($form['credits'] ?? 0),
            'hours' => ['theory' => $number($form['theory'] ?? 0), 'practical' => $number($form['practical'] ?? 0)],
            'prerequisites' => array_values(array_filter(array_map('strval', is_array($form['prerequisites'] ?? null) ? $form['prerequisites'] : []))),
            'faculties' => array_values(array_map(static function (array $link) use ($number): array {
                $entry = ['faculty' => trim((string) ($link['faculty'] ?? '')), 'code' => trim((string) ($link['code'] ?? ''))];
                if (is_numeric($link['credits'] ?? null)) {
                    $entry['credits'] = $number($link['credits']);
                }

                return $entry;
            }, array_filter(is_array($form['faculties'] ?? null) ? $form['faculties'] : [], 'is_array'))),
            'teachers' => [
                'theory' => ['memberIds' => $ids($form['theoryTeacherIds'] ?? []), 'enabled' => (bool) ($form['theoryEnabled'] ?? true)],
                'practical' => ['memberIds' => $ids($form['practicalTeacherIds'] ?? []), 'enabled' => (bool) ($form['practicalEnabled'] ?? true)],
            ],
            'specificationUrl' => trim((string) ($form['specificationUrl'] ?? '')),
            'lessons' => array_values(array_map(static fn (array $lesson): array => [
                'type' => in_array($lesson['type'] ?? null, ['lecture', 'lab'], true) ? $lesson['type'] : 'lecture',
                'titleAr' => trim((string) ($lesson['titleAr'] ?? '')),
                'titleEn' => trim((string) ($lesson['titleEn'] ?? '')),
                'pdfUrl' => trim((string) ($lesson['pdfUrl'] ?? '')),
            ], array_filter(is_array($form['lessons'] ?? null) ? $form['lessons'] : [], 'is_array'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function localeToForm(array $content): array
    {
        return $content;
    }

    /**
     * Keeps only the fields of this kind of page, with lists as plain lists.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function localeFromForm(string $part, array $state): array
    {
        $text = static fn (mixed $value): string => is_string($value) ? trim($value) : '';
        $list = static fn (mixed $items): array => array_values(array_filter(is_array($items) ? $items : [], 'is_array'));
        $hero = ['title' => $text($state['hero']['title'] ?? null), 'summary' => $text($state['hero']['summary'] ?? null)];
        $content = ['hero' => $hero, 'seoTitle' => $text($state['seoTitle'] ?? null), 'seoDescription' => $text($state['seoDescription'] ?? null)];

        return match ($part) {
            'landing' => [
                ...$content,
                'centersTitle' => $text($state['centersTitle'] ?? null),
                'offers' => [
                    'title' => $text($state['offers']['title'] ?? null),
                    'summary' => $text($state['offers']['summary'] ?? null),
                    'items' => array_map(static fn (array $item): array => ['title' => $text($item['title'] ?? null), 'summary' => $text($item['summary'] ?? null)], $list($state['offers']['items'] ?? [])),
                ],
                'extraCards' => array_map(static fn (array $card): array => [
                    'title' => $text($card['title'] ?? null),
                    'summary' => $text($card['summary'] ?? null),
                    'url' => $text($card['url'] ?? null),
                    'image' => $text($card['image'] ?? null),
                ], $list($state['extraCards'] ?? [])),
            ],
            'page' => [
                ...$content,
                'tabs' => array_map(static fn (array $tab): array => ['label' => $text($tab['label'] ?? null), 'body' => $text($tab['body'] ?? null)], $list($state['tabs'] ?? [])),
                'manager' => ['title' => $text($state['manager']['title'] ?? null), 'message' => $text($state['manager']['message'] ?? null)],
                'stats' => array_map(static fn (array $stat): array => ['value' => $text($stat['value'] ?? null), 'label' => $text($stat['label'] ?? null)], $list($state['stats'] ?? [])),
                'highlightsTitle' => $text($state['highlightsTitle'] ?? null),
                'highlights' => array_map(static fn (array $card): array => [
                    'subpage' => $text($card['subpage'] ?? null),
                    'title' => $text($card['title'] ?? null),
                    'summary' => $text($card['summary'] ?? null),
                ], $list($state['highlights'] ?? [])),
            ],
            'staff' => [...$content, 'body' => $text($state['body'] ?? null)],
            'courses' => [
                ...$content,
                'electiveHero' => ['title' => $text($state['electiveHero']['title'] ?? null), 'summary' => $text($state['electiveHero']['summary'] ?? null)],
            ],
            default => $content,
        };
    }

    /** @return array<string, string> */
    private function courseIdOptions(string $except): array
    {
        $options = [];
        foreach (is_array($this->data['courses'] ?? null) ? $this->data['courses'] : [] as $course) {
            $id = is_array($course) ? trim((string) ($course['id'] ?? '')) : '';
            if ($id !== '' && $id !== $except) {
                $options[$id] = trim((string) ($course[app()->getLocale() === 'ar' ? 'titleAr' : 'titleEn'] ?? '')) ?: $id;
            }
        }

        return $options;
    }

    /** @return array<int, string> */
    private function staffOptions(): array
    {
        $centerSlug = (string) ($this->catalog->parseTarget($this->currentTargetKey())['center'] ?? '');

        return $centerSlug !== '' ? $this->pages->staffOptions($centerSlug, app()->getLocale() === 'ar' ? 'ar' : 'en') : [];
    }

    /**
     * Any profile, for a course taught by someone outside the center's staff.
     *
     * @param  array<int, int>  $includeIds
     * @return array<int|string, string>
     */
    private function profileOptions(string $search, array $includeIds = []): array
    {
        try {
            return app(ProfileAdminServiceInterface::class)->profileOptions($search, (int) auth()->id(), $includeIds);
        } catch (AuthorizationException) {
            return array_intersect_key($this->staffOptions(), array_flip($includeIds)) ?: $this->staffOptions();
        }
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        $options = [];
        foreach ($this->targetRegistry->forArea('centers') as $target) {
            if (! $target instanceof CmsTargetDTO) {
                continue;
            }

            $center = $this->catalog->parseTarget($target->key)['center'] ?? null;
            $options[$target->key] = ($center !== null ? $this->catalog->centerName($center, app()->getLocale() === 'ar' ? 'ar' : 'en').' › ' : '').__($target->labelKey);
        }

        return $this->onlyGrantedTargets($options);
    }

    private function part(): string
    {
        return (string) ($this->catalog->parseTarget($this->currentTargetKey())['part'] ?? 'landing');
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'centers.landing');

        return $this->catalog->isCenterTarget($targetKey) ? $targetKey : 'centers.landing';
    }

    private function assertCenterTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'centers') {
            throw new \InvalidArgumentException('Unknown center page.');
        }
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }
}
