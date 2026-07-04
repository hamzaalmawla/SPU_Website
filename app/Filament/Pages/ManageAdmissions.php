<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageAdmissions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $slug = 'manage-admissions';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-admissions';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private AdmissionsPageServiceInterface $admissionsPageService;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        AdmissionsPageServiceInterface $admissionsPageService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->admissionsPageService = $admissionsPageService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.admissions');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.admissions');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_admissions');
    }

    public function mount(): void
    {
        $this->loadTarget('admissions.landing');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Admissions Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Subpage')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('admissions_locales')
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
            Action::make('save')
                ->label('Save Draft')
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action(function (): void {
                    $this->save();
                }),
            Action::make('preview_ar')
                ->label('Preview AR')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function (): void {
                    $this->openPreview('ar');
                }),
            Action::make('preview_en')
                ->label('Preview EN')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function (): void {
                    $this->openPreview('en');
                }),
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')
                        ->label('Publish At')
                        ->required()
                        ->minDate(now())
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')
                ->label('Unpublish')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->unpublish();
                }),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertAdmissionsTarget($targetKey);

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey);
        $payload = is_array($draftPayload) ? $draftPayload : $this->admissionsPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_landing' => $targetKey === 'admissions.landing' && is_array($payload['translations']['ar'] ?? null) ? $this->landingFormData($payload['translations']['ar']) : [],
            'en_landing' => $targetKey === 'admissions.landing' && is_array($payload['translations']['en'] ?? null) ? $this->landingFormData($payload['translations']['en']) : [],
            'ar_requirements' => $targetKey === 'admissions.requirements' && is_array($payload['translations']['ar'] ?? null) ? $this->requirementsFormData($payload['translations']['ar']) : [],
            'en_requirements' => $targetKey === 'admissions.requirements' && is_array($payload['translations']['en'] ?? null) ? $this->requirementsFormData($payload['translations']['en']) : [],
            'ar_tuition' => $targetKey === 'admissions.tuition' && is_array($payload['translations']['ar'] ?? null) ? $this->tuitionFormData($payload['translations']['ar']) : [],
            'en_tuition' => $targetKey === 'admissions.tuition' && is_array($payload['translations']['en'] ?? null) ? $this->tuitionFormData($payload['translations']['en']) : [],
            'ar_how_to_apply' => $targetKey === 'admissions.how-to-apply' && is_array($payload['translations']['ar'] ?? null) ? $this->howToApplyFormData($payload['translations']['ar']) : [],
            'en_how_to_apply' => $targetKey === 'admissions.how-to-apply' && is_array($payload['translations']['en'] ?? null) ? $this->howToApplyFormData($payload['translations']['en']) : [],
            'ar_faq' => $targetKey === 'admissions.faq' && is_array($payload['translations']['ar'] ?? null) ? $this->faqFormData($payload['translations']['ar']) : [],
            'en_faq' => $targetKey === 'admissions.faq' && is_array($payload['translations']['en'] ?? null) ? $this->faqFormData($payload['translations']['en']) : [],
            'ar_calendar' => $targetKey === 'admissions.calendar' && is_array($payload['translations']['ar'] ?? null) ? $this->calendarFormData($payload['translations']['ar']) : [],
            'en_calendar' => $targetKey === 'admissions.calendar' && is_array($payload['translations']['en'] ?? null) ? $this->calendarFormData($payload['translations']['en']) : [],
            'ar_documents' => $targetKey === 'admissions.documents' && is_array($payload['translations']['ar'] ?? null) ? $this->documentsFormData($payload['translations']['ar']) : [],
            'en_documents' => $targetKey === 'admissions.documents' && is_array($payload['translations']['en'] ?? null) ? $this->documentsFormData($payload['translations']['en']) : [],
            'ar_transfer' => $targetKey === 'admissions.transfer' && is_array($payload['translations']['ar'] ?? null) ? $this->transferFormData($payload['translations']['ar']) : [],
            'en_transfer' => $targetKey === 'admissions.transfer' && is_array($payload['translations']['en'] ?? null) ? $this->transferFormData($payload['translations']['en']) : [],
        ]);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft(
                $this->currentTargetKey(),
                $this->payloadFromForm($this->currentFormData()),
                (int) $user->id,
                $this->draftVersion,
            );
            $this->draftVersion = $draft->version;

            Notification::make()->title('Admissions draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this admissions target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save admissions draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this admissions target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create admissions preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Admissions target published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish admissions target')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Admissions target scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule admissions target')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'Admissions target unpublished' : 'No published admissions target found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->targetRegistry->forArea('admissions')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'admissions.landing');
        $this->assertAdmissionsTarget($targetKey);

        return $targetKey;
    }

    private function assertAdmissionsTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'admissions') {
            throw new \InvalidArgumentException('Unsupported admissions target.');
        }
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'admissions.landing') {
            return [
                'translations' => [
                    'ar' => $this->landingPayloadFromForm(is_array($state['ar_landing'] ?? null) ? $state['ar_landing'] : []),
                    'en' => $this->landingPayloadFromForm(is_array($state['en_landing'] ?? null) ? $state['en_landing'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.requirements') {
            return [
                'translations' => [
                    'ar' => $this->requirementsPayloadFromForm(is_array($state['ar_requirements'] ?? null) ? $state['ar_requirements'] : []),
                    'en' => $this->requirementsPayloadFromForm(is_array($state['en_requirements'] ?? null) ? $state['en_requirements'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.tuition') {
            return [
                'translations' => [
                    'ar' => $this->tuitionPayloadFromForm(is_array($state['ar_tuition'] ?? null) ? $state['ar_tuition'] : []),
                    'en' => $this->tuitionPayloadFromForm(is_array($state['en_tuition'] ?? null) ? $state['en_tuition'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.how-to-apply') {
            return [
                'translations' => [
                    'ar' => $this->howToApplyPayloadFromForm(is_array($state['ar_how_to_apply'] ?? null) ? $state['ar_how_to_apply'] : []),
                    'en' => $this->howToApplyPayloadFromForm(is_array($state['en_how_to_apply'] ?? null) ? $state['en_how_to_apply'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.faq') {
            return [
                'translations' => [
                    'ar' => $this->faqPayloadFromForm(is_array($state['ar_faq'] ?? null) ? $state['ar_faq'] : []),
                    'en' => $this->faqPayloadFromForm(is_array($state['en_faq'] ?? null) ? $state['en_faq'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.calendar') {
            return [
                'translations' => [
                    'ar' => $this->calendarPayloadFromForm(is_array($state['ar_calendar'] ?? null) ? $state['ar_calendar'] : []),
                    'en' => $this->calendarPayloadFromForm(is_array($state['en_calendar'] ?? null) ? $state['en_calendar'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.documents') {
            return [
                'translations' => [
                    'ar' => $this->documentsPayloadFromForm(is_array($state['ar_documents'] ?? null) ? $state['ar_documents'] : []),
                    'en' => $this->documentsPayloadFromForm(is_array($state['en_documents'] ?? null) ? $state['en_documents'] : []),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.transfer') {
            return [
                'translations' => [
                    'ar' => $this->transferPayloadFromForm(is_array($state['ar_transfer'] ?? null) ? $state['ar_transfer'] : []),
                    'en' => $this->transferPayloadFromForm(is_array($state['en_transfer'] ?? null) ? $state['en_transfer'] : []),
                ],
            ];
        }

        throw new \InvalidArgumentException('This admissions subpage form will be structured next. Select the Admissions hub page for now.');
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->targetKeyForSchema() === 'admissions.landing') {
            return $this->landingFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.requirements') {
            return $this->requirementsFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.tuition') {
            return $this->tuitionFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.how-to-apply') {
            return $this->howToApplyFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.faq') {
            return $this->faqFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.calendar') {
            return $this->calendarFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.documents') {
            return $this->documentsFields($locale);
        }

        if ($this->targetKeyForSchema() === 'admissions.transfer') {
            return $this->transferFields($locale);
        }

        return [
            Section::make('Subpage Schema Pending')
                ->description('We are converting Admissions one page at a time. The hub page is editable now; this subpage will get its own correct structured form next.')
                ->schema([
                    TextInput::make($locale.'_subpage_pending')
                        ->label('Status')
                        ->default('Structured form pending for this subpage')
                        ->disabled(),
                ]),
        ];
    }

    /** @return array<int, Section> */
    private function transferFields(string $locale): array
    {
        $prefix = $locale.'_transfer';

        return [
            Section::make('Hero and Labels')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(160),
                TextInput::make($prefix.'.apply_label')->label('Apply Button')->required()->maxLength(120),
                TextInput::make($prefix.'.apply_url')->label('Apply URL')->required()->maxLength(255),
                TextInput::make($prefix.'.request_info_label')->label('Request Info Button')->required()->maxLength(120),
                TextInput::make($prefix.'.request_info_url')->label('Request Info URL')->required()->maxLength(255),
                TextInput::make($prefix.'.required_label')->label('Required Label')->required()->maxLength(80),
                TextInput::make($prefix.'.optional_label')->label('Optional Label')->required()->maxLength(120),
                TextInput::make($prefix.'.notes_title')->label('Notes Title')->required()->maxLength(180),
                Textarea::make($prefix.'.notes_desc')->label('Notes Description')->required()->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make('Transfer and International Tabs')->schema([
                Repeater::make($prefix.'.tabs')
                    ->label('Applicant Tabs')
                    ->schema([
                        TextInput::make('id')->label('Tab ID')->required()->maxLength(80),
                        TextInput::make('label')->label('Tab Label')->required()->maxLength(160),
                        TextInput::make('policiesTitle')->label('Policies Title')->required()->maxLength(180),
                        Repeater::make('policies')
                            ->label('Policies')
                            ->schema([
                                MediaPicker::icon('icon', 'Icon', true),
                                TextInput::make('title')->required()->maxLength(180),
                                Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('documentsTitle')->label('Documents Title')->required()->maxLength(180),
                        Repeater::make('documents')
                            ->label('Documents')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(180),
                                Toggle::make('required')->label('Required'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('processTitle')->label('Process Title')->required()->maxLength(180),
                        Repeater::make('steps')
                            ->label('Process Steps')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(180),
                                Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
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
    private function documentsFields(string $locale): array
    {
        $prefix = $locale.'_documents';

        return [
            Section::make('Hero and Labels')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(120),
                TextInput::make($prefix.'.apply_label')->label('Apply Button')->required()->maxLength(120),
                TextInput::make($prefix.'.apply_url')->label('Apply URL')->required()->maxLength(255),
                TextInput::make($prefix.'.request_info_label')->label('Request Info Button')->required()->maxLength(120),
                TextInput::make($prefix.'.request_info_url')->label('Request Info URL')->required()->maxLength(255),
                TextInput::make($prefix.'.required_label')->label('Required Label')->required()->maxLength(80),
                TextInput::make($prefix.'.optional_label')->label('Optional Label')->required()->maxLength(80),
                TextInput::make($prefix.'.download_label')->label('Download Button Label')->required()->maxLength(120),
                TextInput::make($prefix.'.download_all_label')->label('Download All Title')->required()->maxLength(180),
                Textarea::make($prefix.'.download_all_desc')->label('Download All Description')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.last_reviewed_label')->label('Last Reviewed Label')->required()->maxLength(120),
                TextInput::make($prefix.'.last_reviewed')->label('Last Reviewed Value')->required()->maxLength(120),
            ])->columns(2),

            Section::make('Admission Checklist')->schema([
                TextInput::make($prefix.'.checklist_label')->label('Tab Label')->required()->maxLength(160),
                Repeater::make($prefix.'.checklist_subtabs')
                    ->label('Applicant Checklists')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        MediaPicker::document('download_href', 'Download File', true),
                        TextInput::make('download_size')->label('Download Size')->required()->maxLength(80),
                        Repeater::make('items')
                            ->label('Documents')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(180),
                                Textarea::make('note')->rows(2)->columnSpanFull(),
                                Toggle::make('required')->label('Required'),
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

            Section::make('University Documents')->schema([
                TextInput::make($prefix.'.granted_label')->label('Tab Label')->required()->maxLength(160),
                Textarea::make($prefix.'.granted_intro')->label('Intro')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.granted_items')
                    ->label('Documents')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('availability')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Study System and GPA')->schema([
                TextInput::make($prefix.'.study_label')->label('Tab Label')->required()->maxLength(160),
                Textarea::make($prefix.'.study_intro')->label('Intro')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.scale_title')->label('Scale Title')->required()->maxLength(160),
                Repeater::make($prefix.'.scale_headers')
                    ->label('Scale Headers')
                    ->schema([
                        TextInput::make('key')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.scale_rows')
                    ->label('Scale Rows')
                    ->schema([
                        TextInput::make('percentage')->required()->maxLength(80),
                        TextInput::make('gpa')->required()->maxLength(80),
                        TextInput::make('grade')->required()->maxLength(80),
                        TextInput::make('descriptor')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.study_notes')
                    ->label('Study Notes')
                    ->schema([
                        Textarea::make('note')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Academic Warnings')->schema([
                TextInput::make($prefix.'.warnings_label')->label('Tab Label')->required()->maxLength(160),
                Textarea::make($prefix.'.warnings_intro')->label('Intro')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.levels_title')->label('Levels Title')->required()->maxLength(160),
                Repeater::make($prefix.'.levels')
                    ->label('Warning Levels')
                    ->schema([
                        TextInput::make('level')->required()->maxLength(160),
                        TextInput::make('threshold')->required()->maxLength(160),
                        Textarea::make('consequences')->required()->rows(2)->columnSpanFull(),
                        Textarea::make('recovery')->required()->rows(2)->columnSpanFull(),
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
    private function calendarFields(string $locale): array
    {
        $prefix = $locale.'_calendar';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(120),
            ])->columns(2),

            Section::make('Highlights and Deadlines')->schema([
                Repeater::make($prefix.'.stat_cards')
                    ->label('Stat Cards')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                TextInput::make($prefix.'.deadlines_title')->label('Deadlines Title')->required()->maxLength(180),
                Repeater::make($prefix.'.deadlines')
                    ->label('Deadlines')
                    ->schema([
                        TextInput::make('type')->required()->maxLength(120),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('date')->required()->maxLength(120),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Academic Timeline')->schema([
                TextInput::make($prefix.'.timeline_title')->label('Timeline Title')->required()->maxLength(180),
                Repeater::make($prefix.'.semesters')
                    ->label('Semesters')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Repeater::make('events')
                            ->label('Events')
                            ->schema([
                                TextInput::make('date')->required()->maxLength(120),
                                TextInput::make('title')->required()->maxLength(160),
                                Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Download and Notice')->schema([
                TextInput::make($prefix.'.download_title')->label('Download Title')->required()->maxLength(180),
                Textarea::make($prefix.'.download_desc')->label('Download Description')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.download_button')->label('Download Button')->required()->maxLength(120),
                MediaPicker::document($prefix.'.download_href', 'Download File', true),
                TextInput::make($prefix.'.notice_title')->label('Notice Title')->required()->maxLength(180),
                Textarea::make($prefix.'.notice_desc')->label('Notice Description')->required()->rows(3)->columnSpanFull(),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function faqFields(string $locale): array
    {
        $prefix = $locale.'_faq';

        return [
            Section::make('Hero and Search')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(120),
                TextInput::make($prefix.'.search_label')->label('Search Label')->required()->maxLength(160),
                TextInput::make($prefix.'.search_placeholder')->label('Search Placeholder')->required()->maxLength(180),
                TextInput::make($prefix.'.empty_state')->label('Empty State')->required()->maxLength(180),
            ])->columns(2),

            Section::make('FAQ Groups')->schema([
                Repeater::make($prefix.'.sections')
                    ->label('FAQ Groups')
                    ->schema([
                        TextInput::make('id')->label('Group ID')->required()->maxLength(100),
                        TextInput::make('title')->label('Group Title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        Repeater::make('items')
                            ->label('Questions')
                            ->schema([
                                TextInput::make('q')->label('Question')->required()->maxLength(240),
                                Textarea::make('a')->label('Answer')->required()->rows(3)->columnSpanFull(),
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
    private function howToApplyFields(string $locale): array
    {
        $prefix = $locale.'_how_to_apply';

        return [
            Section::make('Hero and Intro')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(120),
                TextInput::make($prefix.'.hero_title')->label('Intro Title')->required()->maxLength(180),
                Textarea::make($prefix.'.hero_desc')->label('Intro Description')->required()->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make('Feature Cards')->schema([
                Repeater::make($prefix.'.feature_cards')
                    ->label('Feature Cards')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Step-by-Step Guide')->schema([
                TextInput::make($prefix.'.guide_title')->label('Guide Title')->required()->maxLength(180),
                Repeater::make($prefix.'.steps')
                    ->label('Steps')
                    ->schema([
                        TextInput::make('number')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('cta')->label('CTA Label')->required()->maxLength(120),
                        TextInput::make('href')->label('CTA URL')->required()->maxLength(255),
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
    private function tuitionFields(string $locale): array
    {
        $prefix = $locale.'_tuition';

        return [
            Section::make('Hero and Filters')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(120),
                TextInput::make($prefix.'.faculty_filter_label')->label('Faculty Filter Label')->required()->maxLength(120),
                TextInput::make($prefix.'.student_type_filter_label')->label('Student Type Filter Label')->required()->maxLength(120),
                TextInput::make($prefix.'.empty_state')->label('Empty State')->required()->maxLength(180),
            ])->columns(2),

            Section::make('Fee Table')->schema([
                TextInput::make($prefix.'.overview_title')->label('Overview Title')->required()->maxLength(180),
                Repeater::make($prefix.'.table_headers')
                    ->label('Table Headers')
                    ->schema([
                        TextInput::make('key')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.fee_rows')
                    ->label('Fee Rows')
                    ->schema([
                        TextInput::make('faculty')->required()->maxLength(160),
                        TextInput::make('type')->required()->maxLength(120),
                        TextInput::make('tuitionFee')->label('Tuition Fee')->required()->maxLength(120),
                        TextInput::make('registrationFee')->label('Registration Fee')->required()->maxLength(120),
                        TextInput::make('additionalFees')->label('Additional Fees')->required()->maxLength(160),
                        Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Payment Methods')->schema([
                TextInput::make($prefix.'.payment_title')->label('Payment Title')->required()->maxLength(180),
                Repeater::make($prefix.'.methods')
                    ->label('Payment Methods')
                    ->schema([
                        MediaPicker::icon('icon', 'Icon', true),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        Repeater::make('details')
                            ->label('Details')
                            ->schema([
                                TextInput::make('label')->required()->maxLength(120),
                                TextInput::make('value')->required()->maxLength(180),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('cta')->label('CTA Label')->maxLength(120),
                        TextInput::make('ctaUrl')->label('CTA URL')->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Financial Notes')->schema([
                TextInput::make($prefix.'.notes_title')->label('Notes Title')->required()->maxLength(180),
                Repeater::make($prefix.'.notes')
                    ->label('Notes')
                    ->schema([
                        Textarea::make('note')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @return array<int, Section> */
    private function requirementsFields(string $locale): array
    {
        $prefix = $locale.'_requirements';

        return [
            Section::make('Hero and Labels')->schema([
                TextInput::make($prefix.'.title')->label('Page Title')->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', 'Hero Image', true),
                TextInput::make($prefix.'.breadcrumb_home')->label('Breadcrumb Home')->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label('Breadcrumb Parent')->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label('Breadcrumb Current')->required()->maxLength(120),
                TextInput::make($prefix.'.eligibility_title')->label('Eligibility Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.documents_title')->label('Documents Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.ready_title')->label('Checklist Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.notes_title')->label('Notes Section Title')->required()->maxLength(160),
                TextInput::make($prefix.'.required_label')->label('Required Label')->required()->maxLength(80),
                TextInput::make($prefix.'.optional_label')->label('Optional Label')->required()->maxLength(120),
            ])->columns(2),

            Section::make('Applicant Tabs')->schema([
                Repeater::make($prefix.'.tabs')
                    ->label('Tabs')
                    ->schema([
                        TextInput::make('id')->label('Tab ID')->required()->maxLength(80),
                        TextInput::make('label')->label('Tab Label')->required()->maxLength(120),
                        Repeater::make('criteria')
                            ->label('Eligibility Criteria')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(180),
                                Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Repeater::make('documents')
                            ->label('Required Documents')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(180),
                                Toggle::make('required')->label('Required'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Repeater::make('checklist')
                            ->label('Ready Checklist')
                            ->schema([
                                TextInput::make('item')->required()->maxLength(180),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Textarea::make('note')->label('Important Note')->required()->rows(3)->columnSpanFull(),
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
    private function landingFields(string $locale): array
    {
        $prefix = $locale.'_landing';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero_title')->label('Title')->required()->maxLength(180),
                Textarea::make($prefix.'.hero_summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                TextInput::make($prefix.'.hero_cta_primary')->label('Primary CTA')->required()->maxLength(80),
                TextInput::make($prefix.'.hero_primary_url')->label('Primary URL')->required()->maxLength(255),
                TextInput::make($prefix.'.hero_cta_secondary')->label('Secondary CTA')->required()->maxLength(80),
                TextInput::make($prefix.'.hero_secondary_url')->label('Secondary URL')->required()->maxLength(255),
                TextInput::make($prefix.'.hero_badge_label')->label('Badge Label')->required()->maxLength(120),
                TextInput::make($prefix.'.hero_badge_value')->label('Badge Value')->required()->maxLength(120),
                MediaPicker::image($prefix.'.hero_campus_image', 'Campus Image', true),
                MediaPicker::image($prefix.'.hero_students_image', 'Students Image', true),
                Repeater::make($prefix.'.hero_checklist_items')
                    ->label('Checklist Items')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Trust Bar')->schema([
                Repeater::make($prefix.'.trust_bar')
                    ->label('Trust Items')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible(),
            ]),

            Section::make('Admissions Journey')->schema([
                TextInput::make($prefix.'.journey_eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.journey_title')->label('Title')->required()->maxLength(180),
                Repeater::make($prefix.'.journey_steps')
                    ->label('Journey Steps')
                    ->schema([
                        TextInput::make('number')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                        Toggle::make('active')->label('Active'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Timeline')->schema([
                TextInput::make($prefix.'.timeline_eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.timeline_title')->label('Title')->required()->maxLength(180),
                Textarea::make($prefix.'.timeline_summary')->label('Summary')->required()->rows(3)->columnSpanFull(),
                TextInput::make($prefix.'.timeline_primary_deadline')->label('Primary Deadline')->required()->maxLength(120),
                TextInput::make($prefix.'.timeline_primary_deadline_label')->label('Deadline Label')->required()->maxLength(120),
                Textarea::make($prefix.'.timeline_primary_deadline_desc')->label('Deadline Description')->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.timeline_image', 'Timeline Image', true),
                Repeater::make($prefix.'.timeline_phases')
                    ->label('Timeline Phases')
                    ->schema([
                        TextInput::make('label')->required()->maxLength(80),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('date')->required()->maxLength(120),
                        Toggle::make('active')->label('Active'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Resources')->schema([
                TextInput::make($prefix.'.resources_eyebrow')->label('Eyebrow')->required()->maxLength(120),
                TextInput::make($prefix.'.resources_title')->label('Title')->required()->maxLength(180),
                Textarea::make($prefix.'.resources_subtitle')->label('Subtitle')->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.resource_cards')
                    ->label('Resource Cards')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', 'Icon', true),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('link')->maxLength(120),
                        TextInput::make('slug')->required()->maxLength(80),
                        Toggle::make('active')->label('Featured'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),
        ];
    }

    /** @return array<string, mixed> */
    private function landingFormData(array $payload): array
    {
        $hero = is_array($payload['hero'] ?? null) ? $payload['hero'] : [];
        $journey = is_array($payload['journey'] ?? null) ? $payload['journey'] : [];
        $timeline = is_array($payload['timeline'] ?? null) ? $payload['timeline'] : [];
        $resources = is_array($payload['resources'] ?? null) ? $payload['resources'] : [];
        $images = is_array($hero['images'] ?? null) ? $hero['images'] : [];

        return [
            'hero_title' => $this->stringValue($hero, 'title'),
            'hero_summary' => $this->stringValue($hero, 'summary'),
            'hero_cta_primary' => $this->stringValue($hero, 'ctaPrimary'),
            'hero_primary_url' => $this->stringValue($hero, 'primaryUrl'),
            'hero_cta_secondary' => $this->stringValue($hero, 'ctaSecondary'),
            'hero_secondary_url' => $this->stringValue($hero, 'secondaryUrl'),
            'hero_badge_label' => $this->stringValue($hero, 'badgeLabel'),
            'hero_badge_value' => $this->stringValue($hero, 'badgeValue'),
            'hero_campus_image' => $this->stringValue($images, 'campus'),
            'hero_students_image' => $this->stringValue($images, 'students'),
            'hero_checklist_items' => array_values(array_filter(is_array($hero['checklistItems'] ?? null) ? $hero['checklistItems'] : [], static fn (mixed $item): bool => is_array($item))),
            'trust_bar' => array_values(array_filter(is_array($payload['trustBar'] ?? null) ? $payload['trustBar'] : [], static fn (mixed $item): bool => is_array($item))),
            'journey_eyebrow' => $this->stringValue($journey, 'eyebrow'),
            'journey_title' => $this->stringValue($journey, 'title'),
            'journey_steps' => array_values(array_filter(is_array($journey['steps'] ?? null) ? $journey['steps'] : [], static fn (mixed $item): bool => is_array($item))),
            'timeline_eyebrow' => $this->stringValue($timeline, 'eyebrow'),
            'timeline_title' => $this->stringValue($timeline, 'title'),
            'timeline_summary' => $this->stringValue($timeline, 'summary'),
            'timeline_primary_deadline' => $this->stringValue($timeline, 'primaryDeadline'),
            'timeline_primary_deadline_label' => $this->stringValue($timeline, 'primaryDeadlineLabel'),
            'timeline_primary_deadline_desc' => $this->stringValue($timeline, 'primaryDeadlineDesc'),
            'timeline_image' => $this->stringValue($timeline, 'image'),
            'timeline_phases' => array_values(array_filter(is_array($timeline['phases'] ?? null) ? $timeline['phases'] : [], static fn (mixed $item): bool => is_array($item))),
            'resources_eyebrow' => $this->stringValue($resources, 'eyebrow'),
            'resources_title' => $this->stringValue($resources, 'title'),
            'resources_subtitle' => $this->stringValue($resources, 'subtitle'),
            'resource_cards' => array_values(array_filter(is_array($resources['cards'] ?? null) ? $resources['cards'] : [], static fn (mixed $item): bool => is_array($item))),
        ];
    }

    /** @return array<string, mixed> */
    private function landingPayloadFromForm(array $data): array
    {
        return [
            'hero' => [
                'title' => (string) ($data['hero_title'] ?? ''),
                'summary' => (string) ($data['hero_summary'] ?? ''),
                'ctaPrimary' => (string) ($data['hero_cta_primary'] ?? ''),
                'primaryUrl' => (string) ($data['hero_primary_url'] ?? ''),
                'ctaSecondary' => (string) ($data['hero_cta_secondary'] ?? ''),
                'secondaryUrl' => (string) ($data['hero_secondary_url'] ?? ''),
                'badgeLabel' => (string) ($data['hero_badge_label'] ?? ''),
                'badgeValue' => (string) ($data['hero_badge_value'] ?? ''),
                'checklistItems' => $this->listValue($data, 'hero_checklist_items'),
                'images' => [
                    'campus' => (string) ($data['hero_campus_image'] ?? ''),
                    'students' => (string) ($data['hero_students_image'] ?? ''),
                ],
            ],
            'trustBar' => $this->listValue($data, 'trust_bar'),
            'journey' => [
                'eyebrow' => (string) ($data['journey_eyebrow'] ?? ''),
                'title' => (string) ($data['journey_title'] ?? ''),
                'steps' => $this->listValue($data, 'journey_steps'),
            ],
            'timeline' => [
                'eyebrow' => (string) ($data['timeline_eyebrow'] ?? ''),
                'title' => (string) ($data['timeline_title'] ?? ''),
                'summary' => (string) ($data['timeline_summary'] ?? ''),
                'primaryDeadline' => (string) ($data['timeline_primary_deadline'] ?? ''),
                'primaryDeadlineLabel' => (string) ($data['timeline_primary_deadline_label'] ?? ''),
                'primaryDeadlineDesc' => (string) ($data['timeline_primary_deadline_desc'] ?? ''),
                'image' => (string) ($data['timeline_image'] ?? ''),
                'phases' => $this->listValue($data, 'timeline_phases'),
            ],
            'resources' => [
                'eyebrow' => (string) ($data['resources_eyebrow'] ?? ''),
                'title' => (string) ($data['resources_title'] ?? ''),
                'subtitle' => (string) ($data['resources_subtitle'] ?? ''),
                'cards' => $this->listValue($data, 'resource_cards'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function requirementsFormData(array $payload): array
    {
        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'eligibility_title' => $this->stringValue($payload, 'eligibilityTitle'),
            'documents_title' => $this->stringValue($payload, 'documentsTitle'),
            'ready_title' => $this->stringValue($payload, 'readyTitle'),
            'notes_title' => $this->stringValue($payload, 'notesTitle'),
            'required_label' => $this->stringValue($payload, 'requiredLabel'),
            'optional_label' => $this->stringValue($payload, 'optionalLabel'),
            'tabs' => collect(is_array($payload['tabs'] ?? null) ? $payload['tabs'] : [])
                ->filter(static fn (mixed $tab): bool => is_array($tab))
                ->map(fn (array $tab): array => [
                    'id' => $this->stringValue($tab, 'id'),
                    'label' => $this->stringValue($tab, 'label'),
                    'criteria' => $this->listValue($tab, 'criteria'),
                    'documents' => $this->listValue($tab, 'documents'),
                    'checklist' => collect(is_array($tab['checklist'] ?? null) ? $tab['checklist'] : [])
                        ->map(static fn (mixed $item): array => ['item' => is_string($item) || is_numeric($item) ? (string) $item : ''])
                        ->filter(static fn (array $item): bool => $item['item'] !== '')
                        ->values()
                        ->all(),
                    'note' => $this->stringValue($tab, 'note'),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function requirementsPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'eligibilityTitle' => (string) ($data['eligibility_title'] ?? ''),
            'documentsTitle' => (string) ($data['documents_title'] ?? ''),
            'readyTitle' => (string) ($data['ready_title'] ?? ''),
            'notesTitle' => (string) ($data['notes_title'] ?? ''),
            'requiredLabel' => (string) ($data['required_label'] ?? ''),
            'optionalLabel' => (string) ($data['optional_label'] ?? ''),
            'tabs' => collect($this->listValue($data, 'tabs'))
                ->map(fn (array $tab): array => [
                    'id' => (string) ($tab['id'] ?? ''),
                    'label' => (string) ($tab['label'] ?? ''),
                    'criteria' => collect($this->listValue($tab, 'criteria'))
                        ->map(static fn (array $criterion): array => [
                            'title' => (string) ($criterion['title'] ?? ''),
                            'desc' => (string) ($criterion['desc'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                    'documents' => collect($this->listValue($tab, 'documents'))
                        ->map(static fn (array $document): array => [
                            'name' => (string) ($document['name'] ?? ''),
                            'required' => (bool) ($document['required'] ?? false),
                        ])
                        ->values()
                        ->all(),
                    'checklist' => collect($this->listValue($tab, 'checklist'))
                        ->map(static fn (array $item): string => (string) ($item['item'] ?? ''))
                        ->filter(static fn (string $item): bool => $item !== '')
                        ->values()
                        ->all(),
                    'note' => (string) ($tab['note'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function tuitionFormData(array $payload): array
    {
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];

        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'faculty_filter_label' => $this->stringValue($filters, 'facultyLabel'),
            'student_type_filter_label' => $this->stringValue($filters, 'studentTypeLabel'),
            'overview_title' => $this->stringValue($payload, 'overviewTitle'),
            'table_headers' => $this->listValue($payload, 'tableHeaders'),
            'fee_rows' => $this->listValue($payload, 'feeRows'),
            'empty_state' => $this->stringValue($payload, 'emptyState'),
            'payment_title' => $this->stringValue($payload, 'paymentTitle'),
            'methods' => $this->listValue($payload, 'methods'),
            'notes_title' => $this->stringValue($payload, 'notesTitle'),
            'notes' => collect(is_array($payload['notes'] ?? null) ? $payload['notes'] : [])
                ->map(static fn (mixed $note): array => ['note' => is_string($note) || is_numeric($note) ? (string) $note : ''])
                ->filter(static fn (array $note): bool => $note['note'] !== '')
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function tuitionPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'filters' => [
                'facultyLabel' => (string) ($data['faculty_filter_label'] ?? ''),
                'studentTypeLabel' => (string) ($data['student_type_filter_label'] ?? ''),
            ],
            'overviewTitle' => (string) ($data['overview_title'] ?? ''),
            'tableHeaders' => collect($this->listValue($data, 'table_headers'))
                ->map(static fn (array $header): array => [
                    'key' => (string) ($header['key'] ?? ''),
                    'label' => (string) ($header['label'] ?? ''),
                ])
                ->values()
                ->all(),
            'feeRows' => collect($this->listValue($data, 'fee_rows'))
                ->map(static fn (array $row): array => [
                    'faculty' => (string) ($row['faculty'] ?? ''),
                    'type' => (string) ($row['type'] ?? ''),
                    'tuitionFee' => (string) ($row['tuitionFee'] ?? ''),
                    'registrationFee' => (string) ($row['registrationFee'] ?? ''),
                    'additionalFees' => (string) ($row['additionalFees'] ?? ''),
                    'notes' => (string) ($row['notes'] ?? ''),
                ])
                ->values()
                ->all(),
            'emptyState' => (string) ($data['empty_state'] ?? ''),
            'paymentTitle' => (string) ($data['payment_title'] ?? ''),
            'methods' => collect($this->listValue($data, 'methods'))
                ->map(fn (array $method): array => array_filter([
                    'icon' => (string) ($method['icon'] ?? ''),
                    'title' => (string) ($method['title'] ?? ''),
                    'desc' => (string) ($method['desc'] ?? ''),
                    'details' => collect($this->listValue($method, 'details'))
                        ->map(static fn (array $detail): array => [
                            'label' => (string) ($detail['label'] ?? ''),
                            'value' => (string) ($detail['value'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                    'cta' => (string) ($method['cta'] ?? ''),
                    'ctaUrl' => (string) ($method['ctaUrl'] ?? ''),
                ], static fn (mixed $value): bool => $value !== '' && $value !== []))
                ->values()
                ->all(),
            'notesTitle' => (string) ($data['notes_title'] ?? ''),
            'notes' => collect($this->listValue($data, 'notes'))
                ->map(static fn (array $note): string => (string) ($note['note'] ?? ''))
                ->filter(static fn (string $note): bool => $note !== '')
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function howToApplyFormData(array $payload): array
    {
        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'hero_title' => $this->stringValue($payload, 'heroTitle'),
            'hero_desc' => $this->stringValue($payload, 'heroDesc'),
            'feature_cards' => $this->listValue($payload, 'featureCards'),
            'guide_title' => $this->stringValue($payload, 'guideTitle'),
            'steps' => $this->listValue($payload, 'steps'),
        ];
    }

    /** @return array<string, mixed> */
    private function howToApplyPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'heroTitle' => (string) ($data['hero_title'] ?? ''),
            'heroDesc' => (string) ($data['hero_desc'] ?? ''),
            'featureCards' => collect($this->listValue($data, 'feature_cards'))
                ->map(static fn (array $card): array => [
                    'title' => (string) ($card['title'] ?? ''),
                    'desc' => (string) ($card['desc'] ?? ''),
                    'icon' => (string) ($card['icon'] ?? ''),
                ])
                ->values()
                ->all(),
            'guideTitle' => (string) ($data['guide_title'] ?? ''),
            'steps' => collect($this->listValue($data, 'steps'))
                ->map(static fn (array $step): array => [
                    'number' => (string) ($step['number'] ?? ''),
                    'title' => (string) ($step['title'] ?? ''),
                    'desc' => (string) ($step['desc'] ?? ''),
                    'cta' => (string) ($step['cta'] ?? ''),
                    'href' => (string) ($step['href'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function faqFormData(array $payload): array
    {
        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'search_label' => $this->stringValue($payload, 'searchLabel'),
            'search_placeholder' => $this->stringValue($payload, 'searchPlaceholder'),
            'empty_state' => $this->stringValue($payload, 'emptyState'),
            'sections' => $this->listValue($payload, 'sections'),
        ];
    }

    /** @return array<string, mixed> */
    private function faqPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'searchLabel' => (string) ($data['search_label'] ?? ''),
            'searchPlaceholder' => (string) ($data['search_placeholder'] ?? ''),
            'emptyState' => (string) ($data['empty_state'] ?? ''),
            'sections' => collect($this->listValue($data, 'sections'))
                ->map(fn (array $section): array => [
                    'id' => (string) ($section['id'] ?? ''),
                    'title' => (string) ($section['title'] ?? ''),
                    'icon' => (string) ($section['icon'] ?? ''),
                    'items' => collect($this->listValue($section, 'items'))
                        ->map(static fn (array $item): array => [
                            'q' => (string) ($item['q'] ?? ''),
                            'a' => (string) ($item['a'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function calendarFormData(array $payload): array
    {
        $download = is_array($payload['download'] ?? null) ? $payload['download'] : [];
        $notice = is_array($payload['notice'] ?? null) ? $payload['notice'] : [];

        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'stat_cards' => $this->listValue($payload, 'statCards'),
            'deadlines_title' => $this->stringValue($payload, 'deadlinesTitle'),
            'deadlines' => $this->listValue($payload, 'deadlines'),
            'timeline_title' => $this->stringValue($payload, 'timelineTitle'),
            'semesters' => $this->listValue($payload, 'semesters'),
            'download_title' => $this->stringValue($download, 'title'),
            'download_desc' => $this->stringValue($download, 'desc'),
            'download_button' => $this->stringValue($download, 'button'),
            'download_href' => $this->stringValue($download, 'href'),
            'notice_title' => $this->stringValue($notice, 'title'),
            'notice_desc' => $this->stringValue($notice, 'desc'),
        ];
    }

    /** @return array<string, mixed> */
    private function calendarPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'statCards' => collect($this->listValue($data, 'stat_cards'))
                ->map(static fn (array $card): array => [
                    'title' => (string) ($card['title'] ?? ''),
                    'desc' => (string) ($card['desc'] ?? ''),
                    'icon' => (string) ($card['icon'] ?? ''),
                ])
                ->values()
                ->all(),
            'deadlinesTitle' => (string) ($data['deadlines_title'] ?? ''),
            'deadlines' => collect($this->listValue($data, 'deadlines'))
                ->map(static fn (array $deadline): array => [
                    'type' => (string) ($deadline['type'] ?? ''),
                    'title' => (string) ($deadline['title'] ?? ''),
                    'date' => (string) ($deadline['date'] ?? ''),
                ])
                ->values()
                ->all(),
            'timelineTitle' => (string) ($data['timeline_title'] ?? ''),
            'semesters' => collect($this->listValue($data, 'semesters'))
                ->map(fn (array $semester): array => [
                    'title' => (string) ($semester['title'] ?? ''),
                    'events' => collect($this->listValue($semester, 'events'))
                        ->map(static fn (array $event): array => [
                            'date' => (string) ($event['date'] ?? ''),
                            'title' => (string) ($event['title'] ?? ''),
                            'desc' => (string) ($event['desc'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'download' => [
                'title' => (string) ($data['download_title'] ?? ''),
                'desc' => (string) ($data['download_desc'] ?? ''),
                'button' => (string) ($data['download_button'] ?? ''),
                'href' => (string) ($data['download_href'] ?? ''),
            ],
            'notice' => [
                'title' => (string) ($data['notice_title'] ?? ''),
                'desc' => (string) ($data['notice_desc'] ?? ''),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function documentsFormData(array $payload): array
    {
        $tabs = collect(is_array($payload['tabs'] ?? null) ? $payload['tabs'] : []);
        $checklist = $tabs->firstWhere('id', 'checklist') ?? [];
        $granted = $tabs->firstWhere('id', 'granted') ?? [];
        $study = $tabs->firstWhere('id', 'studySystem') ?? [];
        $warnings = $tabs->firstWhere('id', 'warnings') ?? [];

        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'apply_label' => $this->stringValue($payload, 'applyLabel'),
            'apply_url' => $this->stringValue($payload, 'applyUrl'),
            'request_info_label' => $this->stringValue($payload, 'requestInfoLabel'),
            'request_info_url' => $this->stringValue($payload, 'requestInfoUrl'),
            'required_label' => $this->stringValue($payload, 'requiredLabel'),
            'optional_label' => $this->stringValue($payload, 'optionalLabel'),
            'download_label' => $this->stringValue($payload, 'downloadLabel'),
            'download_all_label' => $this->stringValue($payload, 'downloadAllLabel'),
            'download_all_desc' => $this->stringValue($payload, 'downloadAllDesc'),
            'last_reviewed_label' => $this->stringValue($payload, 'lastReviewedLabel'),
            'last_reviewed' => $this->stringValue($payload, 'lastReviewed'),
            'checklist_label' => $this->stringValue(is_array($checklist) ? $checklist : [], 'label'),
            'checklist_subtabs' => collect(is_array($checklist['subTabs'] ?? null) ? $checklist['subTabs'] : [])
                ->map(fn (array $subTab): array => [
                    'id' => $this->stringValue($subTab, 'id'),
                    'label' => $this->stringValue($subTab, 'label'),
                    'desc' => $this->stringValue($subTab, 'desc'),
                    'download_href' => $this->stringValue(is_array($subTab['download'] ?? null) ? $subTab['download'] : [], 'href'),
                    'download_size' => $this->stringValue(is_array($subTab['download'] ?? null) ? $subTab['download'] : [], 'size'),
                    'items' => $this->listValue($subTab, 'items'),
                ])
                ->values()
                ->all(),
            'granted_label' => $this->stringValue(is_array($granted) ? $granted : [], 'label'),
            'granted_intro' => $this->stringValue(is_array($granted) ? $granted : [], 'intro'),
            'granted_items' => $this->listValue(is_array($granted) ? $granted : [], 'items'),
            'study_label' => $this->stringValue(is_array($study) ? $study : [], 'label'),
            'study_intro' => $this->stringValue(is_array($study) ? $study : [], 'intro'),
            'scale_title' => $this->stringValue(is_array($study) ? $study : [], 'scaleTitle'),
            'scale_headers' => $this->listValue(is_array($study) ? $study : [], 'scaleHeaders'),
            'scale_rows' => $this->listValue(is_array($study) ? $study : [], 'scaleRows'),
            'study_notes' => collect(is_array($study['notes'] ?? null) ? $study['notes'] : [])
                ->map(static fn (mixed $note): array => ['note' => is_string($note) || is_numeric($note) ? (string) $note : ''])
                ->filter(static fn (array $note): bool => $note['note'] !== '')
                ->values()
                ->all(),
            'warnings_label' => $this->stringValue(is_array($warnings) ? $warnings : [], 'label'),
            'warnings_intro' => $this->stringValue(is_array($warnings) ? $warnings : [], 'intro'),
            'levels_title' => $this->stringValue(is_array($warnings) ? $warnings : [], 'levelsTitle'),
            'levels' => $this->listValue(is_array($warnings) ? $warnings : [], 'levels'),
        ];
    }

    /** @return array<string, mixed> */
    private function documentsPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'lastReviewed' => (string) ($data['last_reviewed'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'applyLabel' => (string) ($data['apply_label'] ?? ''),
            'applyUrl' => (string) ($data['apply_url'] ?? ''),
            'requestInfoLabel' => (string) ($data['request_info_label'] ?? ''),
            'requestInfoUrl' => (string) ($data['request_info_url'] ?? ''),
            'requiredLabel' => (string) ($data['required_label'] ?? ''),
            'optionalLabel' => (string) ($data['optional_label'] ?? ''),
            'downloadLabel' => (string) ($data['download_label'] ?? ''),
            'downloadAllLabel' => (string) ($data['download_all_label'] ?? ''),
            'downloadAllDesc' => (string) ($data['download_all_desc'] ?? ''),
            'lastReviewedLabel' => (string) ($data['last_reviewed_label'] ?? ''),
            'tabs' => [
                [
                    'id' => 'checklist',
                    'label' => (string) ($data['checklist_label'] ?? ''),
                    'subTabs' => collect($this->listValue($data, 'checklist_subtabs'))
                        ->map(fn (array $subTab): array => [
                            'id' => (string) ($subTab['id'] ?? ''),
                            'label' => (string) ($subTab['label'] ?? ''),
                            'desc' => (string) ($subTab['desc'] ?? ''),
                            'download' => [
                                'href' => (string) ($subTab['download_href'] ?? ''),
                                'size' => (string) ($subTab['download_size'] ?? ''),
                            ],
                            'items' => collect($this->listValue($subTab, 'items'))
                                ->map(static fn (array $item): array => [
                                    'name' => (string) ($item['name'] ?? ''),
                                    'required' => (bool) ($item['required'] ?? false),
                                    'note' => (string) ($item['note'] ?? ''),
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'id' => 'granted',
                    'label' => (string) ($data['granted_label'] ?? ''),
                    'intro' => (string) ($data['granted_intro'] ?? ''),
                    'items' => collect($this->listValue($data, 'granted_items'))
                        ->map(static fn (array $item): array => [
                            'title' => (string) ($item['title'] ?? ''),
                            'desc' => (string) ($item['desc'] ?? ''),
                            'availability' => (string) ($item['availability'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ],
                [
                    'id' => 'studySystem',
                    'label' => (string) ($data['study_label'] ?? ''),
                    'intro' => (string) ($data['study_intro'] ?? ''),
                    'scaleTitle' => (string) ($data['scale_title'] ?? ''),
                    'scaleHeaders' => collect($this->listValue($data, 'scale_headers'))
                        ->map(static fn (array $header): array => [
                            'key' => (string) ($header['key'] ?? ''),
                            'label' => (string) ($header['label'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                    'scaleRows' => collect($this->listValue($data, 'scale_rows'))
                        ->map(static fn (array $row): array => [
                            'percentage' => (string) ($row['percentage'] ?? ''),
                            'gpa' => (string) ($row['gpa'] ?? ''),
                            'grade' => (string) ($row['grade'] ?? ''),
                            'descriptor' => (string) ($row['descriptor'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                    'notes' => collect($this->listValue($data, 'study_notes'))
                        ->map(static fn (array $note): string => (string) ($note['note'] ?? ''))
                        ->filter(static fn (string $note): bool => $note !== '')
                        ->values()
                        ->all(),
                ],
                [
                    'id' => 'warnings',
                    'label' => (string) ($data['warnings_label'] ?? ''),
                    'intro' => (string) ($data['warnings_intro'] ?? ''),
                    'levelsTitle' => (string) ($data['levels_title'] ?? ''),
                    'levels' => collect($this->listValue($data, 'levels'))
                        ->map(static fn (array $level): array => [
                            'level' => (string) ($level['level'] ?? ''),
                            'threshold' => (string) ($level['threshold'] ?? ''),
                            'consequences' => (string) ($level['consequences'] ?? ''),
                            'recovery' => (string) ($level['recovery'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function transferFormData(array $payload): array
    {
        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'apply_label' => $this->stringValue($payload, 'applyLabel'),
            'apply_url' => $this->stringValue($payload, 'applyUrl'),
            'request_info_label' => $this->stringValue($payload, 'requestInfoLabel'),
            'request_info_url' => $this->stringValue($payload, 'requestInfoUrl'),
            'required_label' => $this->stringValue($payload, 'requiredLabel'),
            'optional_label' => $this->stringValue($payload, 'optionalLabel'),
            'notes_title' => $this->stringValue($payload, 'notesTitle'),
            'notes_desc' => $this->stringValue($payload, 'notesDesc'),
            'tabs' => $this->listValue($payload, 'tabs'),
        ];
    }

    /** @return array<string, mixed> */
    private function transferPayloadFromForm(array $data): array
    {
        return [
            'heroImage' => (string) ($data['hero_image'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumb_home'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumb_parent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumb_current'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'applyLabel' => (string) ($data['apply_label'] ?? ''),
            'applyUrl' => (string) ($data['apply_url'] ?? ''),
            'requestInfoLabel' => (string) ($data['request_info_label'] ?? ''),
            'requestInfoUrl' => (string) ($data['request_info_url'] ?? ''),
            'requiredLabel' => (string) ($data['required_label'] ?? ''),
            'optionalLabel' => (string) ($data['optional_label'] ?? ''),
            'tabs' => collect($this->listValue($data, 'tabs'))
                ->map(fn (array $tab): array => [
                    'id' => (string) ($tab['id'] ?? ''),
                    'label' => (string) ($tab['label'] ?? ''),
                    'policiesTitle' => (string) ($tab['policiesTitle'] ?? ''),
                    'policies' => collect($this->listValue($tab, 'policies'))
                        ->map(static fn (array $policy): array => [
                            'icon' => (string) ($policy['icon'] ?? ''),
                            'title' => (string) ($policy['title'] ?? ''),
                            'desc' => (string) ($policy['desc'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                    'documentsTitle' => (string) ($tab['documentsTitle'] ?? ''),
                    'documents' => collect($this->listValue($tab, 'documents'))
                        ->map(static fn (array $document): array => [
                            'title' => (string) ($document['title'] ?? ''),
                            'required' => (bool) ($document['required'] ?? false),
                        ])
                        ->values()
                        ->all(),
                    'processTitle' => (string) ($tab['processTitle'] ?? ''),
                    'steps' => collect($this->listValue($tab, 'steps'))
                        ->map(static fn (array $step): array => [
                            'title' => (string) ($step['title'] ?? ''),
                            'desc' => (string) ($step['desc'] ?? ''),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'notesTitle' => (string) ($data['notes_title'] ?? ''),
            'notesDesc' => (string) ($data['notes_desc'] ?? ''),
        ];
    }

    private function targetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== ''
            ? $this->data['target_key']
            : 'admissions.landing';
    }

    /** @return array<int, array<string, mixed>> */
    private function listValue(array $data, string $key): array
    {
        return array_values(array_filter(is_array($data[$key] ?? null) ? $data[$key] : [], static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
