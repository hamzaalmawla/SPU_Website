<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanLinkServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Exceptions\ConflictException;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ManagesFacultyHomepage
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    public ?string $activeTargetKey = null;

    private FacultyPageServiceInterface $facultyPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    private FacultyStudyPlanLinkServiceInterface $studyPlanLinkService;

    private FacultyStudyPlanEditorServiceInterface $studyPlanEditorService;

    /** @return array<string, string> */
    abstract protected function targetOptions(): array;

    abstract protected function defaultTargetKey(): string;

    abstract protected static function managedFacultyScope(): string;

    public function boot(
        FacultyPageServiceInterface $facultyPageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
        FacultyStudyPlanLinkServiceInterface $studyPlanLinkService,
        FacultyStudyPlanEditorServiceInterface $studyPlanEditorService,
    ): void {
        $this->facultyPageService = $facultyPageService;
        $this->cmsWorkflowService = $cmsWorkflowService;
        $this->studyPlanLinkService = $studyPlanLinkService;
        $this->studyPlanEditorService = $studyPlanEditorService;
    }

    public static function canAccess(): bool
    {
        if (! Gate::allows('manage-faculties')) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User || $user->role_slug !== 'faculty_editor') {
            return true;
        }

        return self::canonicalManagedFacultyScope((string) $user->faculty_scope_slug)
            === self::canonicalManagedFacultyScope(static::managedFacultyScope());
    }

    private static function canonicalManagedFacultyScope(string $scope): string
    {
        return match ($scope) {
            'ai', 'ai-engineering' => 'artificial-intelligence',
            'construction' => 'building-construction-engineering',
            'business' => 'business-administration',
            default => $scope,
        };
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
    }

    public function mount(): void
    {
        $requestedTarget = request()->query('target', $this->defaultTargetKey());
        $targetKey = is_string($requestedTarget) && array_key_exists($requestedTarget, $this->targetOptions())
            ? $requestedTarget
            : $this->defaultTargetKey();

        $requestedDepartment = request()->query('department');
        $requestedTerm = request()->query('term');
        $this->data['study_plan_department_id'] = is_string($requestedDepartment) ? $requestedDepartment : '';
        $this->data['study_plan_term_id'] = is_string($requestedTerm) ? $requestedTerm : '';

        $this->loadTarget($targetKey);
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertManagedTarget($targetKey);
        $this->activeTargetKey = $targetKey;
        $userId = $this->authenticatedUserId();
        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $userId);
        $payload = is_array($draftPayload) ? $draftPayload : $this->facultyPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId);
        $studyPlanDepartmentOptions = $this->studyPlanDepartmentOptionsFromPayload($targetKey, $payload);
        $studyPlanDepartmentId = $this->studyPlanDepartmentIdFromPayload($targetKey, $payload, (string) ($this->data['study_plan_department_id'] ?? ''));
        $studyPlanTermOptions = $this->studyPlanTermOptionsFromPayload($targetKey, $payload, $studyPlanDepartmentId);
        $studyPlanTermId = $this->studyPlanTermIdFromPayload($targetKey, $payload, $studyPlanDepartmentId, (string) ($this->data['study_plan_term_id'] ?? ''));
        $isStudyPlan = $this->subpageSlugFromTarget($targetKey) === 'study-plan';
        $studyPlanCourseOptions = $isStudyPlan && is_string($studyPlanDepartmentId)
            ? $this->studyPlanEditorService->prerequisiteOptions($payload, $studyPlanDepartmentId)
            : [];
        $studyPlanLessonTypeOptions = $isStudyPlan && is_string($studyPlanDepartmentId)
            ? $this->studyPlanEditorService->lessonTypeOptions($payload, $studyPlanDepartmentId)
            : [];
        $studyPlanWorkspace = $isStudyPlan && is_string($studyPlanDepartmentId) && is_string($studyPlanTermId)
            ? $this->studyPlanEditorService->buildWorkspace($payload, $studyPlanDepartmentId, $studyPlanTermId)
            : [];

        $this->form->fill([
            'target_key' => $targetKey,
            'study_plan_department_id' => $studyPlanDepartmentId,
            'study_plan_term_id' => $studyPlanTermId,
            'record_search' => (string) ($this->data['record_search'] ?? ''),
            'record_department_filter' => (string) ($this->data['record_department_filter'] ?? ''),
            'record_year_filter' => (string) ($this->data['record_year_filter'] ?? ''),
            'study_plan_workspace' => $studyPlanWorkspace,
            'ar_content' => $this->contentForForm($targetKey, is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [], $studyPlanDepartmentId, $studyPlanTermId),
            'en_content' => $this->contentForForm($targetKey, is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [], $studyPlanDepartmentId, $studyPlanTermId),
        ]);

        if (is_array($this->data)) {
            $this->data['study_plan_department_options'] = $studyPlanDepartmentOptions;
            $this->data['study_plan_term_options'] = $studyPlanTermOptions;
            $this->data['study_plan_course_options'] = $studyPlanCourseOptions;
            $this->data['study_plan_lesson_type_options'] = $studyPlanLessonTypeOptions;
            $this->data['department_study_plan_options'] = $this->departmentStudyPlanOptionsFromTarget($targetKey);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('target_key')->required(),
                Hidden::make('study_plan_department_id'),
                Hidden::make('study_plan_term_id'),
                Section::make(__('admin.faculty_workspace.editing_tools'))->schema([
                    TextInput::make('record_search')
                        ->label(__('admin.faculty_workspace.fields.search'))
                        ->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema()))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (): void {
                            $this->loadTarget($this->currentTargetKeyForSchema());
                        }),
                    TextInput::make('record_department_filter')
                        ->label(__('admin.faculty_workspace.fields.department_filter'))
                        ->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema()))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (): void {
                            $this->loadTarget($this->currentTargetKeyForSchema());
                        }),
                    TextInput::make('record_year_filter')
                        ->label(__('admin.faculty_workspace.fields.year_filter'))
                        ->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema()))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (): void {
                            $this->loadTarget($this->currentTargetKeyForSchema());
                        }),
                ])->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema())),
                Tabs::make('faculty_homepage_locales')
                    ->tabs([
                        Tab::make(__('admin.locales.ar'))->extraAttributes(['dir' => 'rtl'])->schema($this->payloadFields('ar')),
                        Tab::make(__('admin.locales.en'))->extraAttributes(['dir' => 'ltr'])->schema($this->payloadFields('en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
                $this->studyPlanWorkspaceFields()
                    ->visible(fn (): bool => $this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) === 'study-plan'),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('admin.faculty_workspace.actions.save_draft'))->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label(__('admin.faculty_workspace.actions.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label(__('admin.faculty_workspace.actions.preview_en'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')->label(__('admin.faculty_workspace.actions.publish'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')
                ->label(__('admin.faculty_workspace.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin.faculty_workspace.fields.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (array $data): void {
                    $this->schedule((string) $data['publish_at']);
                }),
            Action::make('unpublish')->label(__('admin.faculty_workspace.actions.unpublish'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))->action(function (): void {
                    $this->unpublish();
                }),
        ];
    }

    /** @return list<array{key: string, label: string, description: string, url: string, active: bool}> */
    public function getFacultyWorkspaceTasks(): array
    {
        $currentTarget = $this->currentTargetKeyForSchema();

        return collect(array_keys($this->targetOptions()))
            ->map(function (string $key) use ($currentTarget): array {
                $task = $key === $this->defaultTargetKey()
                    ? 'homepage'
                    : str_replace('-', '_', (string) Str::afterLast($key, '.'));

                return [
                    'key' => $key,
                    'label' => __('admin.faculty_workspace.targets.'.$task),
                    'description' => __('admin.faculty_workspace.descriptions.'.$task),
                    'url' => static::getUrl(['target' => $key]),
                    'active' => $key === $currentTarget,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{id: string, label: string, url: string, active: bool}> */
    public function getStudyPlanDepartmentNavigation(): array
    {
        if ($this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) !== 'study-plan') {
            return [];
        }

        $currentDepartment = (string) ($this->data['study_plan_department_id'] ?? '');

        return collect($this->studyPlanDepartmentOptions())
            ->map(fn (string $label, string $id): array => [
                'id' => $id,
                'label' => $label,
                'url' => static::getUrl([
                    'target' => $this->currentTargetKeyForSchema(),
                    'department' => $id,
                ]),
                'active' => $id === $currentDepartment,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id: string, label: string, url: string, active: bool}> */
    public function getStudyPlanTermNavigation(): array
    {
        if ($this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) !== 'study-plan') {
            return [];
        }

        $currentDepartment = (string) ($this->data['study_plan_department_id'] ?? '');
        $currentTerm = (string) ($this->data['study_plan_term_id'] ?? '');

        return collect($this->studyPlanTermOptions())
            ->map(fn (string $label, string $id): array => [
                'id' => $id,
                'label' => $label,
                'url' => static::getUrl([
                    'target' => $this->currentTargetKeyForSchema(),
                    'department' => $currentDepartment,
                    'term' => $id,
                ]),
                'active' => $id === $currentTerm,
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title(__('admin.faculty_workspace.notifications.draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.faculty_workspace.notifications.conflict'))->body(__('admin.faculty_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.faculty_workspace.notifications.save_failed'))->body(__('admin.faculty_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            Notification::make()->title(__('admin.faculty_workspace.notifications.preview_failed'))->body(__('admin.faculty_workspace.notifications.invalid_preview_locale'))->danger()->send();

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
            Notification::make()->title(__('admin.faculty_workspace.notifications.conflict'))->body(__('admin.faculty_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.faculty_workspace.notifications.preview_failed'))->body(__('admin.faculty_workspace.notifications.safe_error'))->danger()->send();
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

            Notification::make()->title(__('admin.faculty_workspace.notifications.published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.faculty_workspace.notifications.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.faculty_workspace.notifications.publish_failed'))->body(__('admin.faculty_workspace.notifications.safe_error'))->danger()->send();
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

            Notification::make()->title(__('admin.faculty_workspace.notifications.scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.faculty_workspace.notifications.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.faculty_workspace.notifications.schedule_failed'))->body(__('admin.faculty_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        try {
            $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
            $notification = Notification::make()->title($result
                ? __('admin.faculty_workspace.notifications.unpublished')
                : __('admin.faculty_workspace.notifications.nothing_published'));

            ($result ? $notification->success() : $notification->warning())->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.faculty_workspace.notifications.unpublish_failed'))->body(__('admin.faculty_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->currentTargetKeyForSchema() !== $this->defaultTargetKey()) {
            return $this->subpageFields($locale);
        }

        $prefix = $locale.'_content';

        return [
            Section::make(__('admin.faculty_workspace.editor.sections.page_content'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin.faculty_workspace.editor.fields.page_title'))->maxLength(180),
                TextInput::make($prefix.'.summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->maxLength(255),
                Textarea::make($prefix.'.body')->label(__('admin.faculty_workspace.editor.fields.body'))->rows(5)->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin.faculty_workspace.editor.sections.faculty_identity'))->schema([
                TextInput::make($prefix.'.faculty.name')->label(__('admin.faculty_workspace.editor.fields.name'))->maxLength(180),
                TextInput::make($prefix.'.faculty.title')->label(__('admin.faculty_workspace.editor.fields.display_title'))->maxLength(180),
                TextInput::make($prefix.'.faculty.yearsLabel')->label(__('admin.faculty_workspace.editor.fields.years_label'))->maxLength(80),
                TextInput::make($prefix.'.faculty.accentColor')->label(__('admin.faculty_workspace.editor.fields.accent_color'))->maxLength(20),
                MediaPicker::image($prefix.'.faculty.heroImage', __('admin.faculty_workspace.editor.fields.hero_image')),
                MediaPicker::image($prefix.'.faculty.logoImage', __('admin.faculty_workspace.editor.fields.logo_image')),
                Textarea::make($prefix.'.faculty.summary')->label(__('admin.faculty_workspace.editor.fields.short_summary'))->rows(2)->columnSpanFull(),
                Textarea::make($prefix.'.faculty.description')->label(__('admin.faculty_workspace.editor.fields.description'))->rows(4)->columnSpanFull(),
            ])->columns(2)->collapsed(),

            Section::make(__('admin.faculty_workspace.editor.sections.overview_tabs'))->schema([
                Repeater::make($prefix.'.tabs')
                    ->label(__('admin.faculty_workspace.editor.fields.tabs'))
                    ->schema([
                        Hidden::make('id')->default(fn (): string => 'tab-'.Str::lower(Str::random(8))),
                        TextInput::make('label')->label(__('admin.faculty_workspace.editor.fields.label'))->maxLength(120),
                        Textarea::make('body')->label(__('admin.faculty_workspace.editor.fields.body'))->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->collapsed(),

            Section::make(__('admin.faculty_workspace.editor.sections.dean_message'))->schema([
                TextInput::make($prefix.'.dean.name')->label(__('admin.faculty_workspace.editor.fields.dean_name'))->maxLength(160),
                TextInput::make($prefix.'.dean.role')->label(__('admin.faculty_workspace.editor.fields.dean_role'))->maxLength(160),
                MediaPicker::image($prefix.'.dean.image', __('admin.faculty_workspace.editor.fields.dean_image')),
                Textarea::make($prefix.'.dean.message')->label(__('admin.faculty_workspace.editor.fields.message'))->rows(4)->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin.faculty_workspace.editor.sections.gallery'))->schema([
                Repeater::make($prefix.'.gallery')
                    ->label(__('admin.faculty_workspace.editor.fields.gallery_images'))
                    ->schema([
                        MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image')),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin.faculty_workspace.editor.sections.stats'))->schema([
                Repeater::make($prefix.'.stats')
                    ->label(__('admin.faculty_workspace.editor.sections.stats'))
                    ->schema([
                        TextInput::make('value')->label(__('admin.faculty_workspace.editor.fields.value'))->maxLength(40),
                        TextInput::make('label')->label(__('admin.faculty_workspace.editor.fields.label'))->maxLength(120),
                        MediaPicker::icon('icon', __('admin.faculty_workspace.editor.fields.icon')),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin.faculty_workspace.editor.sections.latest_research'))->schema([
                Repeater::make($prefix.'.latestResearch')
                    ->label(__('admin.faculty_workspace.editor.sections.latest_research'))
                    ->schema([
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.title'))->maxLength(180),
                        TextInput::make('type')->label(__('admin.faculty_workspace.editor.fields.type'))->maxLength(120),
                        TextInput::make('date')->label(__('admin.faculty_workspace.editor.fields.date'))->maxLength(80),
                        TextInput::make('doi')->label(__('admin.faculty_workspace.editor.fields.doi'))->maxLength(120),
                        MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image')),
                        TextInput::make('url')->label(__('admin.faculty_workspace.editor.fields.url'))->maxLength(255),
                        TextInput::make('cta')->label(__('admin.faculty_workspace.editor.fields.action_label'))->maxLength(120),
                        Textarea::make('summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function payloadFromForm(array $state): array
    {
        $targetKey = (string) ($state['target_key'] ?? $this->defaultTargetKey());
        $this->assertManagedTarget($targetKey);

        $payload = [
            'translations' => [
                'ar' => $targetKey === $this->defaultTargetKey()
                    ? $this->normalizeContent(is_array($state['ar_content'] ?? null) ? $state['ar_content'] : [])
                    : $this->normalizeSubpageContent($targetKey, 'ar', is_array($state['ar_content'] ?? null) ? $state['ar_content'] : []),
                'en' => $targetKey === $this->defaultTargetKey()
                    ? $this->normalizeContent(is_array($state['en_content'] ?? null) ? $state['en_content'] : [])
                    : $this->normalizeSubpageContent($targetKey, 'en', is_array($state['en_content'] ?? null) ? $state['en_content'] : []),
            ],
        ];

        if ($this->subpageSlugFromTarget($targetKey) !== 'study-plan') {
            return $payload;
        }

        $workspace = $this->studyPlanEditorService->prepareWorkspace(
            is_array($state['study_plan_workspace'] ?? null) ? $state['study_plan_workspace'] : [],
        );
        $this->data['study_plan_workspace'] = $workspace;

        return $this->studyPlanEditorService->mergeWorkspace(
            $payload,
            $workspace,
            (string) ($state['study_plan_department_id'] ?? ''),
            (string) ($state['study_plan_term_id'] ?? ''),
        );
    }

    /** @return array<int, Section> */
    private function subpageFields(string $locale): array
    {
        $prefix = $locale.'_content';
        $targetKey = $this->currentTargetKeyForSchema();
        $subpageSlug = $this->subpageSlugFromTarget($targetKey);

        $sections = $this->baseSubpageFields($prefix);

        return match ($subpageSlug) {
            'overview' => [...$sections, ...$this->overviewSubpageFields($prefix)],
            'departments' => [...$sections, ...$this->departmentsSubpageFields($prefix)],
            'study-plan' => [...$sections, ...$this->studyPlanSubpageFields($prefix)],
            'labs' => [...$sections, ...$this->labsSubpageFields($prefix)],
            'projects' => [...$sections, ...$this->projectsSubpageFields($prefix)],
            'alumni' => [...$sections, ...$this->alumniSubpageFields($prefix)],
            'valedictorians' => [...$sections, ...$this->valedictoriansSubpageFields($prefix)],
            'training' => [...$sections, ...$this->trainingSubpageFields($prefix)],
            'research' => [...$sections, ...$this->researchSubpageFields($prefix)],
            default => throw new \InvalidArgumentException('Unsupported faculty subpage target.'),
        };
    }

    /** @return array<int, Section> */
    private function baseSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.page_intro'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.heroImage', __('admin.faculty_workspace.editor.fields.hero_image')),
                Textarea::make($prefix.'.summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->rows(2)->columnSpanFull(),
                Textarea::make($prefix.'.body')->label(__('admin.faculty_workspace.editor.fields.body'))->rows(4)->columnSpanFull(),
            ])->columns(2)->collapsed(),
        ];
    }

    /** @return array<int, Section> */
    private function trainingSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.training_intro'))->schema([
                TextInput::make($prefix.'.payload.hero.eyebrow')->label(__('admin.faculty_workspace.editor.fields.eyebrow'))->required()->maxLength(120),
                TextInput::make($prefix.'.payload.hero.title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                Textarea::make($prefix.'.payload.hero.summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.payload.hero.image', __('admin.faculty_workspace.editor.fields.hero_image'), true),
            ])->columns(2),
            Section::make(__('admin.faculty_workspace.editor.sections.training_cards'))->schema([
                Repeater::make($prefix.'.payload.introCards')->schema([
                    TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                    MediaPicker::icon('icon', __('admin.faculty_workspace.editor.fields.icon'), true),
                    Textarea::make('description')->label(__('admin.faculty_workspace.editor.fields.description'))->required()->rows(3)->columnSpanFull(),
                ])->columns(2)->reorderable()->minItems(1)->columnSpanFull(),
            ]),
            Section::make(__('admin.faculty_workspace.editor.sections.training_program'))->schema([
                TextInput::make($prefix.'.payload.programme.title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                Repeater::make($prefix.'.payload.programme.steps')->schema([
                    TextInput::make('number')->label(__('admin.faculty_workspace.editor.fields.step_number'))->required()->maxLength(20),
                    TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                    Textarea::make('description')->label(__('admin.faculty_workspace.editor.fields.description'))->required()->rows(3)->columnSpanFull(),
                ])->columns(2)->reorderable()->minItems(1)->columnSpanFull(),
            ]),
            Section::make(__('admin.faculty_workspace.editor.sections.training_destinations'))->description(__('admin.faculty_workspace.editor.help.verified_routes'))->schema([
                TextInput::make($prefix.'.payload.partners.title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                TextInput::make($prefix.'.payload.partners.cta')->label(__('admin.faculty_workspace.editor.fields.action_label'))->required()->maxLength(120),
                Repeater::make($prefix.'.payload.partners.items')->schema([
                    TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.title'))->required()->maxLength(180),
                    TextInput::make('category')->label(__('admin.faculty_workspace.editor.fields.category'))->required()->maxLength(120),
                    TextInput::make('href')->label(__('admin.faculty_workspace.editor.fields.url'))->required()->maxLength(255),
                    MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image'), true),
                    Textarea::make('description')->label(__('admin.faculty_workspace.editor.fields.description'))->required()->rows(3)->columnSpanFull(),
                ])->columns(2)->reorderable()->columnSpanFull(),
            ])->columns(2),
            Section::make(__('admin.faculty_workspace.editor.sections.verified_facts'))->schema([
                Repeater::make($prefix.'.payload.facts')->schema([
                    TextInput::make('value')->label(__('admin.faculty_workspace.editor.fields.value'))->required()->maxLength(80),
                    TextInput::make('label')->label(__('admin.faculty_workspace.editor.fields.label'))->required()->maxLength(160),
                    Toggle::make('verified')->label(__('admin.faculty_workspace.editor.fields.verified'))->required(),
                ])->columns(2)->reorderable()->columnSpanFull(),
            ]),
            Section::make(__('admin.faculty_workspace.editor.sections.seo'))->schema([
                TextInput::make($prefix.'.seoTitle')->label(__('admin.faculty_workspace.editor.fields.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin.faculty_workspace.editor.fields.seo_description'))->required()->rows(2),
                MediaPicker::image($prefix.'.seoImage', __('admin.faculty_workspace.editor.fields.seo_image'), true),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function overviewSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.overview_sections'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label(__('admin.faculty_workspace.editor.sections.overview_sections'))
                    ->schema([
                        Hidden::make('id')->default(fn (): string => 'section-'.Str::lower(Str::random(8))),
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.title'))->maxLength(160),
                        Textarea::make('body')->label(__('admin.faculty_workspace.editor.fields.body'))->rows(4)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin.faculty_workspace.editor.sections.stats'))->schema([
                Repeater::make($prefix.'.stats')
                    ->label(__('admin.faculty_workspace.editor.sections.stats'))
                    ->schema([
                        TextInput::make('value')->label(__('admin.faculty_workspace.editor.fields.value'))->maxLength(40),
                        TextInput::make('label')->label(__('admin.faculty_workspace.editor.fields.label'))->maxLength(120),
                        MediaPicker::icon('icon', __('admin.faculty_workspace.editor.fields.icon')),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin.faculty_workspace.editor.sections.dean_message'))->schema([
                TextInput::make($prefix.'.dean.nameAr')->label(__('admin.faculty_workspace.editor.fields.dean_name_ar'))->maxLength(160),
                TextInput::make($prefix.'.dean.nameEn')->label(__('admin.faculty_workspace.editor.fields.dean_name_en'))->maxLength(160),
                TextInput::make($prefix.'.dean.roleAr')->label(__('admin.faculty_workspace.editor.fields.dean_role_ar'))->maxLength(160),
                TextInput::make($prefix.'.dean.roleEn')->label(__('admin.faculty_workspace.editor.fields.dean_role_en'))->maxLength(160),
                MediaPicker::image($prefix.'.dean.image', __('admin.faculty_workspace.editor.fields.dean_image')),
                Textarea::make($prefix.'.dean.messageAr')->label(__('admin.faculty_workspace.editor.fields.message_ar'))->rows(4),
                Textarea::make($prefix.'.dean.messageEn')->label(__('admin.faculty_workspace.editor.fields.message_en'))->rows(4),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function studyPlanSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.study_plan.interface_labels'))->schema([
                TextInput::make($prefix.'.payload.labels.title')->label(__('admin.faculty_workspace.study_plan.labels.title'))->maxLength(160),
                TextInput::make($prefix.'.payload.labels.home')->label(__('admin.faculty_workspace.study_plan.labels.home'))->maxLength(120),
                TextInput::make($prefix.'.payload.labels.faculties')->label(__('admin.faculty_workspace.study_plan.labels.faculties'))->maxLength(120),
                TextInput::make($prefix.'.payload.labels.empty')->label(__('admin.faculty_workspace.study_plan.labels.empty'))->maxLength(160),
                TextInput::make($prefix.'.payload.labels.electiveRequirements')->label(__('admin.faculty_workspace.study_plan.labels.elective_requirements'))->maxLength(160),
                TextInput::make($prefix.'.payload.labels.promotionRequirements')->label(__('admin.faculty_workspace.study_plan.labels.promotion_requirements'))->maxLength(160),
                TextInput::make($prefix.'.payload.labels.viewDetails')->label(__('admin.faculty_workspace.study_plan.labels.course_details'))->maxLength(120),
                TextInput::make($prefix.'.payload.labels.close')->label(__('admin.faculty_workspace.study_plan.labels.close'))->maxLength(120),
            ])->columns(2)->collapsed(),

            Section::make(__('admin.faculty_workspace.study_plan.course_page_labels'))->schema([
                TextInput::make($prefix.'.payload.courseLabels.studyPlan')->label(__('admin.faculty_workspace.study_plan.labels.study_plan'))->maxLength(160),
                TextInput::make($prefix.'.payload.courseLabels.coursePage')->label(__('admin.faculty_workspace.study_plan.labels.course_page'))->maxLength(160),
                TextInput::make($prefix.'.payload.courseLabels.credits')->label(__('admin.faculty_workspace.study_plan.labels.credits'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.courseType')->label(__('admin.faculty_workspace.study_plan.labels.course_type'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.requiredStatus')->label(__('admin.faculty_workspace.study_plan.labels.required_status'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.required')->label(__('admin.faculty_workspace.study_plan.labels.required'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.elective')->label(__('admin.faculty_workspace.study_plan.labels.elective'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.prerequisites')->label(__('admin.faculty_workspace.study_plan.labels.prerequisites'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.opensAfter')->label(__('admin.faculty_workspace.study_plan.labels.opens_after'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.lessons')->label(__('admin.faculty_workspace.study_plan.labels.lessons'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.all')->label(__('admin.faculty_workspace.study_plan.labels.all_lessons'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.viewPdf')->label(__('admin.faculty_workspace.study_plan.labels.view_pdf'))->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.download')->label(__('admin.faculty_workspace.study_plan.labels.download'))->maxLength(120),
            ])->columns(2)->collapsed(),

            Section::make(__('admin.faculty_workspace.study_plan.plan_settings'))->schema([
                TextInput::make($prefix.'.payload.plan.faculty')->label(__('admin.faculty_workspace.study_plan.plan_faculty'))->maxLength(180),
                MediaPicker::image($prefix.'.payload.plan.heroImage', __('admin.faculty_workspace.study_plan.plan_image')),
                TextInput::make($prefix.'.payload.plan.accent')->label(__('admin.faculty_workspace.study_plan.plan_color'))->maxLength(20),
            ])->columns(2)->collapsed(),

            Section::make(__('admin.faculty_workspace.study_plan.legend_settings'))->schema([
                Repeater::make($prefix.'.payload.legend')
                    ->label(__('admin.faculty_workspace.study_plan.course_type_legend'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        TextInput::make('label')->label(__('admin.faculty_workspace.editor.fields.label'))->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.payload.lessonTypes')
                    ->label(__('admin.faculty_workspace.study_plan.lesson_types_label'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        TextInput::make('label')->label(__('admin.faculty_workspace.editor.fields.label'))->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->collapsed(),
        ];
    }

    private function studyPlanWorkspaceFields(): Section
    {
        return Section::make(__('admin.faculty_workspace.study_plan.paired_workspace'))
            ->description(__('admin.faculty_workspace.study_plan.paired_workspace_help'))
            ->schema([
                Repeater::make('study_plan_workspace.courses')
                    ->label(__('admin.faculty_workspace.study_plan.courses_in_term'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        Hidden::make('_originalId')->dehydrated(),
                        Hidden::make('_originalIndexes')->dehydrated(),
                        TextInput::make('code')->label(__('admin.faculty_workspace.study_plan.course_code'))->required()->maxLength(80),
                        TextInput::make('credits')->label(__('admin.faculty_workspace.study_plan.credits'))->numeric(),
                        Select::make('type')->label(__('admin.faculty_workspace.study_plan.requirement_type'))->options([
                            'university' => __('admin.faculty_workspace.study_plan.types.university'),
                            'faculty' => __('admin.faculty_workspace.study_plan.types.faculty'),
                            'specialization' => __('admin.faculty_workspace.study_plan.types.specialization'),
                        ]),
                        Toggle::make('required')->label(__('admin.faculty_workspace.study_plan.required')),
                        Select::make('prerequisites')
                            ->label(__('admin.faculty_workspace.study_plan.prerequisites'))
                            ->helperText(__('admin.faculty_workspace.study_plan.prerequisites_help'))
                            ->options(fn (): array => $this->studyPlanCourseOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->disableOptionWhen(fn (string $value, Get $get): bool => $value === (string) $get('id'))
                            ->columnSpanFull(),
                        Section::make(__('admin.faculty_workspace.study_plan.arabic_content'))->schema([
                            TextInput::make('titleAr')->label(__('admin.faculty_workspace.study_plan.course_title_ar'))->required()->maxLength(180),
                            TextInput::make('instructor.nameAr')->label(__('admin.faculty_workspace.study_plan.instructor_ar'))->maxLength(160),
                            Textarea::make('descriptionAr')->label(__('admin.faculty_workspace.study_plan.description_ar'))->rows(2)->columnSpanFull(),
                        ])->columns(2),
                        Section::make(__('admin.faculty_workspace.study_plan.english_content'))->schema([
                            TextInput::make('titleEn')->label(__('admin.faculty_workspace.study_plan.course_title_en'))->required()->maxLength(180),
                            TextInput::make('instructor.nameEn')->label(__('admin.faculty_workspace.study_plan.instructor_en'))->maxLength(160),
                            Textarea::make('descriptionEn')->label(__('admin.faculty_workspace.study_plan.description_en'))->rows(2)->columnSpanFull(),
                        ])->columns(2),
                        Hidden::make('instructor.staffSlug')->dehydrated(),
                        Repeater::make('lessons')
                            ->label(__('admin.faculty_workspace.study_plan.lessons'))
                            ->schema([
                                Hidden::make('id')->dehydrated(),
                                Hidden::make('_originalId')->dehydrated(),
                                Hidden::make('_originalIndexes')->dehydrated(),
                                TextInput::make('order')->label(__('admin.faculty_workspace.study_plan.lesson_order'))->numeric(),
                                Select::make('type')->label(__('admin.faculty_workspace.study_plan.lesson_type'))->options(fn (): array => $this->studyPlanLessonTypeOptions()),
                                MediaPicker::document('pdfUrl', __('admin.faculty_workspace.study_plan.lesson_file')),
                                TextInput::make('titleAr')->label(__('admin.faculty_workspace.study_plan.lesson_title_ar'))->required()->maxLength(180),
                                TextInput::make('titleEn')->label(__('admin.faculty_workspace.study_plan.lesson_title_en'))->required()->maxLength(180),
                                Textarea::make('descriptionAr')->label(__('admin.faculty_workspace.study_plan.description_ar'))->rows(2),
                                Textarea::make('descriptionEn')->label(__('admin.faculty_workspace.study_plan.description_en'))->rows(2),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->collapsed()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make('study_plan_workspace.electivePools')
                    ->label(__('admin.faculty_workspace.study_plan.elective_pools'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        Hidden::make('_originalId')->dehydrated(),
                        Hidden::make('_originalIndexes')->dehydrated(),
                        TextInput::make('requiredHours')->label(__('admin.faculty_workspace.study_plan.required_hours'))->numeric(),
                        Textarea::make('descriptionAr')->label(__('admin.faculty_workspace.study_plan.description_ar'))->rows(2),
                        Textarea::make('descriptionEn')->label(__('admin.faculty_workspace.study_plan.description_en'))->rows(2),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make('study_plan_workspace.promotionRequirements')
                    ->label(__('admin.faculty_workspace.study_plan.promotion_requirements'))
                    ->schema([
                        Hidden::make('id')->dehydrated(),
                        Hidden::make('_originalId')->dehydrated(),
                        Hidden::make('_originalIndexes')->dehydrated(),
                        TextInput::make('fromYear')->label(__('admin.faculty_workspace.study_plan.from_year'))->required()->maxLength(20),
                        TextInput::make('toYear')->label(__('admin.faculty_workspace.study_plan.to_year'))->required()->maxLength(20),
                        TextInput::make('requiredCredits')->label(__('admin.faculty_workspace.study_plan.required_credits'))->numeric(),
                        Textarea::make('descriptionAr')->label(__('admin.faculty_workspace.study_plan.description_ar'))->rows(2),
                        Textarea::make('descriptionEn')->label(__('admin.faculty_workspace.study_plan.description_en'))->rows(2),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<int, Section> */
    private function departmentsSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.departments'))->schema([
                Repeater::make($prefix.'.items')
                    ->label(__('admin.faculty_workspace.editor.sections.departments'))
                    ->schema([
                        Hidden::make('slug')->default(fn (): string => 'department-'.Str::lower(Str::random(8))),
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.department_name'))->required()->maxLength(180),
                        TextInput::make('degrees')->label(__('admin.faculty_workspace.editor.fields.degree_track'))->maxLength(160),
                        TagsInput::make('tags')->label(__('admin.faculty_workspace.editor.fields.tags'))->columnSpanFull(),
                        Textarea::make('summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->rows(3)->columnSpanFull(),
                        Section::make(__('admin.faculty_workspace.editor.sections.advanced'))->collapsed()->schema([
                            TextInput::make('code')->label(__('admin.faculty_workspace.editor.fields.department_code'))->maxLength(40),
                            Select::make('studyPlanDepartmentId')
                                ->label(__('admin.faculty_workspace.editor.fields.study_plan_link'))
                                ->options(fn (): array => $this->departmentStudyPlanOptions())
                                ->searchable()
                                ->placeholder(__('admin.faculty_workspace.editor.fields.automatic'))
                                ->helperText(__('admin.faculty_workspace.editor.help.study_plan_link')),
                        ]),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function labsSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.labs'))->schema([
                Repeater::make($prefix.'.items')
                    ->label(__('admin.faculty_workspace.editor.sections.labs'))
                    ->schema([
                        Hidden::make('slug')->default(fn (): string => 'lab-'.Str::lower(Str::random(8))),
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.lab_name'))->required()->maxLength(180),
                        TextInput::make('department')->label(__('admin.faculty_workspace.editor.fields.department'))->maxLength(160),
                        TextInput::make('instructor')->label(__('admin.faculty_workspace.editor.fields.instructor'))->maxLength(160),
                        MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image')),
                        Textarea::make('summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function projectsSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.projects'))->schema([
                Repeater::make($prefix.'.items')
                    ->label(__('admin.faculty_workspace.editor.sections.projects'))
                    ->schema([
                        Hidden::make('slug')->default(fn (): string => 'project-'.Str::lower(Str::random(8))),
                        Hidden::make('detailRoute'),
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.project_name'))->required()->maxLength(180),
                        TextInput::make('tag')->label(__('admin.faculty_workspace.editor.fields.category'))->maxLength(120),
                        TextInput::make('team')->label(__('admin.faculty_workspace.editor.fields.team'))->maxLength(180),
                        TextInput::make('supervisor')->label(__('admin.faculty_workspace.editor.fields.supervisor'))->maxLength(180),
                        TextInput::make('createdBy')->label(__('admin.faculty_workspace.editor.fields.created_by'))->maxLength(180),
                        TextInput::make('academicYear')->label(__('admin.faculty_workspace.editor.fields.academic_year'))->maxLength(40),
                        TextInput::make('status')->label(__('admin.faculty_workspace.editor.fields.status'))->maxLength(80),
                        MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image')),
                        Textarea::make('summary')->label(__('admin.faculty_workspace.editor.fields.summary'))->rows(3)->columnSpanFull(),
                        Repeater::make('longDescription')
                            ->label(__('admin.faculty_workspace.editor.fields.long_description'))
                            ->schema([
                                Textarea::make('paragraph')->label(__('admin.faculty_workspace.editor.fields.paragraph'))->required()->rows(3)->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Repeater::make('gallery')
                            ->label(__('admin.faculty_workspace.editor.fields.gallery_images'))
                            ->schema([
                                MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image'), true),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TagsInput::make('technologies')->label(__('admin.faculty_workspace.editor.fields.technologies'))->separator(',')->columnSpanFull(),
                        Repeater::make('teamMembers')
                            ->label(__('admin.faculty_workspace.editor.fields.team_members'))
                            ->schema([
                                TextInput::make('name')->label(__('admin.faculty_workspace.editor.fields.member_name'))->required()->maxLength(180),
                                TextInput::make('role')->label(__('admin.faculty_workspace.editor.fields.member_role'))->required()->maxLength(120),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function alumniSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.alumni'))->schema([
                Repeater::make($prefix.'.items')
                    ->label(__('admin.faculty_workspace.editor.sections.alumni'))
                    ->schema([
                        Hidden::make('_cmsKey'),
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.graduate_name'))->required()->maxLength(180),
                        TextInput::make('graduationYear')->label(__('admin.faculty_workspace.editor.fields.graduation_year'))->maxLength(20),
                        TextInput::make('semester')->label(__('admin.faculty_workspace.editor.fields.semester'))->maxLength(80),
                        TextInput::make('department')->label(__('admin.faculty_workspace.editor.fields.department'))->maxLength(160),
                        TextInput::make('faculty')->label(__('admin.faculty_workspace.editor.fields.faculty'))->maxLength(180),
                        TextInput::make('degree')->label(__('admin.faculty_workspace.editor.fields.degree'))->maxLength(120),
                        TextInput::make('academicPhase')->label(__('admin.faculty_workspace.editor.fields.academic_phase'))->maxLength(120),
                        MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image')),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function valedictoriansSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.honor_students'))->schema([
                Textarea::make($prefix.'.payload.quote')
                    ->label(__('admin.faculty_workspace.editor.fields.honor_quote'))
                    ->rows(2)
                    ->columnSpanFull(),
                Repeater::make($prefix.'.items')
                    ->label(__('admin.faculty_workspace.editor.sections.honor_students'))
                    ->schema([
                        Hidden::make('_cmsKey'),
                        TextInput::make('title')->label(__('admin.faculty_workspace.editor.fields.student_name'))->required()->maxLength(180),
                        TextInput::make('academicYear')->label(__('admin.faculty_workspace.editor.fields.academic_year'))->maxLength(40),
                        TextInput::make('semester')->label(__('admin.faculty_workspace.editor.fields.semester'))->maxLength(80),
                        TextInput::make('department')->label(__('admin.faculty_workspace.editor.fields.department'))->maxLength(160),
                        TextInput::make('faculty')->label(__('admin.faculty_workspace.editor.fields.faculty'))->maxLength(180),
                        TextInput::make('gpa')->label(__('admin.faculty_workspace.editor.fields.gpa'))->maxLength(20),
                        MediaPicker::image('image', __('admin.faculty_workspace.editor.fields.image')),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function researchSubpageFields(string $prefix): array
    {
        return [
            Section::make(__('admin.faculty_workspace.editor.sections.research_settings'))->schema([
                TextInput::make($prefix.'.emptyTitle')->label(__('admin.faculty_workspace.editor.fields.empty_title'))->maxLength(180),
                Textarea::make($prefix.'.emptySummary')->label(__('admin.faculty_workspace.editor.fields.empty_summary'))->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.seoTitle')->label(__('admin.faculty_workspace.editor.fields.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin.faculty_workspace.editor.fields.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin.faculty_workspace.editor.fields.seo_image')),
            ])->columns(2)->collapsed(),
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeContent(array $content): array
    {
        $content['faculty'] = is_array($content['faculty'] ?? null) ? $content['faculty'] : [];
        $content['tabs'] = $this->listOfArrays($content['tabs'] ?? []);
        $content['dean'] = is_array($content['dean'] ?? null) ? $content['dean'] : [];
        $content['gallery'] = $this->listOfArrays($content['gallery'] ?? []);
        $content['stats'] = $this->listOfArrays($content['stats'] ?? []);
        $content['latestResearch'] = $this->listOfArrays($content['latestResearch'] ?? []);

        return $content;
    }

    /** @return array<string, mixed> */
    private function contentForForm(string $targetKey, array $content, ?string $departmentId = null, ?string $termId = null): array
    {
        $subpageSlug = $this->subpageSlugFromTarget($targetKey);

        if ($this->hasFilterableRecords($targetKey)) {
            return $this->filterableRecordContentForForm($targetKey, $content);
        }

        if ($subpageSlug !== 'study-plan') {
            if ($subpageSlug === 'projects') {
                $content['items'] = array_map(function (array $item): array {
                    $item['longDescription'] = collect(is_array($item['longDescription'] ?? null) ? $item['longDescription'] : [])
                        ->map(static fn (mixed $paragraph): array => [
                            'paragraph' => is_array($paragraph)
                                ? (string) ($paragraph['paragraph'] ?? $paragraph['body'] ?? '')
                                : (string) $paragraph,
                        ])
                        ->filter(static fn (array $paragraph): bool => $paragraph['paragraph'] !== '')
                        ->values()
                        ->all();
                    $item['gallery'] = collect(is_array($item['gallery'] ?? null) ? $item['gallery'] : [])
                        ->map(static fn (mixed $image): array => ['image' => is_array($image) ? (string) ($image['image'] ?? '') : (string) $image])
                        ->filter(static fn (array $image): bool => $image['image'] !== '')
                        ->values()
                        ->all();
                    $item['teamMembers'] = $this->listOfArrays($item['teamMembers'] ?? []);

                    return $item;
                }, $this->listOfArrays($content['items'] ?? []));
            }

            return $content;
        }

        $payload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : [];
        unset($plan['departments'], $plan['terms'], $plan['courses'], $plan['lessons'], $plan['electivePools'], $plan['promotionRequirements']);
        $payload['plan'] = $plan;
        $payload['lessonTypes'] = $this->lessonTypesForForm($payload['lessonTypes'] ?? []);
        $content['payload'] = $payload;

        return $content;
    }

    /** @return array<string, mixed> */
    private function filterableRecordContentForForm(string $targetKey, array $content): array
    {
        $items = $this->recordItemsWithKeys($this->listOfArrays($content['items'] ?? []));

        if (! $this->hasActiveRecordFilters()) {
            $content['items'] = [];

            return $content;
        }

        $content['items'] = array_values(array_filter($items, fn (array $item): bool => $this->recordMatchesFilters($targetKey, $item)));

        return $content;
    }

    /** @return array<string, mixed> */
    private function normalizeSubpageContent(string $targetKey, string $locale, array $content): array
    {
        $content['sections'] = $this->listOfArrays($content['sections'] ?? []);
        $subpageSlug = $this->subpageSlugFromTarget($targetKey);

        if ($subpageSlug === 'overview') {
            $content['stats'] = $this->listOfArrays($content['stats'] ?? []);
            $content['dean'] = is_array($content['dean'] ?? null) ? $content['dean'] : [];
            unset($content['items']);

            return $content;
        }

        if ($subpageSlug === 'study-plan') {
            $content['payload'] = is_array($content['payload'] ?? null) ? $content['payload'] : [];
            $content['payload']['plan'] = is_array($content['payload']['plan'] ?? null) ? $content['payload']['plan'] : [];
            $content['payload']['labels'] = is_array($content['payload']['labels'] ?? null) ? $content['payload']['labels'] : [];
            $content['payload']['courseLabels'] = is_array($content['payload']['courseLabels'] ?? null) ? $content['payload']['courseLabels'] : [];
            $content['payload']['legend'] = $this->listOfArrays($content['payload']['legend'] ?? []);
            $content['payload']['lessonTypes'] = $this->keyedItemsById($content['payload']['lessonTypes'] ?? []);
            $content = $this->mergeStudyPlanDepartmentContent($targetKey, $locale, $content);
            unset($content['items'], $content['stats'], $content['dean']);

            return $content;
        }

        if ($subpageSlug === 'research') {
            unset($content['items'], $content['stats'], $content['dean']);

            return $content;
        }

        $content['items'] = $this->listOfArrays($content['items'] ?? []);
        $content['items'] = array_map(function (array $item) use ($subpageSlug): array {
            if ($subpageSlug === 'departments') {
                $item['tags'] = array_values(array_filter(is_array($item['tags'] ?? null) ? $item['tags'] : []));
            }

            if ($subpageSlug === 'projects') {
                $item['longDescription'] = collect($this->listOfArrays($item['longDescription'] ?? []))
                    ->map(static fn (array $paragraph): string => (string) ($paragraph['paragraph'] ?? $paragraph['body'] ?? ''))
                    ->filter()
                    ->values()
                    ->all();
                $item['gallery'] = collect(is_array($item['gallery'] ?? null) ? $item['gallery'] : [])
                    ->map(static fn (mixed $image): string => is_array($image) ? (string) ($image['image'] ?? '') : (string) $image)
                    ->filter()
                    ->values()
                    ->all();
                $item['technologies'] = array_values(array_filter(is_array($item['technologies'] ?? null) ? $item['technologies'] : [], static fn (mixed $technology): bool => is_string($technology) && trim($technology) !== ''));
                $item['teamMembers'] = collect($this->listOfArrays($item['teamMembers'] ?? []))
                    ->map(static fn (array $member): array => [
                        'name' => (string) ($member['name'] ?? ''),
                        'role' => (string) ($member['role'] ?? ''),
                    ])
                    ->filter(static fn (array $member): bool => $member['name'] !== '')
                    ->values()
                    ->all();
            }

            return $item;
        }, $content['items']);

        if ($this->hasFilterableRecords($targetKey)) {
            $content = $this->mergeFilterableRecordContent($targetKey, $locale, $content);
        }

        $content['items'] = array_map(fn (array $item): array => $this->withoutKeys($item, ['_cmsKey']), $content['items']);
        unset($content['stats'], $content['dean']);

        return $content;
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    private function authenticatedUserId(): int
    {
        $userId = auth()->id();

        if (! is_int($userId) && ! is_string($userId)) {
            throw new AuthorizationException('An authenticated CMS user is required.');
        }

        return (int) $userId;
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? $this->defaultTargetKey());
        $this->assertManagedTarget($targetKey);

        return $targetKey;
    }

    private function currentTargetKeyForSchema(): string
    {
        if (is_string($this->activeTargetKey) && $this->activeTargetKey !== '') {
            return $this->activeTargetKey;
        }

        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== ''
            ? $this->data['target_key']
            : $this->defaultTargetKey();
    }

    private function assertManagedTarget(string $targetKey): void
    {
        if (! array_key_exists($targetKey, $this->targetOptions())) {
            throw new \InvalidArgumentException('Unsupported faculty CMS target.');
        }
    }

    private function subpageSlugFromTarget(string $targetKey): string
    {
        $parts = explode('.', $targetKey);
        $slug = (string) ($parts[2] ?? '');

        return $slug === 'study_plan' ? 'study-plan' : $slug;
    }

    private function hasFilterableRecords(string $targetKey): bool
    {
        return in_array($this->subpageSlugFromTarget($targetKey), ['alumni', 'valedictorians'], true);
    }

    private function hasActiveRecordFilters(): bool
    {
        return trim((string) ($this->data['record_search'] ?? '')) !== ''
            || trim((string) ($this->data['record_department_filter'] ?? '')) !== ''
            || trim((string) ($this->data['record_year_filter'] ?? '')) !== '';
    }

    /** @param array<string, mixed> $item */
    private function recordMatchesFilters(string $targetKey, array $item): bool
    {
        $search = trim((string) ($this->data['record_search'] ?? ''));
        $department = trim((string) ($this->data['record_department_filter'] ?? ''));
        $year = trim((string) ($this->data['record_year_filter'] ?? ''));
        $yearKey = $this->subpageSlugFromTarget($targetKey) === 'alumni' ? 'graduationYear' : 'academicYear';

        if ($search !== '' && ! Str::contains(Str::lower($this->recordSearchText($item)), Str::lower($search))) {
            return false;
        }

        if ($department !== '' && ! Str::contains(Str::lower((string) ($item['department'] ?? '').' '.(string) ($item['faculty'] ?? '')), Str::lower($department))) {
            return false;
        }

        if ($year !== '' && ! Str::contains(Str::lower((string) ($item[$yearKey] ?? '')), Str::lower($year))) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $item */
    private function recordSearchText(array $item): string
    {
        return implode(' ', array_map('strval', array_filter([
            $item['title'] ?? null,
            $item['graduationYear'] ?? null,
            $item['academicYear'] ?? null,
            $item['semester'] ?? null,
            $item['department'] ?? null,
            $item['faculty'] ?? null,
            $item['degree'] ?? null,
            $item['academicPhase'] ?? null,
            $item['gpa'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '')));
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function recordItemsWithKeys(array $items): array
    {
        return array_map(function (array $item, int $index): array {
            $item['_cmsKey'] = (string) ($item['_cmsKey'] ?? sha1($index.'|'.$this->recordSearchText($item)));

            return $item;
        }, $items, array_keys($items));
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function mergeFilterableRecordContent(string $targetKey, string $locale, array $content): array
    {
        $basePayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $this->authenticatedUserId()) ?? $this->facultyPageService->getEditablePayload($targetKey);
        $baseContent = is_array($basePayload['translations'][$locale] ?? null) ? $basePayload['translations'][$locale] : [];
        $baseItems = $this->recordItemsWithKeys($this->listOfArrays($baseContent['items'] ?? []));
        $editedItems = $this->recordItemsWithKeys($this->listOfArrays($content['items'] ?? []));

        if (! $this->hasActiveRecordFilters()) {
            $content['items'] = [...$baseItems, ...$editedItems];

            return $content;
        }

        $editedByKey = collect($editedItems)->keyBy(fn (array $item): string => (string) ($item['_cmsKey'] ?? ''));
        $seenKeys = [];

        $content['items'] = array_map(function (array $baseItem) use ($targetKey, $editedByKey, &$seenKeys): array {
            $key = (string) ($baseItem['_cmsKey'] ?? '');

            if ($this->recordMatchesFilters($targetKey, $baseItem)) {
                $seenKeys[] = $key;

                $editedItem = $editedByKey->get($key);

                if (is_array($editedItem)) {
                    return $editedItem;
                }
            }

            return $baseItem;
        }, $baseItems);

        foreach ($editedItems as $editedItem) {
            $key = (string) ($editedItem['_cmsKey'] ?? '');

            if ($key !== '' && in_array($key, $seenKeys, true)) {
                continue;
            }

            $content['items'][] = $editedItem;
        }

        return $content;
    }

    /** @param array<string, mixed> $payload @return array<string, string> */
    private function studyPlanDepartmentOptionsFromPayload(string $targetKey, array $payload): array
    {
        if ($this->subpageSlugFromTarget($targetKey) !== 'study-plan') {
            return [];
        }

        $content = is_array($payload['translations']['en'] ?? null)
            ? $payload['translations']['en']
            : (is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : []);
        $departments = is_array($content['payload']['plan']['departments'] ?? null) ? $content['payload']['plan']['departments'] : [];
        $options = [];

        foreach ($this->listOfArrays($departments) as $department) {
            $id = (string) ($department['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $options[$id] = (string) ($department['name'] ?? $department['nameEn'] ?? $department['nameAr'] ?? $id);
        }

        return $options;
    }

    /** @param array<string, mixed> $payload */
    private function studyPlanDepartmentIdFromPayload(string $targetKey, array $payload, string $preferred): ?string
    {
        $options = $this->studyPlanDepartmentOptionsFromPayload($targetKey, $payload);

        if ($preferred !== '' && array_key_exists($preferred, $options)) {
            return $preferred;
        }

        $ids = array_keys($options);

        return $ids === [] ? null : (string) $ids[0];
    }

    /** @param array<string, mixed> $payload @return array<string, string> */
    private function studyPlanTermOptionsFromPayload(string $targetKey, array $payload, ?string $departmentId): array
    {
        if ($this->subpageSlugFromTarget($targetKey) !== 'study-plan' || $departmentId === null || $departmentId === '') {
            return [];
        }

        $content = is_array($payload['translations']['en'] ?? null)
            ? $payload['translations']['en']
            : (is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : []);
        $departments = is_array($content['payload']['plan']['departments'] ?? null) ? $content['payload']['plan']['departments'] : [];
        $department = collect($this->listOfArrays($departments))->firstWhere('id', $departmentId);
        $options = [];

        foreach ($this->listOfArrays(is_array($department) ? ($department['terms'] ?? []) : []) as $term) {
            $id = (string) ($term['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $options[$id] = (string) ($term['label'] ?? $id);
        }

        return $options;
    }

    /** @param array<string, mixed> $payload */
    private function studyPlanTermIdFromPayload(string $targetKey, array $payload, ?string $departmentId, string $preferred): ?string
    {
        $options = $this->studyPlanTermOptionsFromPayload($targetKey, $payload, $departmentId);

        if ($preferred !== '' && array_key_exists($preferred, $options)) {
            return $preferred;
        }

        $ids = array_keys($options);

        return $ids === [] ? null : (string) $ids[0];
    }

    /** @return array<string, string> */
    private function studyPlanDepartmentOptions(): array
    {
        return is_array($this->data['study_plan_department_options'] ?? null) ? $this->data['study_plan_department_options'] : [];
    }

    /** @return array<string, string> */
    private function studyPlanTermOptions(): array
    {
        return is_array($this->data['study_plan_term_options'] ?? null) ? $this->data['study_plan_term_options'] : [];
    }

    /** @return array<string, string> */
    private function studyPlanCourseOptions(): array
    {
        return is_array($this->data['study_plan_course_options'] ?? null) ? $this->data['study_plan_course_options'] : [];
    }

    /** @return array<string, string> */
    private function studyPlanLessonTypeOptions(): array
    {
        return is_array($this->data['study_plan_lesson_type_options'] ?? null) ? $this->data['study_plan_lesson_type_options'] : [];
    }

    /**
     * Resolve the Study Plan department options that should be presented per department row in the
     * departments subpage editor. They are loaded from the matching faculty's study_plan target draft
     * or published payload, regardless of the current departments subpage being edited.
     *
     * @return array<string, string> Map of study-plan department tab id => bilingual label.
     */
    private function departmentStudyPlanOptionsFromTarget(string $targetKey): array
    {
        if ($this->subpageSlugFromTarget($targetKey) !== 'departments') {
            return [];
        }

        return $this->studyPlanLinkService->optionsForDepartmentsTarget($targetKey);
    }

    /** @return array<string, string> */
    private function departmentStudyPlanOptions(): array
    {
        return is_array($this->data['department_study_plan_options'] ?? null) ? $this->data['department_study_plan_options'] : [];
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function mergeStudyPlanDepartmentContent(string $targetKey, string $locale, array $content): array
    {
        $basePayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $this->authenticatedUserId()) ?? $this->facultyPageService->getEditablePayload($targetKey);
        $baseContent = is_array($basePayload['translations'][$locale] ?? null) ? $basePayload['translations'][$locale] : [];
        $baseContent['payload'] = is_array($baseContent['payload'] ?? null) ? $baseContent['payload'] : [];
        $baseContent['payload']['plan'] = is_array($baseContent['payload']['plan'] ?? null) ? $baseContent['payload']['plan'] : [];
        $editedPayload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        $editedPlan = is_array($editedPayload['plan'] ?? null) ? $editedPayload['plan'] : [];

        foreach (['faculty', 'heroImage', 'accent'] as $key) {
            if (array_key_exists($key, $editedPlan)) {
                $baseContent['payload']['plan'][$key] = $editedPlan[$key];
            }
        }

        foreach (['labels', 'courseLabels', 'legend', 'lessonTypes'] as $key) {
            if (array_key_exists($key, $editedPayload)) {
                $baseContent['payload'][$key] = $editedPayload[$key];
            }
        }

        foreach (['title', 'summary', 'body', 'heroImage', 'sections'] as $key) {
            if (array_key_exists($key, $content)) {
                $baseContent[$key] = $content[$key];
            }
        }

        return $baseContent;
    }

    /** @return array<string, array<string, mixed>> */
    private function keyedItemsById(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $keyed = [];

        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $id = (string) ($item['id'] ?? $key);
                unset($item['id']);
                $keyed[$id] = $item;
            }
        }

        return $keyed;
    }

    /** @return array<int, array<string, mixed>> */
    private function lessonTypesForForm(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $rows[] = ['id' => (string) ($item['id'] ?? $key), ...$item];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $item @param array<int, string> $keys @return array<string, mixed> */
    private function withoutKeys(array $item, array $keys): array
    {
        foreach ($keys as $key) {
            unset($item[$key]);
        }

        return $item;
    }

    /** @return array<int, array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return array_values(array_filter(is_array($items) ? $items : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
