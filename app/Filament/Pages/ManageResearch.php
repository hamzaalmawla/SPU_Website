<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\Research\ResearchCouncilStaffServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Exceptions\ConflictException;
use App\Filament\Components\PageUrlSelect;
use App\Filament\Concerns\LimitsCmsTargetsToGrants;
use App\Filament\Resources\ResearchPublicationResource;
use App\Filament\Support\FormPicker;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use App\Support\FormLink;
use App\Support\HtmlSanitizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageResearch extends Page implements HasForms
{
    use InteractsWithForms;
    use LimitsCmsTargetsToGrants;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $slug = 'manage-research';

    protected static ?int $navigationSort = 40;

    protected static string $view = 'filament.pages.manage-research';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    /** @var array<string, mixed> */
    public array $sourcePayload = [];

    private ResearchPageServiceInterface $researchPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private HtmlSanitizer $htmlSanitizer;

    /** @var array<int, string>|null The staff list, read once per request. */
    private ?array $councilStaffOptions = null;

    public function boot(
        ResearchPageServiceInterface $researchPageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
        HtmlSanitizer $htmlSanitizer,
    ): void {
        $this->researchPageService = $researchPageService;
        $this->cmsWorkflowService = $cmsWorkflowService;
        $this->htmlSanitizer = $htmlSanitizer;
    }

    public static function canAccess(): bool
    {
        return static::canAccessCmsArea('research_pages', 'research');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.academic');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.research');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_research');
    }

    public function mount(): void
    {
        $requestedTarget = request()->query('target', 'research.index');
        $targetKey = is_string($requestedTarget) && array_key_exists($requestedTarget, $this->targetOptions())
            ? $requestedTarget
            : $this->firstGrantedTarget('research.index', $this->targetOptions());

        $this->loadTarget($targetKey);
    }

    public function form(Form $form): Form
    {
        $targetKey = $this->formTargetKey();

        return $form
            ->schema([
                Hidden::make('target_key')->required(),
                Tabs::make('research_locales')
                    ->tabs([
                        Tab::make(__('admin.research_workspace.locales.ar'))
                            ->extraAttributes(['dir' => 'rtl', 'lang' => 'ar'])
                            ->schema($this->targetFields($targetKey, 'ar')),
                        Tab::make(__('admin.research_workspace.locales.en'))
                            ->extraAttributes(['dir' => 'ltr', 'lang' => 'en'])
                            ->schema($this->targetFields($targetKey, 'en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
                $this->councilMembersSection(),
            ])
            ->statePath('data');
    }

    private function formTargetKey(): string
    {
        $stateTarget = $this->data['target_key'] ?? null;
        if (is_string($stateTarget) && array_key_exists($stateTarget, $this->targetOptions())) {
            return $stateTarget;
        }

        $requestedTarget = request()->query('target', 'research.index');

        return is_string($requestedTarget) && array_key_exists($requestedTarget, $this->targetOptions())
            ? $requestedTarget
            : $this->firstGrantedTarget('research.index', $this->targetOptions());
    }

    /** @return array<int, Component> */
    private function targetFields(string $targetKey, string $locale): array
    {
        return match ($targetKey) {
            'research.index' => $this->landingFields($locale),
            'research.publications' => $this->publicationFields($locale),
            'research.centers' => $this->centerFields($locale),
            'research.projects' => $this->projectFields($locale),
            'research.themes' => $this->themeFields($locale),
            'research.experts' => $this->expertFields($locale),
            'research.conferences' => $this->conferenceFields($locale),
            'research.library' => $this->libraryFields($locale),
            'research.office' => $this->officeFields($locale),
            'research.policies' => $this->policyFields($locale),
            default => [],
        };
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertResearchTarget($targetKey);

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->researchPageService->getEditablePayload($targetKey);
        $payload = $this->publicationBodyForEditor($targetKey, $payload);
        $this->sourcePayload = $payload;
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

        if ($targetKey === 'research.office') {
            $payload = $this->officePayloadForEditor($payload);
        }

        $this->form->fill([
            'target_key' => $targetKey,
            'office_members' => $targetKey === 'research.office' ? $this->councilMembersForForm($payload) : [],
            'ar_landing' => $targetKey === 'research.index' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_landing' => $targetKey === 'research.index' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_publications' => $targetKey === 'research.publications' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_publications' => $targetKey === 'research.publications' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_centers' => $targetKey === 'research.centers' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_centers' => $targetKey === 'research.centers' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_projects' => $targetKey === 'research.projects' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_projects' => $targetKey === 'research.projects' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_themes' => $targetKey === 'research.themes' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_themes' => $targetKey === 'research.themes' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_experts' => $targetKey === 'research.experts' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_experts' => $targetKey === 'research.experts' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_conferences' => $targetKey === 'research.conferences' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_conferences' => $targetKey === 'research.conferences' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_library' => $targetKey === 'research.library' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_library' => $targetKey === 'research.library' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_office' => $targetKey === 'research.office' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_office' => $targetKey === 'research.office' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
            'ar_policies' => $targetKey === 'research.policies' && is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_policies' => $targetKey === 'research.policies' && is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('load_reviewed')
                ->label(__('admin.research_workspace.actions.load_reviewed', [], 'ar') !== 'admin.research_workspace.actions.load_reviewed' ? __('admin.research_workspace.actions.load_reviewed') : 'تحميل المحتوى المعتمد')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->formTargetKey() === 'research.index')
                ->requiresConfirmation()
                ->modalHeading('تحميل المحتوى المعتمد')
                ->modalDescription('سيتم ملء النموذج بالمحتوى البحثي المعتمد في database/content/research-landing.json.')
                ->action(function (): void {
                    $path = database_path('content/research-landing.json');
                    if (is_file($path)) {
                        $payload = json_decode((string) file_get_contents($path), true);
                        if (is_array($payload) && is_array($payload['translations'] ?? null)) {
                            $this->data['ar_landing'] = $payload['translations']['ar'] ?? [];
                            $this->data['en_landing'] = $payload['translations']['en'] ?? [];
                            $this->form->fill($this->data);
                            Notification::make()
                                ->title('تم تحميل المحتوى المعتمد بنجاح')
                                ->success()
                                ->send();
                        }
                    }
                }),
            Action::make('save')->label(__('admin.research_workspace.actions.save_draft'))->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label(__('admin.research_workspace.actions.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label(__('admin.research_workspace.actions.preview_en'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')
                ->label(__('admin.research_workspace.actions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('admin.research_workspace.confirm.publish_heading'))
                ->modalDescription(__('admin.research_workspace.confirm.publish_description'))
                ->visible(fn (): bool => Gate::allows('research_pages.publish'))
                ->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')
                ->label(__('admin.research_workspace.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin.research_workspace.fields.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->visible(fn (): bool => Gate::allows('research_pages.publish'))
                ->action(function (array $data): void {
                    $this->schedule((string) $data['publish_at']);
                }),
            Action::make('unpublish')
                ->label(__('admin.research_workspace.actions.unpublish'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('admin.research_workspace.confirm.unpublish_heading'))
                ->modalDescription(__('admin.research_workspace.confirm.unpublish_description'))
                ->visible(fn (): bool => Gate::allows('research_pages.publish'))
                ->action(function (): void {
                    $this->unpublish();
                }),
        ];
    }

    /** @return list<array{key: string, label: string, description: string, url: string, active: bool}> */
    public function getWorkspaceTasks(): array
    {
        $currentTarget = $this->currentTargetKey();

        return collect($this->targetOptions())
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'description' => __('admin.research_workspace.descriptions.'.str_replace(['research.', '.'], ['', '_'], $key)),
                'url' => static::getUrl(['target' => $key]),
                'active' => $key === $currentTarget,
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title(__('admin.research_workspace.notifications.draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.research_workspace.notifications.conflict'))->body(__('admin.research_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.save_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            Notification::make()->title(__('admin.research_workspace.notifications.preview_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();

            return;
        }

        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $preview = $this->cmsWorkflowService->preview($targetKey, $locale, (int) $user->id);

            $this->redirect($preview->previewUrl);
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.research_workspace.notifications.conflict'))->body(__('admin.research_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.preview_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function publish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->publish($targetKey, (int) $user->id);

            Notification::make()->title(__('admin.research_workspace.notifications.published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.research_workspace.notifications.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.publish_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function schedule(string $publishAt): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $targetKey = $this->currentTargetKey();
            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->schedule($targetKey, new \DateTimeImmutable($publishAt), (int) $user->id);

            Notification::make()->title(__('admin.research_workspace.notifications.scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.research_workspace.notifications.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.schedule_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
            $notification = Notification::make()->title($result
                ? __('admin.research_workspace.notifications.unpublished')
                : __('admin.research_workspace.notifications.nothing_published'));

            ($result ? $notification->success() : $notification->warning())->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.research_workspace.notifications.unpublish_failed'))->body(__('admin.research_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->onlyGrantedTargets([
            'research.index' => __('admin.cms.targets.research.index'),
            'research.publications' => __('admin.cms.targets.research.publications'),
            'research.centers' => __('admin.cms.targets.research.centers'),
            'research.projects' => __('admin.cms.targets.research.projects'),
            'research.themes' => __('admin.cms.targets.research.themes'),
            'research.experts' => __('admin.cms.targets.research.experts'),
            'research.conferences' => __('admin.cms.targets.research.conferences'),
            'research.library' => __('admin.cms.targets.research.library'),
            'research.office' => __('admin.cms.targets.research.office'),
            'research.policies' => __('admin.cms.targets.research.policies'),
        ]);
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'research.index');
        $this->assertResearchTarget($targetKey);

        return $targetKey;
    }

    private function assertResearchTarget(string $targetKey): void
    {
        if (! in_array($targetKey, ['research.index', 'research.publications', 'research.centers', 'research.projects', 'research.themes', 'research.experts', 'research.conferences', 'research.library', 'research.office', 'research.policies'], true)) {
            throw new \InvalidArgumentException('Unsupported research target.');
        }
    }

    /** @return array<int, Section> */
    private function landingFields(string $locale): array
    {
        $prefix = $locale.'_landing';
        $sections = [
            Section::make($this->sectionLabel('landing_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.cta1')->label($this->fieldLabel('primary_cta'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.hero.cta1Url', $this->fieldLabel('primary_cta_url'), $locale, true),
                TextInput::make($prefix.'.hero.cta2')->label($this->fieldLabel('secondary_cta'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.hero.cta2Url', $this->fieldLabel('secondary_cta_url'), $locale, true),
            ])->columns(2),
            Section::make($this->sectionLabel('landing_stats'))->schema([
                Repeater::make($prefix.'.stats')
                    ->label($this->fieldLabel('statistics'))
                    ->schema([
                        Select::make('counter')
                            ->label($this->fieldLabel('counter'))
                            ->helperText(__('admin.research_workspace.help.counter'))
                            ->options([
                                'publications' => $this->fieldLabel('counter_publications'),
                                'projects' => $this->fieldLabel('counter_projects'),
                            ])
                            // Pages saved before the projects counter stored `true` for publications.
                            ->formatStateUsing(fn (mixed $state): ?string => $state === true ? 'publications' : (in_array($state, ['publications', 'projects'], true) ? $state : null))
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('value')
                            ->label($this->fieldLabel('value'))
                            ->required(fn (Get $get): bool => ! $get('counter'))
                            ->disabled(fn (Get $get): bool => (bool) $get('counter'))
                            ->placeholder(fn (Get $get): ?string => match ($get('counter')) {
                                'publications' => (string) $this->researchPageService->publicationCount($locale),
                                'projects' => (string) $this->researchPageService->projectCount($locale),
                                default => null,
                            })
                            ->maxLength(60),
                        TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(140),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
            Section::make($this->sectionLabel('latest_research'))
                ->description(__('admin.research_workspace.help.latest_research'))
                ->schema([
                    TextInput::make($prefix.'.latestResearch.sectionTitle')->label($this->fieldLabel('section_title'))->maxLength(160)->placeholder(__('research.latest.title', [], $locale)),
                ]),
            Section::make($this->sectionLabel('research_gateway'))->schema([
                TextInput::make($prefix.'.gateway.sectionTitle')->label($this->fieldLabel('section_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.gateway.cards')
                    ->label($this->fieldLabel('gateway_cards'))
                    ->schema([
                        TextInput::make('number')->label($this->fieldLabel('number'))->required()->maxLength(20),
                        TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(160),
                        Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                        TextInput::make('cta')->label($this->fieldLabel('cta_label'))->required()->maxLength(120),
                        PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];

        return $this->targetSections($sections, 'research.index', $locale);
    }

    /** @return array<int, Section> */
    private function publicationFields(string $locale): array
    {
        $prefix = $locale.'_publications';

        $sections = [
            Section::make($this->sectionLabel('publications_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),

            Section::make($this->sectionLabel('filters'))->schema([
                TextInput::make($prefix.'.filters.facultyLabel')->label($this->fieldLabel('faculty_filter_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.filters.typeLabel')->label($this->fieldLabel('type_filter_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.filters.yearLabel')->label($this->fieldLabel('year_filter_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.filters.searchPlaceholder')->label($this->fieldLabel('search_placeholder'))->required()->maxLength(160),
                Repeater::make($prefix.'.filters.faculties')->label($this->fieldLabel('faculty_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.publicationTypes')->label($this->fieldLabel('publication_type_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.years')->label($this->fieldLabel('year_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),

            // Publications are managed in the Research publications list now; this
            // stays only so editors can review and remove the old hand-made items.
            Section::make($this->sectionLabel('publication_items'))
                ->description(fn (): HtmlString => new HtmlString(e(__('admin.research_publication.help.legacy_items')).' <a class="underline" href="'.e(ResearchPublicationResource::getUrl('index')).'">'.e(__('admin.research_publication.plural_model')).'</a>'))
                ->schema([
                    Repeater::make($prefix.'.items')
                        ->label($this->fieldLabel('publications'))
                        ->schema($this->publicationItemFields())
                        ->defaultItems(0)
                        ->addable(false)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool => is_array($this->data[$prefix]['items'] ?? null) && $this->data[$prefix]['items'] !== [])
                ->collapsible()
                ->collapsed(),
        ];

        return $this->targetSections($sections, 'research.publications', $locale);
    }

    /** @return array<int, Section> */
    private function centerFields(string $locale): array
    {
        $prefix = $locale.'_centers';
        $sections = [
            Section::make($this->sectionLabel('centers_hero'))->schema([
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.primaryCta')->label($this->fieldLabel('primary_cta'))->required()->maxLength(120),
                TextInput::make($prefix.'.hero.secondaryCta')->label($this->fieldLabel('secondary_cta'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.hero.secondaryCtaUrl', $this->fieldLabel('secondary_cta_url'), $locale, true)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')
                    ->label($this->fieldLabel('breadcrumbs'))
                    ->schema([
                        TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(120),
                        PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('centers_introduction'))->schema([
                TextInput::make($prefix.'.intro.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Textarea::make($prefix.'.intro.summary')->label($this->fieldLabel('summary'))->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.intro.highlights')
                    ->label($this->fieldLabel('highlights'))
                    ->schema([
                        TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                        MediaPicker::image('icon', $this->fieldLabel('icon'), true),
                        Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('research_centers'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('centers'))
                    ->schema($this->centerItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('research_laboratories'))->schema([
                TextInput::make($prefix.'.laboratories.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.laboratories.items')
                    ->label($this->fieldLabel('laboratories'))
                    ->schema($this->laboratoryItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.centers', $locale);
    }

    /** @return array<int, Section> */
    private function projectFields(string $locale): array
    {
        $prefix = $locale.'_projects';
        $sections = [
            Section::make($this->sectionLabel('projects_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')->label($this->fieldLabel('breadcrumbs'))->schema([
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(120),
                    PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('project_filters'))->schema([
                TextInput::make($prefix.'.filters.statusLabel')->label($this->fieldLabel('status_filter_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.facultyLabel')->label($this->fieldLabel('faculty_filter_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.themeLabel')->label($this->fieldLabel('theme_filter_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.searchPlaceholder')->label($this->fieldLabel('search_placeholder'))->required()->maxLength(180),
                Repeater::make($prefix.'.filters.statuses')->label($this->fieldLabel('status_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.faculties')->label($this->fieldLabel('faculty_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                Repeater::make($prefix.'.filters.themes')->label($this->fieldLabel('theme_options'))->schema($this->optionFields())->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('project_card_labels'))->schema([
                TextInput::make($prefix.'.cardLabels.viewProject')->label($this->fieldLabel('view_project_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.cardLabels.since')->label($this->fieldLabel('since_label'))->required()->maxLength(80),
            ])->columns(2),
            Section::make($this->sectionLabel('research_projects'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('projects'))
                    ->schema($this->projectItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.projects', $locale);
    }

    /** @return array<int, Section> */
    private function themeFields(string $locale): array
    {
        $prefix = $locale.'_themes';
        $sections = [
            Section::make($this->sectionLabel('themes_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.hero.breadcrumbs')->label($this->fieldLabel('breadcrumbs'))->schema([
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(120),
                    PageUrlSelect::make('url', $this->fieldLabel('url'), $locale, true),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('research_themes'))->schema([
                Repeater::make($prefix.'.items')
                    ->label($this->fieldLabel('themes'))
                    ->schema($this->themeItemFields())
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? $state['slug'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.themes', $locale);
    }

    /** @return array<int, Section> */
    private function expertFields(string $locale): array
    {
        $prefix = $locale.'_experts';
        $sections = [
            Section::make($this->sectionLabel('expert_finder_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.searchPlaceholder')->label($this->fieldLabel('search_placeholder'))->required()->maxLength(180)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('expert_filters'))->schema([
                TextInput::make($prefix.'.filters.allFaculties')->label($this->fieldLabel('all_faculties_label'))->required()->maxLength(100),
                TextInput::make($prefix.'.filters.allExpertise')->label($this->fieldLabel('all_expertise_label'))->required()->maxLength(100),
            ])->columns(2)->collapsible()->collapsed(),
            // The people, faculties and expertise filters come from the
            // Researchers list and staff profiles, not from this page.
            Section::make($this->sectionLabel('expert_profiles'))->schema([
                Placeholder::make($prefix.'_directory_source')
                    ->hiddenLabel()
                    ->content(__('admin.researcher.help.directory_source')),
            ]),
        ];

        return $this->targetSections($sections, 'research.experts', $locale);
    }

    /** @return array<int, Section> */
    private function conferenceFields(string $locale): array
    {
        $prefix = $locale.'_conferences';
        $sections = [
            Section::make($this->sectionLabel('conferences_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('upcoming_events'))->schema([
                TextInput::make($prefix.'.upcomingSection.title')->label($this->fieldLabel('section_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.upcoming')
                    ->label($this->fieldLabel('upcoming_events'))
                    ->schema($this->conferenceEventFields(true))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('past_conferences'))->schema([
                TextInput::make($prefix.'.pastSection.title')->label($this->fieldLabel('section_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.pastSection.proceedings')->label($this->fieldLabel('proceedings_label'))->maxLength(120),
                Repeater::make($prefix.'.past')
                    ->label($this->fieldLabel('past_conferences'))
                    ->schema($this->conferenceEventFields(false))
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
        ];

        return $this->targetSections($sections, 'research.conferences', $locale);
    }

    /** @return array<int, Section> */
    private function libraryFields(string $locale): array
    {
        $prefix = $locale.'_library';
        $sections = [
            Section::make($this->sectionLabel('library_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('digital_resources'))->schema([
                TextInput::make($prefix.'.resourcesSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.resourcesSection.subtitle')->label($this->fieldLabel('subtitle'))->maxLength(220)->columnSpanFull(),
                Repeater::make($prefix.'.databases')
                    ->label($this->fieldLabel('databases'))
                    ->schema([
                        TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180),
                        TextInput::make('accessType')->label($this->fieldLabel('access_type'))->required()->maxLength(120),
                        PageUrlSelect::make('url', $this->fieldLabel('url'), null, true)->columnSpanFull(),
                        Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed(),
            Section::make($this->sectionLabel('borrowing_rules'))->schema([
                TextInput::make($prefix.'.borrowingSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.borrowingSection.rules')
                    ->label($this->fieldLabel('rules'))
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('special_collections'))->schema([
                TextInput::make($prefix.'.specialCollections.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Repeater::make($prefix.'.specialCollections.items')
                    ->label($this->fieldLabel('collections'))
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('librarian_contact'))->schema([
                TextInput::make($prefix.'.librarianSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.name')->label($this->fieldLabel('name'))->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.hours')->label($this->fieldLabel('hours'))->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.email')->label($this->fieldLabel('email'))->email()->required()->maxLength(180),
                TextInput::make($prefix.'.librarianSection.phone')->label($this->fieldLabel('phone'))->required()->maxLength(80),
            ])->columns(2),
        ];

        return $this->targetSections($sections, 'research.library', $locale);
    }

    /**
     * The research and scientific publication council page: its introduction, the
     * headings around the members, and the council's functions, in each language.
     * The members themselves are one list for both languages (councilMembersSection).
     *
     * @return array<int, Section>
     */
    private function officeFields(string $locale): array
    {
        $prefix = $locale.'_office';
        $sections = [
            Section::make($this->sectionLabel('office_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('council_headings'))
                ->description(__('admin.research_workspace.help.council_headings'))
                ->schema([
                    TextInput::make($prefix.'.council.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                    TextInput::make($prefix.'.council.subtitle')->label($this->fieldLabel('subtitle'))->maxLength(255),
                    TextInput::make($prefix.'.council.headLabel')->label($this->fieldLabel('council_head_label'))->required()->maxLength(80),
                    TextInput::make($prefix.'.council.memberLabel')->label($this->fieldLabel('council_member_label'))->required()->maxLength(80),
                    TextInput::make($prefix.'.council.profileLabel')->label($this->fieldLabel('council_profile_label'))->required()->maxLength(80),
                ])->columns(2)->collapsible(),
            Section::make($this->sectionLabel('council_functions'))->schema([
                TextInput::make($prefix.'.functions.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                TextInput::make($prefix.'.functions.subtitle')->label($this->fieldLabel('subtitle'))->maxLength(255),
                Repeater::make($prefix.'.functions.items')
                    ->label($this->fieldLabel('council_functions'))
                    ->schema($this->titleDescriptionFields())
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                    ->addActionLabel(__('admin.research_workspace.actions.add_council_function'))
                    ->columnSpanFull(),
            ])->columns(2)->collapsible(),
        ];

        return $this->targetSections($sections, 'research.office', $locale);
    }

    /**
     * The council members, one row for both languages. Each member is the council
     * head or a member. A teaching staff member is picked from the staff list and
     * the page shows their profile (name, rank, photo, email, profile link); anyone
     * else is typed in by name, with an optional position and photo, and has no
     * profile link.
     */
    private function councilMembersSection(): Section
    {
        $isStaff = static fn (Get $get): bool => (bool) $get('isStaff');

        return Section::make($this->sectionLabel('council_members'))
            ->description(__('admin.research_workspace.help.council_members'))
            ->schema([
                Repeater::make('office_members')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('id')->default(fn (): string => 'member-'.Str::lower(Str::random(8))),
                        Select::make('role')
                            ->label($this->fieldLabel('council_role'))
                            ->options([
                                'head' => __('admin.research_workspace.options.council_roles.head'),
                                'member' => __('admin.research_workspace.options.council_roles.member'),
                            ])
                            ->default('member')
                            ->selectablePlaceholder(false)
                            ->required(),
                        Checkbox::make('isStaff')
                            ->label($this->fieldLabel('council_is_staff'))
                            ->helperText(__('admin.research_workspace.help.council_is_staff'))
                            ->default(false)
                            ->live(),
                        Select::make('staffId')
                            ->label($this->fieldLabel('council_staff_member'))
                            ->options(fn (): array => $this->councilStaffOptions())
                            ->searchable()
                            ->live()
                            ->required($isStaff)
                            ->visible($isStaff)
                            ->columnSpanFull(),
                        Placeholder::make('staffPreview')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => $this->staffPreview($get('staffId')))
                            ->visible(fn (Get $get): bool => (bool) $get('isStaff') && is_numeric($get('staffId')))
                            ->columnSpanFull(),
                        TextInput::make('nameAr')
                            ->label($this->fieldLabel('council_name_ar'))
                            ->required(fn (Get $get): bool => ! $get('isStaff'))
                            ->visible(fn (Get $get): bool => ! $get('isStaff'))
                            ->maxLength(180),
                        TextInput::make('nameEn')
                            ->label($this->fieldLabel('council_name_en'))
                            ->helperText(__('admin.research_workspace.help.council_name_en'))
                            ->visible(fn (Get $get): bool => ! $get('isStaff'))
                            ->maxLength(180),
                        TextInput::make('positionAr')
                            ->label($this->fieldLabel('council_position_ar'))
                            ->helperText(__('admin.research_workspace.help.council_position'))
                            ->maxLength(180),
                        TextInput::make('positionEn')
                            ->label($this->fieldLabel('council_position_en'))
                            ->maxLength(180),
                        MediaPicker::image('image', $this->fieldLabel('profile_image'))
                            ->visible(fn (Get $get): bool => ! $get('isStaff'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $this->councilMemberLabel($state))
                    ->addActionLabel(__('admin.research_workspace.actions.add_council_member'))
                    ->columnSpanFull(),
            ])
            ->visible(fn (Get $get): bool => $get('target_key') === 'research.office')
            ->columnSpanFull();
    }

    /** @return array<int, string> */
    private function councilStaffOptions(): array
    {
        return $this->councilStaffOptions ??= app(ResearchCouncilStaffServiceInterface::class)->staffOptions(app()->getLocale());
    }

    /** What the picked staff member's profile shows, so the editor sees who they picked. */
    private function staffPreview(mixed $staffId): HtmlString
    {
        $id = is_numeric($staffId) ? (int) $staffId : 0;
        $card = app(ResearchCouncilStaffServiceInterface::class)->staffCards([$id], app()->getLocale() === 'ar' ? 'ar' : 'en', false)[$id] ?? null;
        if ($card === null) {
            return new HtmlString('<p class="text-sm text-danger-600">'.e(__('admin.research_workspace.help.council_staff_missing')).'</p>');
        }

        $photo = is_string($card['image']) && $card['image'] !== ''
            ? '<img src="'.e($card['image']).'" alt="" style="width:56px;height:56px;border-radius:9999px;object-fit:cover">'
            : '<span style="display:inline-flex;width:56px;height:56px;border-radius:9999px;background:#e5e7eb"></span>';
        $lines = array_filter([
            '<strong>'.e($card['name']).'</strong>',
            $card['rank'] !== '' ? e($card['rank']) : null,
            $card['email'] !== '' ? e($card['email']) : null,
            '<a href="'.e($card['url']).'" target="_blank" rel="noopener" class="text-primary-600 underline">'.e(__('admin.research_workspace.help.council_staff_profile')).'</a>',
            $card['isPublic'] ? null : '<span class="text-warning-600">'.e(__('admin.research_workspace.help.council_staff_not_public')).'</span>',
        ]);

        return new HtmlString('<div style="display:flex;gap:12px;align-items:center">'.$photo.'<div class="text-sm" style="display:flex;flex-direction:column;gap:2px">'.implode('', array_map(static fn (string $line): string => '<span>'.$line.'</span>', $lines)).'</div></div>');
    }

    /** @param array<string, mixed> $state */
    private function councilMemberLabel(array $state): ?string
    {
        $role = ($state['role'] ?? 'member') === 'head'
            ? __('admin.research_workspace.options.council_roles.head')
            : __('admin.research_workspace.options.council_roles.member');
        $name = null;
        if ((bool) ($state['isStaff'] ?? false)) {
            $id = is_numeric($state['staffId'] ?? null) ? (int) $state['staffId'] : 0;
            $name = $id > 0 ? ($this->councilStaffOptions()[$id] ?? null) : null;
        } else {
            $name = trim((string) ($state['nameAr'] ?? '')) ?: (trim((string) ($state['nameEn'] ?? '')) ?: null);
        }

        return trim(implode(' · ', array_filter([$role, $name]))) ?: null;
    }

    /**
     * Content saved before the council layout: its leadership becomes the members
     * (typed in, as members) and its services become the functions. The old
     * statistics and contact are not part of the page any more.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function officePayloadForEditor(array $payload): array
    {
        foreach (['ar', 'en'] as $locale) {
            $content = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
            if (! is_array($content['council']['members'] ?? null) && is_array($content['leadership'] ?? null)) {
                $content['council'] = [
                    ...(is_array($content['council'] ?? null) ? $content['council'] : []),
                    'title' => $content['council']['title'] ?? ($content['leadership']['title'] ?? ''),
                    'members' => array_map(static fn (array $leader, int $index): array => [
                        'id' => 'member-legacy-'.($index + 1),
                        'role' => 'member',
                        'isStaff' => false,
                        'staffId' => null,
                        'name' => (string) ($leader['name'] ?? ''),
                        'position' => (string) ($leader['role'] ?? ''),
                        'image' => $leader['image'] ?? null,
                    ], $this->listOfArrays($content['leadership']['items'] ?? []), array_keys($this->listOfArrays($content['leadership']['items'] ?? []))),
                ];
            }
            if (! is_array($content['functions'] ?? null) && is_array($content['services'] ?? null)) {
                $content['functions'] = [
                    'title' => $content['services']['title'] ?? '',
                    'subtitle' => $content['services']['subtitle'] ?? '',
                    'items' => $this->listOfArrays($content['services']['items'] ?? []),
                ];
            }
            unset($content['leadership'], $content['services'], $content['statistics'], $content['contact']);
            $payload['translations'][$locale] = $content;
        }

        return $payload;
    }

    /**
     * The members as editor rows: the Arabic and English lists hold the same
     * members in the same order, so each row joins the two by position.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function councilMembersForForm(array $payload): array
    {
        $ar = $this->listOfArrays($payload['translations']['ar']['council']['members'] ?? []);
        $en = $this->listOfArrays($payload['translations']['en']['council']['members'] ?? []);
        $rows = [];

        foreach ($ar === [] ? $en : $ar as $index => $member) {
            $english = $en[$index] ?? [];
            $id = trim((string) ($member['id'] ?? ''));
            $rows[] = [
                'id' => $id !== '' ? $id : 'member-'.Str::lower(Str::random(8)),
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

        return $rows;
    }

    /**
     * The editor rows as each language's members. An English name left empty
     * shows the Arabic one.
     *
     * @param  array<int, mixed>  $rows
     * @return array{ar: array<int, array<string, mixed>>, en: array<int, array<string, mixed>>}
     */
    private function councilMembersFromForm(array $rows): array
    {
        $members = ['ar' => [], 'en' => []];

        foreach ($this->listOfArrays($rows) as $row) {
            $isStaff = (bool) ($row['isStaff'] ?? false);
            $id = trim((string) ($row['id'] ?? ''));
            $nameAr = $isStaff ? '' : trim((string) ($row['nameAr'] ?? ''));
            $nameEn = $isStaff ? '' : trim((string) ($row['nameEn'] ?? ''));
            $image = $isStaff ? null : (is_string($row['image'] ?? null) && trim($row['image']) !== '' ? trim($row['image']) : null);
            $shared = [
                'id' => $id !== '' ? $id : 'member-'.Str::lower(Str::random(8)),
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

    /** @return array<int, Section> */
    private function policyFields(string $locale): array
    {
        $prefix = $locale.'_policies';
        $sections = [
            Section::make($this->sectionLabel('policies_hero'))->schema([
                TextInput::make($prefix.'.hero.eyebrow')->label($this->fieldLabel('eyebrow'))->required()->maxLength(160),
                TextInput::make($prefix.'.hero.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero.backgroundImage', $this->fieldLabel('background_image'), true),
                Textarea::make($prefix.'.hero.summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
            Section::make($this->sectionLabel('policy_sections'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label($this->fieldLabel('policy_sections'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                        Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
                        Toggle::make('documentsUnavailable')
                            ->label($this->fieldLabel('documents_unavailable'))
                            ->helperText($this->fieldLabel('documents_unavailable_help'))
                            ->live(),
                        Repeater::make('documents')
                            ->label($this->fieldLabel('documents'))
                            ->schema([
                                TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                                TextInput::make('fileType')->label($this->fieldLabel('file_type'))->required()->maxLength(40),
                                MediaPicker::document('url', $this->fieldLabel('document_file'))->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->visible(fn (Get $get): bool => ! (bool) $get('documentsUnavailable'))
                            ->reorderable()
                            ->cloneable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->cloneable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['id'] ?? null)
                    ->columnSpanFull(),
            ])->collapsible()->collapsed(),
            Section::make($this->sectionLabel('policy_contact'))->schema([
                TextInput::make($prefix.'.contactSection.title')->label($this->fieldLabel('title'))->required()->maxLength(180),
                Textarea::make($prefix.'.contactSection.description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.contactSection.email')->label($this->fieldLabel('email'))->email()->required()->maxLength(180),
                TextInput::make($prefix.'.contactSection.phone')->label($this->fieldLabel('phone'))->required()->maxLength(80),
                TextInput::make($prefix.'.contactSection.location')->label($this->fieldLabel('location'))->required()->maxLength(220)->columnSpanFull(),
            ])->columns(2),
        ];

        return $this->targetSections($sections, 'research.policies', $locale);
    }

    /** @return array<int, mixed> */
    private function optionFields(): array
    {
        return [
            Hidden::make('value')->dehydrated(),
            TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(160),
        ];
    }

    /** @return array<int, mixed> */
    private function titleDescriptionFields(): array
    {
        return [
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180),
            Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function centerItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('mission')->label($this->fieldLabel('mission'))->required()->rows(3)->columnSpanFull(),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            Select::make('facultySlug')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions()),
            TextInput::make('directorName')->label($this->fieldLabel('director_name'))->required()->maxLength(180),
            TextInput::make('contactEmail')->label($this->fieldLabel('contact_email'))->email()->required()->maxLength(180),
            TextInput::make('contactPhone')->label($this->fieldLabel('contact_phone'))->maxLength(80),
            MediaPicker::image('image', $this->fieldLabel('center_image'), true)->columnSpanFull(),
            Section::make($this->sectionLabel('advanced'))->schema([
                TextInput::make('externalWebsite')->label($this->fieldLabel('external_website'))->url()->maxLength(255)->columnSpanFull(),
                Hidden::make('labs')->dehydrated(),
                Hidden::make('researchers')->dehydrated(),
                Hidden::make('projects')->dehydrated(),
                Hidden::make('publications')->dehydrated(),
                TagsInput::make('publicationSlugs')->label($this->fieldLabel('related_publications'))->columnSpanFull(),
                TagsInput::make('projectSlugs')->label($this->fieldLabel('related_projects'))->columnSpanFull(),
                TagsInput::make('researcherSlugs')->label($this->fieldLabel('affiliated_researchers'))->columnSpanFull(),
            ])->columns(2)->collapsible()->collapsed()->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function laboratoryItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(180)->columnSpanFull(),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            TextInput::make('director')->label($this->fieldLabel('director'))->required()->maxLength(180),
            Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            TextInput::make('projects')->label($this->fieldLabel('projects'))->required()->maxLength(180),
            TextInput::make('publications')->label($this->fieldLabel('publications'))->required()->maxLength(180),
            TextInput::make('contact')->label($this->fieldLabel('contact'))->required()->maxLength(180),
            TextInput::make('cta')->label($this->fieldLabel('cta_label'))->required()->maxLength(120),
            MediaPicker::image('image', $this->fieldLabel('laboratory_image'), true)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function projectItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
            Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(3)->columnSpanFull(),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(180),
            Select::make('facultySlug')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions()),
            TextInput::make('theme')->label($this->fieldLabel('theme'))->required()->maxLength(180),
            Select::make('themeSlug')->label($this->fieldLabel('theme_assignment'))->required()->options($this->themeOptions()),
            Select::make('status')->label($this->fieldLabel('status'))->required()->options([
                'ongoing' => __('admin.research_workspace.options.statuses.ongoing'),
                'completed' => __('admin.research_workspace.options.statuses.completed'),
                'paused' => __('admin.research_workspace.options.statuses.paused'),
            ]),
            TextInput::make('startYear')->label($this->fieldLabel('start_year'))->required()->numeric()->minValue(1900)->maxValue(2200),
            TextInput::make('endYear')->label($this->fieldLabel('end_year'))->numeric()->minValue(1900)->maxValue(2200),
            TextInput::make('funding')->label($this->fieldLabel('funding'))->required()->maxLength(180)->columnSpanFull(),
            MediaPicker::image('image', $this->fieldLabel('project_image'), true)->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function themeItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('name')->label($this->fieldLabel('name'))->required()->maxLength(180)->columnSpanFull(),
            Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(3)->columnSpanFull(),
            MediaPicker::image('icon', $this->fieldLabel('theme_icon'), true)->columnSpanFull(),
            Hidden::make('publicationCount')->dehydrated(),
            Hidden::make('projectCount')->dehydrated(),
        ];
    }

    /** @return array<int, mixed> */
    private function publicationItemFields(): array
    {
        return [
            Hidden::make('id')->dehydrated(),
            Hidden::make('slug')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
            Textarea::make('summary')->label($this->fieldLabel('summary'))->required()->rows(2)->columnSpanFull(),
            TextInput::make('type')->label($this->fieldLabel('publication_type'))->required()->maxLength(120),
            TextInput::make('faculty')->label($this->fieldLabel('faculty'))->required()->maxLength(160),
            TextInput::make('author')->label($this->fieldLabel('author'))->required()->maxLength(160),
            TextInput::make('year')->label($this->fieldLabel('year'))->required()->maxLength(20),
            MediaPicker::image('image', $this->fieldLabel('publication_image'), true)->columnSpanFull(),
            Textarea::make('lead')->label($this->fieldLabel('detail_lead'))->rows(2)->columnSpanFull(),
            RichEditor::make('body')
                ->label($this->fieldLabel('detail_body'))
                ->toolbarButtons(['bold', 'bulletList', 'h2', 'h3', 'highlight', 'italic', 'link', 'orderedList', 'redo', 'strike', 'underline', 'undo'])
                ->columnSpanFull(),
            Hidden::make('paragraphs')->dehydrated(),
            Textarea::make('keyStatement')->label($this->fieldLabel('key_statement'))->rows(2)->columnSpanFull(),
            TagsInput::make('keywords')->label($this->fieldLabel('keywords'))->columnSpanFull(),
            Repeater::make('downloads')
                ->label($this->fieldLabel('publication_files'))
                ->schema([
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(180),
                    TextInput::make('type')->label($this->fieldLabel('file_type'))->maxLength(40),
                    MediaPicker::document('url', $this->fieldLabel('publication_file'), true)->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->columnSpanFull(),
            Hidden::make('isOpenAccess')->dehydrated(),
            Placeholder::make('open_access_guidance')->label($this->fieldLabel('open_access'))->content(__('admin.research_workspace.help.open_access'))->columnSpanFull(),
            Section::make($this->sectionLabel('advanced'))->schema([
                TextInput::make('typeSlug')->label($this->fieldLabel('publication_type_slug'))->required()->maxLength(120),
                Select::make('facultySlug')->label($this->fieldLabel('faculty_assignment'))->required()->options($this->facultyOptions()),
                TextInput::make('authorSlug')->label($this->fieldLabel('author_slug'))->required()->maxLength(160),
                TextInput::make('doi')->label($this->fieldLabel('doi'))->maxLength(180),
                TextInput::make('journalTitle')->label($this->fieldLabel('journal_proceedings'))->maxLength(180),
                TextInput::make('volume')->label($this->fieldLabel('volume'))->maxLength(40),
                TextInput::make('issue')->label($this->fieldLabel('issue'))->maxLength(40),
                TextInput::make('pages')->label($this->fieldLabel('pages'))->maxLength(40),
                TextInput::make('issn')->label($this->fieldLabel('issn'))->maxLength(40),
                TextInput::make('license')->label($this->fieldLabel('license'))->maxLength(180)->columnSpanFull(),
                TagsInput::make('themes')->label($this->fieldLabel('theme_slugs'))->columnSpanFull(),
                Repeater::make('resolvedThemes')->label($this->fieldLabel('resolved_themes'))->schema([
                    Hidden::make('slug')->dehydrated(),
                    TextInput::make('label')->label($this->fieldLabel('label'))->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->reorderable()->collapsible()->columnSpanFull(),
                TextInput::make('scholarUrl')->label($this->fieldLabel('scholar_url'))->maxLength(255)->columnSpanFull(),
                TextInput::make('scopusUrl')->label($this->fieldLabel('scopus_url'))->maxLength(255)->columnSpanFull(),
                TextInput::make('category')->label($this->fieldLabel('category'))->maxLength(120),
                TextInput::make('rate')->label($this->fieldLabel('ranking'))->maxLength(80),
                Toggle::make('gsIndexed')->label($this->fieldLabel('google_scholar_indexed')),
            ])->columns(2)->collapsible()->collapsed()->columnSpanFull(),
        ];
    }

    /** @return array<int, mixed> */
    private function conferenceEventFields(bool $upcoming): array
    {
        $fields = [
            Hidden::make('id')->dehydrated(),
            TextInput::make('title')->label($this->fieldLabel('title'))->required()->maxLength(240)->columnSpanFull(),
            TextInput::make('date')->label($this->fieldLabel('date'))->required()->maxLength(120),
            TextInput::make('location')->label($this->fieldLabel('location'))->required()->maxLength(180),
            MediaPicker::image('image', $this->fieldLabel('event_image'), true)->columnSpanFull(),
            Textarea::make('description')->label($this->fieldLabel('description'))->required()->rows(2)->columnSpanFull(),
            // The text date above is what the page shows; these place the
            // conference on the news events calendar.
            Toggle::make('showOnCalendar')
                ->label($this->fieldLabel('show_on_calendar'))
                ->helperText($this->fieldLabel('show_on_calendar_help'))
                ->live()
                ->columnSpanFull(),
            DatePicker::make('startsOn')
                ->label($this->fieldLabel('starts_on'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->required(fn (Get $get): bool => (bool) $get('showOnCalendar'))
                ->visible(fn (Get $get): bool => (bool) $get('showOnCalendar')),
            DatePicker::make('endsOn')
                ->label($this->fieldLabel('ends_on'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->afterOrEqual('startsOn')
                ->visible(fn (Get $get): bool => (bool) $get('showOnCalendar')),
        ];

        if ($upcoming) {
            $fields[] = TextInput::make('eventType')->label($this->fieldLabel('event_type'))->required()->maxLength(160);
            // The conference keeps its address in registrationUrl, so a form on another site is stored there.
            array_push($fields, ...FormPicker::withSource('formId', 'formSource', 'registrationUrl', false, $this->fieldLabel('registration_form'), __('admin.research_workspace.options.registration_forms.none')));

            return $fields;
        }

        $fields[] = TextInput::make('participants')->label($this->fieldLabel('participants'))->maxLength(120);
        $fields[] = Toggle::make('hasProceedings')->label($this->fieldLabel('proceedings_available'));
        $fields[] = Toggle::make('proceedingsUnavailable')
            ->label($this->fieldLabel('proceedings_unavailable'))
            ->helperText($this->fieldLabel('proceedings_unavailable_help'))
            ->visible(fn (Get $get): bool => (bool) $get('hasProceedings'));
        $fields[] = MediaPicker::document('proceedingsUrl', $this->fieldLabel('proceedings_file'))
            ->visible(fn (Get $get): bool => (bool) $get('hasProceedings') && ! (bool) $get('proceedingsUnavailable'))
            ->columnSpanFull();

        return $fields;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function payloadFromForm(array $state): array
    {
        $targetKey = (string) ($state['target_key'] ?? 'research.index');
        [$stateKey, $normalizer] = match ($targetKey) {
            'research.index' => ['landing', 'normalizeLandingContent'],
            'research.centers' => ['centers', 'normalizeCenterContent'],
            'research.projects' => ['projects', 'normalizeProjectContent'],
            'research.themes' => ['themes', 'normalizeThemeContent'],
            'research.experts' => ['experts', 'normalizeExpertContent'],
            'research.conferences' => ['conferences', 'normalizeConferenceContent'],
            'research.library' => ['library', 'normalizeLibraryContent'],
            'research.office' => ['office', 'normalizeOfficeContent'],
            'research.policies' => ['policies', 'normalizePolicyContent'],
            default => ['publications', 'normalizePublicationContent'],
        };
        $payload = [
            'translations' => [
                'ar' => $this->{$normalizer}(is_array($state['ar_'.$stateKey] ?? null) ? $state['ar_'.$stateKey] : []),
                'en' => $this->{$normalizer}(is_array($state['en_'.$stateKey] ?? null) ? $state['en_'.$stateKey] : []),
            ],
        ];

        if ($targetKey === 'research.office') {
            $members = $this->councilMembersFromForm(is_array($state['office_members'] ?? null) ? $state['office_members'] : []);
            foreach (['ar', 'en'] as $locale) {
                $payload['translations'][$locale]['council']['members'] = $members[$locale];
            }
        }

        $payload = $this->synchronizeGeneratedIdentity($targetKey, $payload);
        $merged = $this->mergePreservingLegacy($this->sourcePayload, $payload);

        if ($targetKey === 'research.office') {
            // The council page has no statistics or contact sections; its members
            // and functions replaced the old leadership and services.
            foreach (['ar', 'en'] as $locale) {
                unset(
                    $merged['translations'][$locale]['leadership'],
                    $merged['translations'][$locale]['services'],
                    $merged['translations'][$locale]['statistics'],
                    $merged['translations'][$locale]['contact'],
                );
            }
        }

        return $merged;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizePublicationContent(array $content): array
    {
        $content['filters']['faculties'] = $this->listOfArrays($content['filters']['faculties'] ?? []);
        $content['filters']['publicationTypes'] = $this->listOfArrays($content['filters']['publicationTypes'] ?? []);
        $content['filters']['years'] = $this->listOfArrays($content['filters']['years'] ?? []);
        $content['items'] = array_map(function (array $item): array {
            $slug = trim((string) ($item['slug'] ?? ''));
            if ($slug !== '') {
                $item['links']['local'] = '/research/publications/'.$slug.'/';
            }
            $item['body'] = $this->htmlSanitizer->sanitize(trim((string) ($item['body'] ?? '')));
            $item['paragraphs'] = $this->listOfStrings($item['paragraphs'] ?? []);
            $item['keywords'] = $this->listOfStrings($item['keywords'] ?? []);
            $item['themes'] = $this->listOfStrings($item['themes'] ?? []);
            $item['resolvedThemes'] = $this->listOfArrays($item['resolvedThemes'] ?? []);
            $item['downloads'] = $this->listOfArrays($item['downloads'] ?? []);
            $item['isOpenAccess'] = collect($item['downloads'])->contains(
                static fn (array $download): bool => is_string($download['url'] ?? null) && trim((string) $download['url']) !== '' && $download['url'] !== '#'
            );
            $item['gsIndexed'] = (bool) ($item['gsIndexed'] ?? false);

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function publicationBodyForEditor(string $targetKey, array $payload): array
    {
        if ($targetKey !== 'research.publications') {
            return $payload;
        }

        foreach (['ar', 'en'] as $locale) {
            foreach (($payload['translations'][$locale]['items'] ?? []) as $index => $item) {
                if (! is_array($item) || trim((string) ($item['body'] ?? '')) !== '') {
                    continue;
                }

                $paragraphs = $this->listOfStrings($item['paragraphs'] ?? []);
                $payload['translations'][$locale]['items'][$index]['body'] = implode('', array_map(
                    static fn (string $paragraph): string => '<p>'.e($paragraph).'</p>',
                    $paragraphs,
                ));
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeCenterContent(array $content): array
    {
        $content['hero']['breadcrumbs'] = $this->listOfArrays($content['hero']['breadcrumbs'] ?? []);
        $content['intro']['highlights'] = $this->listOfArrays($content['intro']['highlights'] ?? []);
        $content['items'] = array_map(function (array $item): array {
            $item['facultySlug'] = $this->canonicalFacultySlug(strtolower(trim((string) ($item['facultySlug'] ?? ''))));
            foreach (['labs', 'researchers', 'projects', 'publications'] as $field) {
                $item[$field] = is_numeric($item[$field] ?? null) ? max(0, (int) $item[$field]) : 0;
            }

            $item['publicationSlugs'] = $this->listOfStrings($item['publicationSlugs'] ?? []);
            $item['projectSlugs'] = $this->listOfStrings($item['projectSlugs'] ?? []);
            $item['researcherSlugs'] = $this->listOfStrings($item['researcherSlugs'] ?? []);

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));
        $content['laboratories']['items'] = array_map(function (array $item): array {
            $slug = trim((string) ($item['slug'] ?? ''));
            $item['ctaUrl'] = $slug !== '' ? '/research/centers/'.$slug.'/' : '';

            return $item;
        }, $this->listOfArrays($content['laboratories']['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeProjectContent(array $content): array
    {
        $content['hero']['breadcrumbs'] = $this->listOfArrays($content['hero']['breadcrumbs'] ?? []);
        foreach (['statuses', 'faculties', 'themes'] as $optionGroup) {
            $content['filters'][$optionGroup] = $this->listOfArrays($content['filters'][$optionGroup] ?? []);
        }
        $content['items'] = array_map(function (array $item): array {
            $item['id'] = strtolower(trim((string) ($item['id'] ?? '')));
            $item['slug'] = strtolower(trim((string) ($item['slug'] ?? '')));
            $item['facultySlug'] = $this->canonicalFacultySlug(strtolower(trim((string) ($item['facultySlug'] ?? ''))));
            $item['themeSlug'] = strtolower(trim((string) ($item['themeSlug'] ?? '')));
            $item['status'] = strtolower(trim((string) ($item['status'] ?? '')));
            $item['startYear'] = trim((string) ($item['startYear'] ?? ''));
            $item['endYear'] = trim((string) ($item['endYear'] ?? ''));

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeThemeContent(array $content): array
    {
        $content['hero']['breadcrumbs'] = $this->listOfArrays($content['hero']['breadcrumbs'] ?? []);
        $content['items'] = array_map(static function (array $item): array {
            $item['id'] = strtolower(trim((string) ($item['id'] ?? '')));
            $item['slug'] = strtolower(trim((string) ($item['slug'] ?? '')));
            $item['publicationCount'] = is_numeric($item['publicationCount'] ?? null) ? max(0, (int) $item['publicationCount']) : 0;
            $item['projectCount'] = is_numeric($item['projectCount'] ?? null) ? max(0, (int) $item['projectCount']) : 0;

            return $item;
        }, $this->listOfArrays($content['items'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeConferenceContent(array $content): array
    {
        $content['upcoming'] = array_map(function (array $event): array {
            $formId = trim((string) ($event['formId'] ?? ''));
            $eventId = trim((string) ($event['id'] ?? ''));

            $link = FormLink::normalize($event['formSource'] ?? null, $event['registrationUrl'] ?? null);
            $event['formSource'] = $link['source'];

            if ($link['source'] === FormLink::EXTERNAL) {
                $event['formId'] = '';
                $event['registrationUrl'] = $link['url'];
            } elseif ($formId !== '' && app(FormDefinitionServiceInterface::class)->find($formId) !== null && $eventId !== '') {
                $event['registrationUrl'] = '/research/conferences/register?event='.rawurlencode($eventId);
            } else {
                $event['registrationUrl'] = null;
            }

            return $event;
        }, $this->listOfArrays($content['upcoming'] ?? []));

        $content['past'] = array_map(function (array $event): array {
            $event['hasProceedings'] = (bool) ($event['hasProceedings'] ?? false);
            $proceedingsUrl = $event['proceedingsUrl'] ?? null;
            if (! $this->isSafeResourceUrl($proceedingsUrl)) {
                $event['proceedingsUrl'] = null;
                $event['proceedingsUnavailable'] = $event['hasProceedings'];
            }

            return $event;
        }, $this->listOfArrays($content['past'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeLibraryContent(array $content): array
    {
        $content['databases'] = $this->listOfArrays($content['databases'] ?? []);
        $content['borrowingSection']['rules'] = $this->listOfArrays($content['borrowingSection']['rules'] ?? []);
        $content['specialCollections']['items'] = $this->listOfArrays($content['specialCollections']['items'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeOfficeContent(array $content): array
    {
        $content['council'] = is_array($content['council'] ?? null) ? $content['council'] : [];
        $content['council']['members'] = $this->listOfArrays($content['council']['members'] ?? []);
        $content['functions'] = is_array($content['functions'] ?? null) ? $content['functions'] : [];
        $content['functions']['items'] = $this->listOfArrays($content['functions']['items'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizePolicyContent(array $content): array
    {
        $content['sections'] = array_map(function (array $section): array {
            $section['documents'] = $this->listOfArrays($section['documents'] ?? []);
            $section['documentsUnavailable'] = $section['documents'] === [] || collect($section['documents'])
                ->every(fn (array $document): bool => ! $this->isSafeResourceUrl($document['url'] ?? null));

            return $section;
        }, $this->listOfArrays($content['sections'] ?? []));

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeLandingContent(array $content): array
    {
        $content['stats'] = array_map(static function (array $stat): array {
            if (in_array($stat['counter'] ?? null, ['publications', 'projects'], true)) {
                // Filled from the live publication or project count when the page renders.
                $stat['value'] = '';
            } else {
                unset($stat['counter']);
            }

            return $stat;
        }, $this->listOfArrays($content['stats'] ?? []));
        // The landing now shows the newest publication or project by itself.
        unset($content['featuredPublication']);
        $content['gateway']['cards'] = $this->listOfArrays($content['gateway']['cards'] ?? []);

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function normalizeExpertContent(array $content): array
    {
        $content['faculties'] = $this->listOfArrays($content['faculties'] ?? []);
        $content['expertiseAreas'] = $this->listOfArrays($content['expertiseAreas'] ?? []);
        $expertiseIds = collect($content['expertiseAreas'])
            ->filter(static fn (array $area): bool => is_string($area['id'] ?? null) && trim((string) $area['id']) !== '')
            ->mapWithKeys(static fn (array $area): array => [mb_strtolower(trim((string) ($area['name'] ?? ''))) => trim((string) $area['id'])]);
        $content['researchers'] = array_map(function (array $item) use ($expertiseIds): array {
            $item['role'] = $item['title'] ?? $item['role'] ?? '';
            $item['description'] = $item['bio'] ?? $item['description'] ?? '';
            $item['biography'] = $this->listOfStrings($item['biography'] ?? []);
            $item['expertise'] = $this->listOfStrings($item['expertise'] ?? []);
            $item['expertiseSlugs'] = $this->listOfStrings($item['expertiseSlugs'] ?? []);
            if ($item['expertiseSlugs'] === []) {
                $item['expertiseSlugs'] = array_values(array_filter(array_map(
                    static fn (string $name): ?string => $expertiseIds->get(mb_strtolower(trim($name))),
                    $item['expertise'],
                )));
            }
            $facultyId = strtolower(trim((string) ($item['facultyId'] ?? data_get($item, 'faculty.id', ''))));
            if ($facultyId !== '') {
                $item['facultyId'] = $facultyId;
                $item['facultySlug'] = $this->canonicalFacultySlug($facultyId);
            }
            $item['education'] = $this->listOfArrays($item['education'] ?? []);
            $item['courses'] = $this->listOfArrays($item['courses'] ?? []);
            $item['profilePublications'] = $this->listOfArrays($item['profilePublications'] ?? []);
            $item['publications'] = is_numeric($item['publications'] ?? null) ? (int) $item['publications'] : 0;
            $item['citations'] = is_numeric($item['citations'] ?? null) ? (int) $item['citations'] : 0;

            return $item;
        }, $this->listOfArrays($content['researchers'] ?? []));
        $content['items'] = $content['researchers'];

        return $content;
    }

    /** @param array<int, Section> $sections @return array<int, Section> */
    private function targetSections(array $sections, string $targetKey, string $locale): array
    {
        return array_map(
            static fn (Section $section): Section => $section
                ->extraAttributes(['dir' => $locale === 'ar' ? 'rtl' : 'ltr', 'lang' => $locale])
                ->visible(static fn (Get $get): bool => $get('target_key') === $targetKey),
            $sections,
        );
    }

    private function sectionLabel(string $key): string
    {
        return __('admin.research_workspace.sections.'.$key);
    }

    private function fieldLabel(string $key): string
    {
        return __('admin.research_workspace.editor_fields.'.$key);
    }

    /** @return array<string, string> */
    private function facultyOptions(bool $includeUniversity = false): array
    {
        $options = [];
        foreach (['medicine', 'dentistry', 'pharmacy', 'artificial-intelligence', 'building-construction-engineering', 'petroleum', 'business-administration'] as $slug) {
            $options[$slug] = __('admin.research_workspace.options.faculties.'.$slug);
        }

        if ($includeUniversity) {
            $options['university'] = __('admin.research_workspace.options.faculties.university');
        }

        $options['ai'] = $options['artificial-intelligence'];
        $options['construction'] = $options['building-construction-engineering'];
        $options['business'] = $options['business-administration'];

        return $options;
    }

    /** @return array<string, string> */
    private function themeOptions(): array
    {
        $options = [];
        foreach (['ai-ml', 'pharmaceutical-sciences', 'clinical-medicine', 'dental-sciences', 'petroleum-engineering', 'construction-engineering', 'business-administration', 'medical-education', 'biomedical-engineering', 'energy-systems', 'data-science', 'structural-engineering'] as $slug) {
            $options[$slug] = __('admin.research_workspace.options.themes.'.$slug);
        }

        return $options;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function synchronizeGeneratedIdentity(string $targetKey, array $payload): array
    {
        $paths = match ($targetKey) {
            'research.publications', 'research.projects', 'research.themes' => ['items'],
            'research.centers' => ['items', 'laboratories.items'],
            'research.experts' => ['researchers'],
            'research.conferences' => ['upcoming', 'past'],
            'research.policies' => ['sections'],
            default => [],
        };
        $slugPaths = match ($targetKey) {
            'research.publications', 'research.projects', 'research.themes', 'research.centers', 'research.experts' => [$paths[0] ?? ''],
            default => [],
        };

        foreach ($paths as $path) {
            $arabicItems = $this->listOfArrays(data_get($payload, 'translations.ar.'.$path, []));
            $englishItems = $this->listOfArrays(data_get($payload, 'translations.en.'.$path, []));
            $count = max(count($arabicItems), count($englishItems));

            for ($index = 0; $index < $count; $index++) {
                $arabic = $arabicItems[$index] ?? [];
                $english = $englishItems[$index] ?? [];
                $id = $this->firstFilled([$arabic['id'] ?? null, $english['id'] ?? null]);
                if ($id === '') {
                    $identitySource = $this->firstFilled([$english['slug'] ?? null, $english['title'] ?? null, $english['name'] ?? null, $arabic['slug'] ?? null]);
                    $generated = Str::slug($identitySource);
                    $id = str_replace('research.', '', $targetKey).'-'.($generated !== '' ? $generated : str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
                }

                foreach ([&$arabic, &$english] as &$item) {
                    if (trim((string) ($item['id'] ?? '')) === '') {
                        $item['id'] = $id;
                    }
                }
                unset($item);

                if (in_array($path, $slugPaths, true)) {
                    $slug = $this->firstFilled([$arabic['slug'] ?? null, $english['slug'] ?? null]);
                    if ($slug === '') {
                        $slug = Str::slug($this->firstFilled([$english['title'] ?? null, $english['name'] ?? null, $arabic['title'] ?? null, $arabic['name'] ?? null, $id]));
                    }
                    foreach ([&$arabic, &$english] as &$item) {
                        if (trim((string) ($item['slug'] ?? '')) === '') {
                            $item['slug'] = $slug;
                        }
                    }
                    unset($item);
                }

                $arabicItems[$index] = $arabic;
                $englishItems[$index] = $english;
            }

            data_set($payload, 'translations.ar.'.$path, $arabicItems);
            data_set($payload, 'translations.en.'.$path, $englishItems);
        }

        $filterPaths = match ($targetKey) {
            'research.publications' => ['filters.faculties', 'filters.publicationTypes', 'filters.years'],
            'research.projects' => ['filters.statuses', 'filters.faculties', 'filters.themes'],
            default => [],
        };
        foreach ($filterPaths as $path) {
            $arabicOptions = $this->listOfArrays(data_get($payload, 'translations.ar.'.$path, []));
            $englishOptions = $this->listOfArrays(data_get($payload, 'translations.en.'.$path, []));
            $count = max(count($arabicOptions), count($englishOptions));

            for ($index = 0; $index < $count; $index++) {
                $arabic = $arabicOptions[$index] ?? [];
                $english = $englishOptions[$index] ?? [];
                $value = $this->firstFilled([$arabic['value'] ?? null, $english['value'] ?? null]);
                if ($value === '' && $index > 0) {
                    $value = Str::slug($this->firstFilled([$english['label'] ?? null, $arabic['label'] ?? null]));
                }
                if ($value !== '') {
                    $arabic['value'] = $this->firstFilled([$arabic['value'] ?? null, $value]);
                    $english['value'] = $this->firstFilled([$english['value'] ?? null, $value]);
                }
                $arabicOptions[$index] = $arabic;
                $englishOptions[$index] = $english;
            }

            data_set($payload, 'translations.ar.'.$path, $arabicOptions);
            data_set($payload, 'translations.en.'.$path, $englishOptions);
        }

        if ($targetKey === 'research.experts') {
            data_set($payload, 'translations.ar.items', data_get($payload, 'translations.ar.researchers', []));
            data_set($payload, 'translations.en.items', data_get($payload, 'translations.en.researchers', []));
        }

        return $payload;
    }

    /** @param array<int, mixed> $values */
    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $edited @return array<string, mixed> */
    private function mergePreservingLegacy(array $source, array $edited): array
    {
        $merged = $source;

        foreach ($edited as $key => $value) {
            $existing = $source[$key] ?? null;
            if (is_array($value) && is_array($existing)) {
                if (array_is_list($value)) {
                    $merged[$key] = $this->mergeLegacyList($existing, $value);
                } else {
                    $merged[$key] = $this->mergePreservingLegacy($existing, $value);
                }
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /** @param array<int, mixed> $source @param array<int, mixed> $edited @return array<int, mixed> */
    private function mergeLegacyList(array $source, array $edited): array
    {
        return array_values(array_map(function (mixed $item, int $index) use ($source): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $identity = $this->firstFilled([$item['id'] ?? null, $item['slug'] ?? null]);
            $indexedSource = is_array($source[$index] ?? null) ? $source[$index] : [];
            $identitylessSource = $this->firstFilled([$indexedSource['id'] ?? null, $indexedSource['slug'] ?? null]) === '' ? $indexedSource : [];
            $existing = $identity === '' ? ($source[$index] ?? []) : collect($source)->first(
                fn (mixed $candidate): bool => is_array($candidate) && $identity === $this->firstFilled([$candidate['id'] ?? null, $candidate['slug'] ?? null]),
                $identitylessSource,
            );

            return is_array($existing) ? $this->mergePreservingLegacy($existing, $item) : $item;
        }, $edited, array_keys($edited)));
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @return array<int, string> */
    private function listOfStrings(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_string($item) && trim($item) !== ''));
    }

    private function isSafeResourceUrl(mixed $url): bool
    {
        return is_string($url)
            && trim($url) !== ''
            && $url !== '#'
            && (
                (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                || (filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https')
            );
    }

    private function canonicalFacultySlug(string $slug): string
    {
        return match ($slug) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => $slug,
        };
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->map(
            static fn (mixed $message): string => preg_replace('/\b([a-z]+)([A-Z][A-Za-z]*)\b/', '$1 $2', (string) $message) ?? (string) $message,
        )->implode(PHP_EOL);
    }
}
