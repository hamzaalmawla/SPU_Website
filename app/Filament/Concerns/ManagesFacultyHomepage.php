<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ManagesFacultyHomepage
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private FacultyPageServiceInterface $facultyPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    /** @return array<string, string> */
    abstract protected function targetOptions(): array;

    abstract protected function defaultTargetKey(): string;

    public function boot(
        FacultyPageServiceInterface $facultyPageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->facultyPageService = $facultyPageService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-faculties');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.facilities');
    }

    public function mount(): void
    {
        $this->loadTarget($this->defaultTargetKey());
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertManagedTarget($targetKey);
        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey);
        $payload = is_array($draftPayload) ? $draftPayload : $this->facultyPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);
        $studyPlanDepartmentOptions = $this->studyPlanDepartmentOptionsFromPayload($targetKey, $payload);
        $studyPlanDepartmentId = $this->studyPlanDepartmentIdFromPayload($targetKey, $payload, (string) ($this->data['study_plan_department_id'] ?? ''));
        $studyPlanTermOptions = $this->studyPlanTermOptionsFromPayload($targetKey, $payload, $studyPlanDepartmentId);
        $studyPlanTermId = $this->studyPlanTermIdFromPayload($targetKey, $payload, $studyPlanDepartmentId, (string) ($this->data['study_plan_term_id'] ?? ''));

        $this->form->fill([
            'target_key' => $targetKey,
            'study_plan_department_id' => $studyPlanDepartmentId,
            'study_plan_term_id' => $studyPlanTermId,
            'record_search' => (string) ($this->data['record_search'] ?? ''),
            'record_department_filter' => (string) ($this->data['record_department_filter'] ?? ''),
            'record_year_filter' => (string) ($this->data['record_year_filter'] ?? ''),
            'ar_content' => $this->contentForForm($targetKey, is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [], $studyPlanDepartmentId, $studyPlanTermId),
            'en_content' => $this->contentForForm($targetKey, is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [], $studyPlanDepartmentId, $studyPlanTermId),
        ]);

        if (is_array($this->data)) {
            $this->data['study_plan_department_options'] = $studyPlanDepartmentOptions;
            $this->data['study_plan_term_options'] = $studyPlanTermOptions;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Medicine Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Subpage')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                    Select::make('study_plan_department_id')
                        ->label('Study Plan Department')
                        ->options(fn (): array => $this->studyPlanDepartmentOptions())
                        ->visible(fn (): bool => $this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) === 'study-plan')
                        ->live()
                        ->afterStateUpdated(fn (): mixed => $this->loadTarget($this->currentTargetKeyForSchema())),
                    Select::make('study_plan_term_id')
                        ->label('Open Term Folder')
                        ->options(fn (): array => $this->studyPlanTermOptions())
                        ->visible(fn (): bool => $this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) === 'study-plan')
                        ->live()
                        ->afterStateUpdated(fn (): mixed => $this->loadTarget($this->currentTargetKeyForSchema())),
                    TextInput::make('record_search')
                        ->label('Search Records')
                        ->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema()))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (): mixed => $this->loadTarget($this->currentTargetKeyForSchema())),
                    TextInput::make('record_department_filter')
                        ->label('Department / Faculty Filter')
                        ->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema()))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (): mixed => $this->loadTarget($this->currentTargetKeyForSchema())),
                    TextInput::make('record_year_filter')
                        ->label('Year Filter')
                        ->visible(fn (): bool => $this->hasFilterableRecords($this->currentTargetKeyForSchema()))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (): mixed => $this->loadTarget($this->currentTargetKeyForSchema())),
                ]),
                Tabs::make('faculty_homepage_locales')
                    ->tabs([
                        Tab::make('Arabic')->schema($this->payloadFields('ar')),
                        Tab::make('English')->schema($this->payloadFields('en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save Draft')->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label('Preview AR')->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label('Preview EN')->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')->label('Publish')->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()->action(function (): void {
                $this->publish();
            }),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label('Publish At')->required()->minDate(now())->native(false),
                ])
                ->action(fn (array $data): mixed => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(function (): void {
                $this->unpublish();
            }),
        ];
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('Faculty draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this faculty target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save faculty draft')->body($e->getMessage())->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
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
            Notification::make()->title('Draft conflict detected')->body('Reload this faculty target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create faculty preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Faculty homepage published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish faculty homepage')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Faculty homepage scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule faculty homepage')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'Faculty homepage unpublished' : 'No published faculty homepage found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->currentTargetKeyForSchema() !== $this->defaultTargetKey()) {
            return $this->subpageFields($locale);
        }

        $prefix = $locale.'_content';

        return [
            Section::make('Page Content')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->maxLength(180),
                TextInput::make($prefix.'.summary')->label('Summary')->maxLength(255),
                Textarea::make($prefix.'.body')->label('Body')->rows(5)->columnSpanFull(),
            ])->columns(2),

            Section::make('Faculty Identity')->schema([
                TextInput::make($prefix.'.faculty.name')->label('Name')->maxLength(180),
                TextInput::make($prefix.'.faculty.title')->label('Display Title')->maxLength(180),
                TextInput::make($prefix.'.faculty.yearsLabel')->label('Years Label')->maxLength(80),
                TextInput::make($prefix.'.faculty.accentColor')->label('Accent Color')->maxLength(20),
                MediaPicker::image($prefix.'.faculty.heroImage', 'Hero Image'),
                MediaPicker::image($prefix.'.faculty.logoImage', 'Logo Image'),
                Textarea::make($prefix.'.faculty.summary')->label('Short Summary')->rows(2)->columnSpanFull(),
                Textarea::make($prefix.'.faculty.description')->label('Description')->rows(4)->columnSpanFull(),
            ])->columns(2),

            Section::make('Overview Tabs')->schema([
                Repeater::make($prefix.'.tabs')
                    ->label('Tabs')
                    ->schema([
                        TextInput::make('id')->maxLength(80),
                        TextInput::make('label')->maxLength(120),
                        Textarea::make('body')->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Dean Message')->schema([
                TextInput::make($prefix.'.dean.name')->label('Dean Name')->maxLength(160),
                TextInput::make($prefix.'.dean.role')->label('Dean Role')->maxLength(160),
                MediaPicker::image($prefix.'.dean.image', 'Dean Image'),
                Textarea::make($prefix.'.dean.message')->label('Message')->rows(4)->columnSpanFull(),
            ])->columns(2),

            Section::make('Gallery')->schema([
                Repeater::make($prefix.'.gallery')
                    ->label('Gallery Images')
                    ->schema([
                        MediaPicker::image('image', 'Image'),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Stats')->schema([
                Repeater::make($prefix.'.stats')
                    ->label('Stats')
                    ->schema([
                        TextInput::make('value')->maxLength(40),
                        TextInput::make('label')->maxLength(120),
                        MediaPicker::icon('icon', 'Icon'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Latest Research Cards')->schema([
                Repeater::make($prefix.'.latestResearch')
                    ->label('Research Cards')
                    ->schema([
                        TextInput::make('title')->maxLength(180),
                        TextInput::make('type')->maxLength(120),
                        TextInput::make('date')->maxLength(80),
                        TextInput::make('doi')->maxLength(120),
                        MediaPicker::image('image', 'Image'),
                        TextInput::make('url')->maxLength(255),
                        TextInput::make('cta')->maxLength(120),
                        Textarea::make('summary')->rows(2)->columnSpanFull(),
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

        return [
            'translations' => [
                'ar' => $targetKey === $this->defaultTargetKey()
                    ? $this->normalizeContent(is_array($state['ar_content'] ?? null) ? $state['ar_content'] : [])
                    : $this->normalizeSubpageContent($targetKey, 'ar', is_array($state['ar_content'] ?? null) ? $state['ar_content'] : []),
                'en' => $targetKey === $this->defaultTargetKey()
                    ? $this->normalizeContent(is_array($state['en_content'] ?? null) ? $state['en_content'] : [])
                    : $this->normalizeSubpageContent($targetKey, 'en', is_array($state['en_content'] ?? null) ? $state['en_content'] : []),
            ],
        ];
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
            default => throw new \InvalidArgumentException('Unsupported faculty subpage target.'),
        };
    }

    /** @return array<int, Section> */
    private function baseSubpageFields(string $prefix): array
    {
        return [
            Section::make('Subpage Content')->schema([
                TextInput::make($prefix.'.title')->label('Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.heroImage', 'Hero Image'),
                Textarea::make($prefix.'.summary')->label('Summary')->rows(2)->columnSpanFull(),
                Textarea::make($prefix.'.body')->label('Body')->rows(4)->columnSpanFull(),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function overviewSubpageFields(string $prefix): array
    {
        return [
            Section::make('Overview Sections')->schema([
                Repeater::make($prefix.'.sections')
                    ->label('Narrative Sections')
                    ->schema([
                        TextInput::make('id')->maxLength(80),
                        TextInput::make('title')->maxLength(160),
                        Textarea::make('body')->rows(4)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Overview Stats')->schema([
                Repeater::make($prefix.'.stats')
                    ->label('Stats')
                    ->schema([
                        TextInput::make('value')->maxLength(40),
                        TextInput::make('label')->maxLength(120),
                        MediaPicker::icon('icon', 'Icon'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Dean Message')->schema([
                TextInput::make($prefix.'.dean.nameAr')->label('Dean Name AR')->maxLength(160),
                TextInput::make($prefix.'.dean.nameEn')->label('Dean Name EN')->maxLength(160),
                TextInput::make($prefix.'.dean.roleAr')->label('Dean Role AR')->maxLength(160),
                TextInput::make($prefix.'.dean.roleEn')->label('Dean Role EN')->maxLength(160),
                MediaPicker::image($prefix.'.dean.image', 'Dean Image'),
                Textarea::make($prefix.'.dean.messageAr')->label('Message AR')->rows(4),
                Textarea::make($prefix.'.dean.messageEn')->label('Message EN')->rows(4),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function studyPlanSubpageFields(string $prefix): array
    {
        return [
            Section::make('Study Plan Labels')->schema([
                TextInput::make($prefix.'.payload.labels.title')->label('Title')->maxLength(160),
                TextInput::make($prefix.'.payload.labels.home')->label('Home Label')->maxLength(120),
                TextInput::make($prefix.'.payload.labels.faculties')->label('Facilities Label')->maxLength(120),
                TextInput::make($prefix.'.payload.labels.empty')->label('Empty Label')->maxLength(160),
                TextInput::make($prefix.'.payload.labels.electiveRequirements')->label('Elective Requirements Label')->maxLength(160),
                TextInput::make($prefix.'.payload.labels.promotionRequirements')->label('Promotion Requirements Label')->maxLength(160),
                TextInput::make($prefix.'.payload.labels.viewDetails')->label('Course Details Label')->maxLength(120),
                TextInput::make($prefix.'.payload.labels.close')->label('Close Label')->maxLength(120),
            ])->columns(2),

            Section::make('Course Page Labels')->schema([
                TextInput::make($prefix.'.payload.courseLabels.studyPlan')->label('Study Plan Label')->maxLength(160),
                TextInput::make($prefix.'.payload.courseLabels.coursePage')->label('Course Page Label')->maxLength(160),
                TextInput::make($prefix.'.payload.courseLabels.credits')->label('Credits Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.courseType')->label('Course Type Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.requiredStatus')->label('Required Status Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.required')->label('Required Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.elective')->label('Elective Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.prerequisites')->label('Prerequisites Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.opensAfter')->label('Opens After Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.lessons')->label('Lessons Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.all')->label('All Lessons Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.viewPdf')->label('View PDF Label')->maxLength(120),
                TextInput::make($prefix.'.payload.courseLabels.download')->label('Download Label')->maxLength(120),
            ])->columns(2),

            Section::make('Study Plan Departments')->schema([
                TextInput::make($prefix.'.payload.plan.faculty')->label('Plan Faculty Name')->maxLength(180),
                MediaPicker::image($prefix.'.payload.plan.heroImage', 'Plan Hero Image'),
                TextInput::make($prefix.'.payload.plan.accent')->label('Plan Accent Color')->maxLength(20),
                Repeater::make($prefix.'.payload.plan.departments')
                    ->label('Departments')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('name')->required()->maxLength(160),
                        TextInput::make('totalCredits')->numeric(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Study Plan Tree')->schema([
                Repeater::make($prefix.'.payload.plan.terms')
                    ->label('Term Folders')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                        Repeater::make('courses')
                            ->label('Courses In This Term')
                            ->visible(fn (Get $get): bool => (string) $get('id') === (string) ($this->data['study_plan_term_id'] ?? ''))
                            ->schema([
                                TextInput::make('id')->required()->label('Course ID')->maxLength(80),
                                TextInput::make('code')->maxLength(80),
                                TextInput::make('title')->required()->maxLength(180),
                                TextInput::make('credits')->numeric(),
                                Select::make('type')->options([
                                    'university' => 'University Requirement',
                                    'faculty' => 'Faculty Requirement',
                                    'specialization' => 'Specialization Requirement',
                                ]),
                                Toggle::make('required')->label('Required'),
                                TagsInput::make('prerequisites')
                                    ->label('Prerequisite Course IDs')
                                    ->helperText('Incoming lines: courses that must be completed before this course.')
                                    ->columnSpanFull(),
                                TagsInput::make('opensCourseIds')
                                    ->label('Opens Course IDs')
                                    ->helperText('Outgoing lines: courses unlocked by this course. These are saved as prerequisites on the target courses for the public graph.')
                                    ->columnSpanFull(),
                                TextInput::make('instructor.nameAr')->label('Instructor AR')->maxLength(160),
                                TextInput::make('instructor.nameEn')->label('Instructor EN')->maxLength(160),
                                TextInput::make('instructor.staffSlug')->label('Instructor Profile Slug')->maxLength(120),
                                Textarea::make('description')->rows(2)->columnSpanFull(),
                                Repeater::make('lessons')
                                    ->label('Lessons')
                                    ->schema([
                                        TextInput::make('order')->numeric(),
                                        Select::make('type')->options([
                                            'lecture' => 'Lecture',
                                            'practical' => 'Practical',
                                            'seminar' => 'Seminar',
                                        ]),
                                        TextInput::make('title')->required()->maxLength(180),
                                        MediaPicker::document('pdfUrl', 'PDF File'),
                                        Textarea::make('description')->rows(2)->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->collapsible()
                                    ->collapsed()
                                    ->columnSpanFull(),
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
                    ->collapsed()
                    ->columnSpanFull(),
            ]),

            Section::make('Electives & Promotion')->schema([
                Repeater::make($prefix.'.payload.plan.electivePools')
                    ->label('Elective Pools')
                    ->schema([
                        TextInput::make('departmentId')->required()->label('Department ID')->maxLength(80),
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('requiredHours')->numeric(),
                        Textarea::make('description')->rows(2)->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.payload.plan.promotionRequirements')
                    ->label('Promotion Requirements')
                    ->schema([
                        TextInput::make('departmentId')->required()->label('Department ID')->maxLength(80),
                        TextInput::make('fromYear')->required()->maxLength(20),
                        TextInput::make('toYear')->required()->maxLength(20),
                        TextInput::make('requiredCredits')->numeric(),
                    ])
                    ->columns(4)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Legend & Lesson Types')->schema([
                Repeater::make($prefix.'.payload.legend')
                    ->label('Course Type Legend')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.payload.lessonTypes')
                    ->label('Lesson Types')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
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
    private function departmentsSubpageFields(string $prefix): array
    {
        return [
            Section::make('Department Directory')->schema([
                Repeater::make($prefix.'.items')
                    ->label('Departments')
                    ->schema([
                        TextInput::make('slug')->required()->maxLength(100),
                        TextInput::make('code')->maxLength(40),
                        TextInput::make('title')->required()->maxLength(180),
                        TextInput::make('degrees')->label('Degree / Track')->maxLength(160),
                        TagsInput::make('tags')->columnSpanFull(),
                        Textarea::make('summary')->rows(3)->columnSpanFull(),
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
            Section::make('Laboratories')->schema([
                Repeater::make($prefix.'.items')
                    ->label('Labs')
                    ->schema([
                        TextInput::make('slug')->required()->maxLength(100),
                        TextInput::make('title')->required()->maxLength(180),
                        TextInput::make('department')->maxLength(160),
                        TextInput::make('instructor')->maxLength(160),
                        MediaPicker::image('image', 'Image'),
                        Textarea::make('summary')->rows(3)->columnSpanFull(),
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
            Section::make('Student Projects')->schema([
                Repeater::make($prefix.'.items')
                    ->label('Projects')
                    ->schema([
                        TextInput::make('slug')->required()->maxLength(100),
                        TextInput::make('title')->required()->maxLength(180),
                        TextInput::make('tag')->maxLength(120),
                        TextInput::make('team')->maxLength(180),
                        TextInput::make('supervisor')->maxLength(180),
                        MediaPicker::image('image', 'Image'),
                        TextInput::make('detailRoute')->maxLength(255),
                        Textarea::make('summary')->rows(3)->columnSpanFull(),
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
            Section::make('Alumni Records')->schema([
                Repeater::make($prefix.'.items')
                    ->label('Alumni')
                    ->schema([
                        Hidden::make('_cmsKey'),
                        TextInput::make('title')->label('Graduate Name')->required()->maxLength(180),
                        TextInput::make('graduationYear')->maxLength(20),
                        TextInput::make('semester')->maxLength(80),
                        TextInput::make('department')->maxLength(160),
                        TextInput::make('faculty')->maxLength(180),
                        TextInput::make('degree')->maxLength(120),
                        TextInput::make('academicPhase')->maxLength(120),
                        MediaPicker::image('image', 'Image'),
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
            Section::make('Honor List Records')->schema([
                Repeater::make($prefix.'.items')
                    ->label('Honor Students')
                    ->schema([
                        Hidden::make('_cmsKey'),
                        TextInput::make('title')->label('Student Name')->required()->maxLength(180),
                        TextInput::make('academicYear')->maxLength(40),
                        TextInput::make('semester')->maxLength(80),
                        TextInput::make('department')->maxLength(160),
                        TextInput::make('faculty')->maxLength(180),
                        TextInput::make('gpa')->maxLength(20),
                        MediaPicker::image('image', 'Image'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
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
            return $content;
        }

        $payload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        $plan = is_array($payload['plan'] ?? null) ? $payload['plan'] : [];
        $departments = $this->listOfArrays($plan['departments'] ?? []);
        $flatDepartments = [];
        $terms = [];
        $electivePools = [];
        $promotionRequirements = [];

        $departmentId = $departmentId !== null && $departmentId !== '' ? $departmentId : (string) ($departments[0]['id'] ?? '');
        $selectedTermId = $termId !== null && $termId !== '' ? $termId : null;

        foreach ($departments as $department) {
            $currentDepartmentId = (string) ($department['id'] ?? '');
            $flatDepartments[] = $this->withoutKeys($department, ['terms', 'electivePools', 'promotionRequirements']);

            if ($currentDepartmentId !== $departmentId) {
                continue;
            }

            $terms = $this->termsWithOpensCourseIds($this->listOfArrays($department['terms'] ?? []), $selectedTermId);

            foreach ($this->listOfArrays($department['electivePools'] ?? []) as $pool) {
                $electivePools[] = ['departmentId' => $currentDepartmentId, ...$pool];
            }

            foreach ($this->listOfArrays($department['promotionRequirements'] ?? []) as $requirement) {
                $promotionRequirements[] = ['departmentId' => $currentDepartmentId, ...$requirement];
            }
        }

        $payload['plan'] = [
            ...$plan,
            'departments' => $flatDepartments,
            'terms' => $terms,
            'electivePools' => $electivePools,
            'promotionRequirements' => $promotionRequirements,
        ];
        $payload['lessonTypes'] = $this->lessonTypesForForm($payload['lessonTypes'] ?? []);
        $content['payload'] = $payload;

        return $content;
    }

    /** @return array<string, mixed> */
    private function filterableRecordContentForForm(string $targetKey, array $content): array
    {
        $items = $this->recordItemsWithKeys($this->listOfArrays($content['items'] ?? []));

        if (! $this->hasActiveRecordFilters()) {
            $content['items'] = $items;

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

        $content['items'] = $this->listOfArrays($content['items'] ?? []);
        $content['items'] = array_map(function (array $item) use ($subpageSlug): array {
            if ($subpageSlug === 'departments') {
                $item['tags'] = array_values(array_filter(is_array($item['tags'] ?? null) ? $item['tags'] : []));
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

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? $this->defaultTargetKey());
        $this->assertManagedTarget($targetKey);

        return $targetKey;
    }

    private function currentTargetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== '' ? $this->data['target_key'] : $this->defaultTargetKey();
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
        if (! $this->hasActiveRecordFilters()) {
            return $content;
        }

        $basePayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey) ?? $this->facultyPageService->getEditablePayload($targetKey);
        $baseContent = is_array($basePayload['translations'][$locale] ?? null) ? $basePayload['translations'][$locale] : [];
        $baseItems = $this->recordItemsWithKeys($this->listOfArrays($baseContent['items'] ?? []));
        $editedItems = $this->recordItemsWithKeys($this->listOfArrays($content['items'] ?? []));
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

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private function mergeStudyPlanDepartmentContent(string $targetKey, string $locale, array $content): array
    {
        $basePayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey) ?? $this->facultyPageService->getEditablePayload($targetKey);
        $baseContent = is_array($basePayload['translations'][$locale] ?? null) ? $basePayload['translations'][$locale] : [];
        $baseContent['payload'] = is_array($baseContent['payload'] ?? null) ? $baseContent['payload'] : [];
        $baseContent['payload']['plan'] = is_array($baseContent['payload']['plan'] ?? null) ? $baseContent['payload']['plan'] : [];
        $baseDepartments = $this->listOfArrays($baseContent['payload']['plan']['departments'] ?? []);
        $selectedDepartmentId = (string) ($this->data['study_plan_department_id'] ?? '');
        $selectedTermId = (string) ($this->data['study_plan_term_id'] ?? '');
        $editedPayload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        $editedPlan = $this->nestedStudyPlan(is_array($editedPayload['plan'] ?? null) ? $editedPayload['plan'] : []);
        $editedDepartment = collect($this->listOfArrays($editedPlan['departments'] ?? []))->firstWhere('id', $selectedDepartmentId);

        if (is_array($editedDepartment)) {
            $replaced = false;
            $baseDepartments = array_map(function (array $department) use ($editedDepartment, $selectedDepartmentId, $selectedTermId, &$replaced): array {
                if ((string) ($department['id'] ?? '') !== $selectedDepartmentId) {
                    return $department;
                }

                $replaced = true;

                return $this->mergeStudyPlanDepartment($department, $editedDepartment, $selectedTermId);
            }, $baseDepartments);

            if (! $replaced) {
                $baseDepartments[] = $this->applyStudyPlanOpensCourseIds($editedDepartment);
            }
        }

        foreach (['faculty', 'heroImage', 'accent'] as $key) {
            if (array_key_exists($key, $editedPlan)) {
                $baseContent['payload']['plan'][$key] = $editedPlan[$key];
            }
        }

        $baseContent['payload']['plan']['departments'] = $baseDepartments;

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

    /** @param array<string, mixed> $baseDepartment @param array<string, mixed> $editedDepartment @return array<string, mixed> */
    private function mergeStudyPlanDepartment(array $baseDepartment, array $editedDepartment, string $selectedTermId): array
    {
        if ($selectedTermId === '') {
            return $this->applyStudyPlanOpensCourseIds($editedDepartment);
        }

        $mergedDepartment = [
            ...$baseDepartment,
            ...$this->withoutKeys($editedDepartment, ['terms']),
        ];
        $editedTerms = collect($this->listOfArrays($editedDepartment['terms'] ?? []))->keyBy(fn (array $term): string => (string) ($term['id'] ?? ''));
        $seenTermIds = [];
        $mergedTerms = array_map(function (array $baseTerm) use ($editedTerms, $selectedTermId, &$seenTermIds): array {
            $termId = (string) ($baseTerm['id'] ?? '');
            $editedTerm = $editedTerms->get($termId);
            $seenTermIds[] = $termId;

            if (! is_array($editedTerm)) {
                return $baseTerm;
            }

            if ($termId === $selectedTermId) {
                return $editedTerm;
            }

            return [
                ...$baseTerm,
                ...$this->withoutKeys($editedTerm, ['courses']),
            ];
        }, $this->listOfArrays($baseDepartment['terms'] ?? []));

        foreach ($editedTerms as $termId => $editedTerm) {
            if (in_array((string) $termId, $seenTermIds, true)) {
                continue;
            }

            $mergedTerms[] = $editedTerm;
        }

        $mergedDepartment['terms'] = $mergedTerms;

        return $this->applyStudyPlanOpensCourseIds($mergedDepartment);
    }

    /** @param array<string, mixed> $plan @return array<string, mixed> */
    private function nestedStudyPlan(array $plan): array
    {
        $rawTerms = $this->listOfArrays($plan['terms'] ?? []);
        $selectedDepartmentId = (string) ($this->data['study_plan_department_id'] ?? '');

        if ($this->termsContainNestedCourses($rawTerms) && $selectedDepartmentId !== '') {
            $departments = array_map(function (array $department) use ($rawTerms, $selectedDepartmentId): array {
                if ((string) ($department['id'] ?? '') === $selectedDepartmentId) {
                    $department['terms'] = $this->normalizeNestedStudyPlanTerms($rawTerms);
                }

                return $department;
            }, $this->listOfArrays($plan['departments'] ?? []));

            unset($plan['terms'], $plan['courses'], $plan['lessons'], $plan['electivePools'], $plan['promotionRequirements']);
            $plan['departments'] = $departments;

            return $plan;
        }

        $terms = collect($rawTerms)->groupBy('departmentId');
        $courses = collect($this->listOfArrays($plan['courses'] ?? []))->groupBy(fn (array $course): string => (string) ($course['departmentId'] ?? '').'|'.(string) ($course['termId'] ?? ''));
        $lessons = collect($this->listOfArrays($plan['lessons'] ?? []))->groupBy('courseId');
        $electivePools = collect($this->listOfArrays($plan['electivePools'] ?? []))->groupBy('departmentId');
        $promotionRequirements = collect($this->listOfArrays($plan['promotionRequirements'] ?? []))->groupBy('departmentId');

        $departments = array_map(function (array $department) use ($terms, $courses, $lessons, $electivePools, $promotionRequirements): array {
            $departmentId = (string) ($department['id'] ?? '');
            $department['electivePools'] = $electivePools->get($departmentId, collect())->map(fn (array $pool): array => $this->withoutKeys($pool, ['departmentId']))->values()->all();
            $department['promotionRequirements'] = $promotionRequirements->get($departmentId, collect())->map(fn (array $requirement): array => $this->withoutKeys($requirement, ['departmentId']))->values()->all();
            $department['terms'] = $terms->get($departmentId, collect())->map(function (array $term) use ($departmentId, $courses, $lessons): array {
                $termId = (string) ($term['id'] ?? '');
                $term['courses'] = $courses->get($departmentId.'|'.$termId, collect())->map(function (array $course) use ($lessons): array {
                    $courseId = (string) ($course['id'] ?? '');
                    $course['prerequisites'] = $this->stringList($course['prerequisites'] ?? []);
                    $course['instructor'] = is_array($course['instructor'] ?? null) ? $course['instructor'] : [];
                    $course['lessons'] = $lessons->get($courseId, collect())->map(fn (array $lesson): array => $this->withoutKeys($lesson, ['courseId']))->values()->all();

                    return $this->withoutKeys($course, ['departmentId', 'termId']);
                })->values()->all();

                return $this->withoutKeys($term, ['departmentId']);
            })->values()->all();

            return $department;
        }, $this->listOfArrays($plan['departments'] ?? []));

        unset($plan['terms'], $plan['courses'], $plan['lessons'], $plan['electivePools'], $plan['promotionRequirements']);
        $plan['departments'] = $departments;

        return $plan;
    }

    /** @param array<int, array<string, mixed>> $terms @return array<int, array<string, mixed>> */
    private function termsWithOpensCourseIds(array $terms, ?string $selectedTermId = null): array
    {
        $openers = [];

        foreach ($terms as $term) {
            foreach ($this->listOfArrays($term['courses'] ?? []) as $course) {
                $courseId = (string) ($course['id'] ?? '');

                if ($courseId === '') {
                    continue;
                }

                foreach ($this->stringList($course['prerequisites'] ?? []) as $prerequisiteId) {
                    $openers[$prerequisiteId][] = $courseId;
                }
            }
        }

        return array_map(function (array $term) use ($openers, $selectedTermId): array {
            $termId = (string) ($term['id'] ?? '');

            if ($selectedTermId !== null && $termId !== $selectedTermId) {
                return $this->withoutKeys($term, ['courses']);
            }

            $term['courses'] = array_map(function (array $course) use ($openers): array {
                $courseId = (string) ($course['id'] ?? '');
                $course['prerequisites'] = $this->stringList($course['prerequisites'] ?? []);
                $course['opensCourseIds'] = array_values(array_unique($openers[$courseId] ?? []));
                $course['lessons'] = $this->listOfArrays($course['lessons'] ?? []);

                return $course;
            }, $this->listOfArrays($term['courses'] ?? []));

            return $term;
        }, $terms);
    }

    /** @param array<int, array<string, mixed>> $terms */
    private function termsContainNestedCourses(array $terms): bool
    {
        foreach ($terms as $term) {
            if (array_key_exists('courses', $term)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $terms @return array<int, array<string, mixed>> */
    private function normalizeNestedStudyPlanTerms(array $terms): array
    {
        return array_map(function (array $term): array {
            $term['courses'] = array_map(function (array $course): array {
                $course['prerequisites'] = $this->stringList($course['prerequisites'] ?? []);
                $course['opensCourseIds'] = $this->stringList($course['opensCourseIds'] ?? []);
                $course['instructor'] = is_array($course['instructor'] ?? null) ? $course['instructor'] : [];
                $course['lessons'] = $this->listOfArrays($course['lessons'] ?? []);

                return $course;
            }, $this->listOfArrays($term['courses'] ?? []));

            return $term;
        }, $terms);
    }

    /** @param array<string, mixed> $department @return array<string, mixed> */
    private function applyStudyPlanOpensCourseIds(array $department): array
    {
        $normalizedTerms = $this->listOfArrays($department['terms'] ?? []);
        $courseLocations = [];

        foreach ($normalizedTerms as $termIndex => $term) {
            foreach ($this->listOfArrays($term['courses'] ?? []) as $courseIndex => $course) {
                $courseId = (string) ($course['id'] ?? '');

                if ($courseId !== '') {
                    $courseLocations[$courseId] = [$termIndex, $courseIndex];
                }
            }
        }

        foreach ($normalizedTerms as $term) {
            foreach ($this->listOfArrays($term['courses'] ?? []) as $course) {
                $sourceId = (string) ($course['id'] ?? '');

                if ($sourceId === '') {
                    continue;
                }

                foreach ($this->stringList($course['opensCourseIds'] ?? []) as $targetId) {
                    if ($targetId === $sourceId || ! isset($courseLocations[$targetId])) {
                        continue;
                    }

                    [$targetTermIndex, $targetCourseIndex] = $courseLocations[$targetId];
                    $prerequisites = $this->stringList($normalizedTerms[$targetTermIndex]['courses'][$targetCourseIndex]['prerequisites'] ?? []);
                    $prerequisites[] = $sourceId;
                    $normalizedTerms[$targetTermIndex]['courses'][$targetCourseIndex]['prerequisites'] = array_values(array_unique($prerequisites));
                }
            }
        }

        $department['terms'] = array_map(function (array $term): array {
            $term['courses'] = array_map(fn (array $course): array => $this->withoutKeys($course, ['opensCourseIds']), $this->listOfArrays($term['courses'] ?? []));

            return $term;
        }, $normalizedTerms);

        return $department;
    }

    /** @return array<int, string> */
    private function stringList(mixed $items): array
    {
        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), is_array($items) ? $items : []), static fn (string $item): bool => $item !== ''));
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
