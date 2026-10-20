<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\Exceptions\ConflictException;
use App\Filament\Components\PageUrlSelect;
use App\Filament\Concerns\LimitsCmsTargetsToGrants;
use App\Filament\Support\FormPicker;
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
use Illuminate\Validation\ValidationException;

class ManageAdmissions extends Page implements HasForms
{
    use InteractsWithForms;
    use LimitsCmsTargetsToGrants;

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
        return static::canAccessCmsArea('admissions', 'admissions');
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
        $this->loadTarget($this->firstGrantedTarget('admissions.landing', $this->targetOptions()));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin_pages.admissions.admissions_target'))->schema([
                    Select::make('target_key')
                        ->label(__('admin_pages.admissions.page_subpage'))
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                ...($this->targetKeyForSchema() === 'admissions.tuition' ? [$this->tuitionFeeRowsSection()] : []),
                ...($this->targetKeyForSchema() === 'admissions.how-to-apply' ? [
                    Section::make(__('admin.forms.fields.form'))->schema([
                        FormPicker::make('application_form_id')->label(__('admin.forms.fields.application_form'))->required(),
                    ]),
                ] : []),
                Tabs::make('admissions_locales')
                    ->tabs([
                        Tab::make('Arabic')->label(__('admin_pages.admissions.arabic'))->schema($this->payloadFields('ar')),
                        Tab::make('English')->label(__('admin_pages.admissions.english'))->schema($this->payloadFields('en')),
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
                ->label(__('admin_pages.admissions.save_draft'))
                ->icon('heroicon-o-check')
                ->color('gray')
                ->action(function (): void {
                    $this->save();
                }),
            Action::make('preview_ar')
                ->label(__('admin_pages.admissions.preview_ar'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function (): void {
                    $this->openPreview('ar');
                }),
            Action::make('preview_en')
                ->label(__('admin_pages.admissions.preview_en'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function (): void {
                    $this->openPreview('en');
                }),
            Action::make('publish')->visible(fn (): bool => static::canPublishCmsArea('admissions'))
                ->label(__('admin_pages.admissions.publish'))
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')->visible(fn (): bool => static::canPublishCmsArea('admissions'))
                ->label(__('admin_pages.admissions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')
                        ->label(__('admin_pages.admissions.publish_at'))
                        ->required()
                        ->minDate(now())
                        ->native(false),
                ])
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->visible(fn (): bool => static::canPublishCmsArea('admissions'))
                ->label(__('admin_pages.admissions.unpublish'))
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

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->admissionsPageService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, (int) auth()->id());

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_landing' => $targetKey === 'admissions.landing' && is_array($payload['translations']['ar'] ?? null) ? $this->landingFormData($payload['translations']['ar']) : [],
            'en_landing' => $targetKey === 'admissions.landing' && is_array($payload['translations']['en'] ?? null) ? $this->landingFormData($payload['translations']['en']) : [],
            'ar_requirements' => $targetKey === 'admissions.requirements' && is_array($payload['translations']['ar'] ?? null) ? $this->requirementsFormData($payload['translations']['ar']) : [],
            'en_requirements' => $targetKey === 'admissions.requirements' && is_array($payload['translations']['en'] ?? null) ? $this->requirementsFormData($payload['translations']['en']) : [],
            'ar_tuition' => $targetKey === 'admissions.tuition' && is_array($payload['translations']['ar'] ?? null) ? $this->tuitionFormData($payload['translations']['ar']) : [],
            'en_tuition' => $targetKey === 'admissions.tuition' && is_array($payload['translations']['en'] ?? null) ? $this->tuitionFormData($payload['translations']['en']) : [],
            'tuition_fees' => $targetKey === 'admissions.tuition' ? $this->tuitionFeeRowsFormData($payload) : [],
            'ar_how_to_apply' => $targetKey === 'admissions.how-to-apply' && is_array($payload['translations']['ar'] ?? null) ? $this->howToApplyFormData($payload['translations']['ar']) : [],
            'en_how_to_apply' => $targetKey === 'admissions.how-to-apply' && is_array($payload['translations']['en'] ?? null) ? $this->howToApplyFormData($payload['translations']['en']) : [],
            'application_form_id' => $targetKey === 'admissions.how-to-apply' ? (string) ($payload['translations']['ar']['applicationFormId'] ?? 'admissions-application') : null,
            'ar_faq' => $targetKey === 'admissions.faq' && is_array($payload['translations']['ar'] ?? null) ? $this->faqFormData($payload['translations']['ar']) : [],
            'en_faq' => $targetKey === 'admissions.faq' && is_array($payload['translations']['en'] ?? null) ? $this->faqFormData($payload['translations']['en']) : [],
            'ar_calendar' => $targetKey === 'admissions.calendar' && is_array($payload['translations']['ar'] ?? null) ? $this->calendarFormData($payload['translations']['ar']) : [],
            'en_calendar' => $targetKey === 'admissions.calendar' && is_array($payload['translations']['en'] ?? null) ? $this->calendarFormData($payload['translations']['en']) : [],
            'ar_documents' => $targetKey === 'admissions.documents' && is_array($payload['translations']['ar'] ?? null) ? $this->documentsFormData($payload['translations']['ar']) : [],
            'en_documents' => $targetKey === 'admissions.documents' && is_array($payload['translations']['en'] ?? null) ? $this->documentsFormData($payload['translations']['en']) : [],
            'ar_transfer' => $targetKey === 'admissions.transfer' && is_array($payload['translations']['ar'] ?? null) ? $this->transferFormData($payload['translations']['ar']) : [],
            'en_transfer' => $targetKey === 'admissions.transfer' && is_array($payload['translations']['en'] ?? null) ? $this->transferFormData($payload['translations']['en']) : [],
            'ar_filling_vacancies' => $targetKey === 'admissions.filling-vacancies' && is_array($payload['translations']['ar'] ?? null) ? $this->simplePageFormData($payload['translations']['ar']) : [],
            'en_filling_vacancies' => $targetKey === 'admissions.filling-vacancies' && is_array($payload['translations']['en'] ?? null) ? $this->simplePageFormData($payload['translations']['en']) : [],
            'ar_graduation_exams' => $targetKey === 'admissions.graduation-exams' && is_array($payload['translations']['ar'] ?? null) ? $this->simplePageFormData($payload['translations']['ar']) : [],
            'en_graduation_exams' => $targetKey === 'admissions.graduation-exams' && is_array($payload['translations']['en'] ?? null) ? $this->simplePageFormData($payload['translations']['en']) : [],
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

            Notification::make()->title(__('admin_pages.admissions.admissions_draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin_pages.admissions.draft_conflict_detected'))->body(__('admin_pages.admissions.reload_this_admissions_target_before_saving_again'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.admissions.failed_to_save_admissions_draft'))->body($e->getMessage())->danger()->send();
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
            Notification::make()->title(__('admin_pages.admissions.draft_conflict_detected'))->body(__('admin_pages.admissions.reload_this_admissions_target_before_previewing_again'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.admissions.failed_to_create_admissions_preview'))->body($e->getMessage())->danger()->send();
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

            Notification::make()->title(__('admin_pages.admissions.admissions_target_published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_pages.admissions.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.admissions.failed_to_publish_admissions_target'))->body($e->getMessage())->danger()->send();
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

            Notification::make()->title(__('admin_pages.admissions.admissions_target_scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin_pages.admissions.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin_pages.admissions.failed_to_schedule_admissions_target'))->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? __('admin_pages.admissions.admissions_target_unpublished') : __('admin_pages.admissions.no_published_admissions_target_found'));

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->onlyGrantedTargets($this->targetRegistry->forArea('admissions')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all());
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
            throw new \InvalidArgumentException(__('admin_pages.admissions.unsupported_admissions_target'));
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
            $feeRows = $this->listValue($state, 'tuition_fees');

            return [
                'translations' => [
                    'ar' => $this->tuitionPayloadFromForm(is_array($state['ar_tuition'] ?? null) ? $state['ar_tuition'] : [], $feeRows, 'ar'),
                    'en' => $this->tuitionPayloadFromForm(is_array($state['en_tuition'] ?? null) ? $state['en_tuition'] : [], $feeRows, 'en'),
                ],
            ];
        }

        if (($state['target_key'] ?? null) === 'admissions.how-to-apply') {
            // One form for both languages, kept in each so the public page reads it with its section.
            $form = ['applicationFormId' => (string) ($state['application_form_id'] ?? 'admissions-application')];

            return [
                'translations' => [
                    'ar' => $this->howToApplyPayloadFromForm(is_array($state['ar_how_to_apply'] ?? null) ? $state['ar_how_to_apply'] : []) + $form,
                    'en' => $this->howToApplyPayloadFromForm(is_array($state['en_how_to_apply'] ?? null) ? $state['en_how_to_apply'] : []) + $form,
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

        if (in_array($state['target_key'] ?? null, ['admissions.filling-vacancies', 'admissions.graduation-exams'], true)) {
            $stateKey = $this->simplePageStateKey((string) $state['target_key']);

            return [
                'translations' => [
                    'ar' => $this->simplePagePayloadFromForm((string) $state['target_key'], is_array($state['ar_'.$stateKey] ?? null) ? $state['ar_'.$stateKey] : []),
                    'en' => $this->simplePagePayloadFromForm((string) $state['target_key'], is_array($state['en_'.$stateKey] ?? null) ? $state['en_'.$stateKey] : []),
                ],
            ];
        }

        throw new \InvalidArgumentException(__('admin_pages.admissions.this_admissions_subpage_form_will_be_structured_next_select'));
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

        if (in_array($this->targetKeyForSchema(), ['admissions.filling-vacancies', 'admissions.graduation-exams'], true)) {
            return $this->simplePageFields($locale, $this->simplePageStateKey($this->targetKeyForSchema()));
        }

        return [
            Section::make(__('admin_pages.admissions.subpage_schema_pending'))
                ->description(__('admin_pages.admissions.we_are_converting_admissions_one_page_at_a_time_the_hub_page'))
                ->schema([
                    TextInput::make($locale.'_subpage_pending')
                        ->label(__('admin_pages.admissions.status'))
                        ->default(__('admin_pages.admissions.structured_form_pending_for_this_subpage'))
                        ->disabled(),
                ]),
        ];
    }

    /** @return array<int, Section> */
    private function simplePageFields(string $locale, string $stateKey): array
    {
        $prefix = $locale.'_'.$stateKey;

        return [
            Section::make(__('admin_pages.admissions.hero_and_intro'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.heroImage', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumbHome')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumbParent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumbCurrent')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(160),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.admissions.seo_description'))->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.intro')
                    ->label(__('admin_pages.admissions.intro_paragraphs'))
                    ->schema([
                        Textarea::make('paragraph')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.cards'))->schema([
                TextInput::make($prefix.'.cardsTitle')->label(__('admin_pages.admissions.cards_title'))->maxLength(180),
                Repeater::make($prefix.'.cards')
                    ->label(__('admin_pages.admissions.cards'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('body')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.admissions.steps'))->schema([
                TextInput::make($prefix.'.stepsTitle')->label(__('admin_pages.admissions.steps_title'))->maxLength(180),
                Repeater::make($prefix.'.steps')
                    ->label(__('admin_pages.admissions.steps'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('body')->required()->rows(3)->columnSpanFull(),
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
    private function transferFields(string $locale): array
    {
        $prefix = $locale.'_transfer';

        return [
            Section::make(__('admin_pages.admissions.hero_and_labels'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(160),
                TextInput::make($prefix.'.apply_label')->label(__('admin_pages.admissions.apply_button'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.apply_url', __('admin_pages.admissions.apply_page'), $locale, true),
                TextInput::make($prefix.'.request_info_label')->label(__('admin_pages.admissions.request_info_button'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.request_info_url', __('admin_pages.admissions.request_info_page'), $locale, true),
                TextInput::make($prefix.'.required_label')->label(__('admin_pages.admissions.required_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.optional_label')->label(__('admin_pages.admissions.optional_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.notes_title')->label(__('admin_pages.admissions.notes_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.notes_desc')->label(__('admin_pages.admissions.notes_description'))->required()->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.transfer_and_international_tabs'))->schema([
                Repeater::make($prefix.'.tabs')
                    ->label(__('admin_pages.admissions.applicant_tabs'))
                    ->schema([
                        TextInput::make('id')->label(__('admin_pages.admissions.tab_id'))->required()->maxLength(80),
                        TextInput::make('label')->label(__('admin_pages.admissions.tab_label'))->required()->maxLength(160),
                        TextInput::make('policiesTitle')->label(__('admin_pages.admissions.policies_title'))->required()->maxLength(180),
                        Repeater::make('policies')
                            ->label(__('admin_pages.admissions.policies'))
                            ->schema([
                                MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                                TextInput::make('title')->required()->maxLength(180),
                                Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('documentsTitle')->label(__('admin_pages.admissions.documents_title'))->required()->maxLength(180),
                        Repeater::make('documents')
                            ->label(__('admin_pages.admissions.documents'))
                            ->schema([
                                TextInput::make('title')->required()->maxLength(180),
                                Toggle::make('required')->label(__('admin_pages.admissions.required')),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('processTitle')->label(__('admin_pages.admissions.process_title'))->required()->maxLength(180),
                        Repeater::make('steps')
                            ->label(__('admin_pages.admissions.process_steps'))
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
            Section::make(__('admin_pages.admissions.hero_and_labels'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(120),
                TextInput::make($prefix.'.apply_label')->label(__('admin_pages.admissions.apply_button'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.apply_url', __('admin_pages.admissions.apply_page'), $locale, true),
                TextInput::make($prefix.'.request_info_label')->label(__('admin_pages.admissions.request_info_button'))->required()->maxLength(120),
                PageUrlSelect::make($prefix.'.request_info_url', __('admin_pages.admissions.request_info_page'), $locale, true),
                TextInput::make($prefix.'.required_label')->label(__('admin_pages.admissions.required_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.optional_label')->label(__('admin_pages.admissions.optional_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.download_label')->label(__('admin_pages.admissions.download_button_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.download_all_label')->label(__('admin_pages.admissions.download_all_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.download_all_desc')->label(__('admin_pages.admissions.download_all_description'))->required()->rows(2)->columnSpanFull(),
                Textarea::make($prefix.'.download_guidance')->label(__('admin_pages.admissions.no_download_guidance'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.last_reviewed_label')->label(__('admin_pages.admissions.last_reviewed_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.last_reviewed')->label(__('admin_pages.admissions.last_reviewed_value'))->maxLength(120),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.admission_checklist'))->schema([
                TextInput::make($prefix.'.checklist_label')->label(__('admin_pages.admissions.tab_label'))->required()->maxLength(160),
                Repeater::make($prefix.'.checklist_subtabs')
                    ->label(__('admin_pages.admissions.applicant_checklists'))
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        MediaPicker::document('download_href', __('admin_pages.admissions.download_file')),
                        TextInput::make('download_size')->label(__('admin_pages.admissions.download_size'))->maxLength(80),
                        Repeater::make('items')
                            ->label(__('admin_pages.admissions.documents'))
                            ->schema([
                                TextInput::make('name')->required()->maxLength(180),
                                Textarea::make('note')->rows(2)->columnSpanFull(),
                                Toggle::make('required')->label(__('admin_pages.admissions.required')),
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

            Section::make(__('admin_pages.admissions.university_documents'))->schema([
                TextInput::make($prefix.'.granted_label')->label(__('admin_pages.admissions.tab_label'))->required()->maxLength(160),
                Textarea::make($prefix.'.granted_intro')->label(__('admin_pages.admissions.intro'))->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.granted_items')
                    ->label(__('admin_pages.admissions.documents'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        Repeater::make($prefix.'.granted_sections')
                            ->label(__('admin_pages.admissions.detail_sections'))
                            ->schema([
                                TextInput::make('title')->maxLength(255),
                                Textarea::make('body')->rows(6)->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('availability')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.admissions.study_system_and_gpa'))->schema([
                TextInput::make($prefix.'.study_label')->label(__('admin_pages.admissions.tab_label'))->required()->maxLength(160),
                Textarea::make($prefix.'.study_intro')->label(__('admin_pages.admissions.intro'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.scale_title')->label(__('admin_pages.admissions.scale_title'))->required()->maxLength(160),
                Repeater::make($prefix.'.scale_headers')
                    ->label(__('admin_pages.admissions.scale_headers'))
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
                    ->label(__('admin_pages.admissions.scale_rows'))
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
                    ->label(__('admin_pages.admissions.study_notes'))
                    ->schema([
                        Textarea::make('note')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                Repeater::make($prefix.'.study_sections')
                    ->label(__('admin_pages.admissions.detail_sections'))
                    ->schema([
                        TextInput::make('title')->maxLength(255),
                        Textarea::make('body')->rows(6)->columnSpanFull(),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.admissions.academic_warnings'))->schema([
                TextInput::make($prefix.'.warnings_label')->label(__('admin_pages.admissions.tab_label'))->required()->maxLength(160),
                Textarea::make($prefix.'.warnings_intro')->label(__('admin_pages.admissions.intro'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.levels_title')->label(__('admin_pages.admissions.levels_title'))->required()->maxLength(160),
                Repeater::make($prefix.'.levels')
                    ->label(__('admin_pages.admissions.warning_levels'))
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
                Repeater::make($prefix.'.warnings_sections')
                    ->label(__('admin_pages.admissions.detail_sections'))
                    ->schema([
                        TextInput::make('title')->maxLength(255),
                        Textarea::make('body')->rows(6)->columnSpanFull(),
                    ])
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
            Section::make(__('admin_pages.admissions.hero'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(120),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.highlights_and_deadlines'))->schema([
                Repeater::make($prefix.'.stat_cards')
                    ->label(__('admin_pages.admissions.stat_cards'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
                TextInput::make($prefix.'.deadlines_title')->label(__('admin_pages.admissions.deadlines_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.deadlines')
                    ->label(__('admin_pages.admissions.deadlines'))
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

            Section::make(__('admin_pages.admissions.academic_timeline'))->schema([
                TextInput::make($prefix.'.timeline_title')->label(__('admin_pages.admissions.timeline_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.semesters')
                    ->label(__('admin_pages.admissions.semesters'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(180),
                        Repeater::make('events')
                            ->label(__('admin_pages.admissions.events'))
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

            Section::make(__('admin_pages.admissions.download_and_notice'))->schema([
                Textarea::make($prefix.'.schedule_guidance')->label(__('admin_pages.admissions.no_published_dates_guidance'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.download_title')->label(__('admin_pages.admissions.download_title'))->maxLength(180),
                Textarea::make($prefix.'.download_desc')->label(__('admin_pages.admissions.download_description'))->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.download_button')->label(__('admin_pages.admissions.download_button'))->maxLength(120),
                MediaPicker::document($prefix.'.download_href', __('admin_pages.admissions.download_file')),
                TextInput::make($prefix.'.notice_title')->label(__('admin_pages.admissions.notice_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.notice_desc')->label(__('admin_pages.admissions.notice_description'))->required()->rows(3)->columnSpanFull(),
            ])->columns(2),
        ];
    }

    /** @return array<int, Section> */
    private function faqFields(string $locale): array
    {
        $prefix = $locale.'_faq';

        return [
            Section::make(__('admin_pages.admissions.hero_and_search'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(120),
                TextInput::make($prefix.'.search_label')->label(__('admin_pages.admissions.search_label'))->required()->maxLength(160),
                TextInput::make($prefix.'.search_placeholder')->label(__('admin_pages.admissions.search_placeholder'))->required()->maxLength(180),
                TextInput::make($prefix.'.empty_state')->label(__('admin_pages.admissions.empty_state'))->required()->maxLength(180),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.faq_groups'))->schema([
                Repeater::make($prefix.'.sections')
                    ->label(__('admin_pages.admissions.faq_groups'))
                    ->schema([
                        TextInput::make('id')->label(__('admin_pages.admissions.group_id'))->required()->maxLength(100),
                        TextInput::make('title')->label(__('admin_pages.admissions.group_title'))->required()->maxLength(160),
                        MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                        Repeater::make('items')
                            ->label(__('admin_pages.admissions.questions'))
                            ->schema([
                                TextInput::make('q')->label(__('admin_pages.admissions.question'))->required()->maxLength(240),
                                Textarea::make('a')->label(__('admin_pages.admissions.answer'))->required()->rows(3)->columnSpanFull(),
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
            Section::make(__('admin_pages.admissions.hero_and_intro'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(120),
                TextInput::make($prefix.'.hero_title')->label(__('admin_pages.admissions.intro_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.hero_desc')->label(__('admin_pages.admissions.intro_description'))->required()->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.feature_cards'))->schema([
                Repeater::make($prefix.'.feature_cards')
                    ->label(__('admin_pages.admissions.feature_cards'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.admissions.step_by_step_guide'))->schema([
                TextInput::make($prefix.'.guide_title')->label(__('admin_pages.admissions.guide_title'))->required()->maxLength(180),
                TextInput::make($prefix.'.application_title')->label(__('admin_pages.admissions.application_form_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.application_guidance')->label(__('admin_pages.admissions.application_form_guidance'))->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.steps')
                    ->label(__('admin_pages.admissions.steps'))
                    ->schema([
                        TextInput::make('number')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('cta')->label(__('admin_pages.admissions.cta_label'))->required()->maxLength(120),
                        PageUrlSelect::make('href', __('admin_pages.admissions.cta_page'), $locale, true),
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
            Section::make(__('admin_pages.admissions.fee_table_text'))
                ->description(__('admin_pages.admissions.fee_table_text_help'))
                ->schema([
                    TextInput::make($prefix.'.overview_title')->label(__('admin_pages.admissions.overview_title'))->required()->maxLength(180),
                    TextInput::make($prefix.'.fee_unit')->label(__('admin_pages.admissions.fee_unit'))->helperText(__('admin_pages.admissions.fee_unit_help'))->maxLength(120),
                    Textarea::make($prefix.'.fee_table_intro')->label(__('admin_pages.admissions.fee_table_intro'))->rows(2)->maxLength(500)->columnSpanFull(),
                    TextInput::make($prefix.'.column_faculty')->label(__('admin_pages.admissions.column_faculty'))->required()->maxLength(120),
                    TextInput::make($prefix.'.column_certificate')->label(__('admin_pages.admissions.column_certificate'))->required()->maxLength(160),
                    TextInput::make($prefix.'.column_credit_hours')->label(__('admin_pages.admissions.column_credit_hours'))->required()->maxLength(120),
                    TextInput::make($prefix.'.column_syrian_fee')->label(__('admin_pages.admissions.column_syrian_fee'))->required()->maxLength(160),
                    TextInput::make($prefix.'.column_foreign_fee')->label(__('admin_pages.admissions.column_foreign_fee'))->required()->maxLength(160),
                    TextInput::make($prefix.'.currency_syrian')->label(__('admin_pages.admissions.currency_syrian'))->required()->maxLength(40),
                    TextInput::make($prefix.'.currency_foreign')->label(__('admin_pages.admissions.currency_foreign'))->required()->maxLength(40),
                    TextInput::make($prefix.'.track_scientific')->label(__('admin_pages.admissions.track_scientific'))->required()->maxLength(80),
                    TextInput::make($prefix.'.track_vocational')->label(__('admin_pages.admissions.track_vocational'))->required()->maxLength(80),
                    TextInput::make($prefix.'.track_literary')->label(__('admin_pages.admissions.track_literary'))->required()->maxLength(80),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

            Section::make(__('admin_pages.admissions.hero_and_filters'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(120),
                TextInput::make($prefix.'.faculty_filter_label')->label(__('admin_pages.admissions.faculty_filter_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.student_type_filter_label')->label(__('admin_pages.admissions.student_type_filter_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.empty_state')->label(__('admin_pages.admissions.empty_state'))->required()->maxLength(180),
                Textarea::make($prefix.'.availability_guidance')->label(__('admin_pages.admissions.no_published_fees_guidance'))->required()->rows(3)->columnSpanFull(),
            ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),

            Section::make(__('admin_pages.admissions.payment_methods'))->schema([
                TextInput::make($prefix.'.payment_title')->label(__('admin_pages.admissions.payment_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.payment_guidance')->label(__('admin_pages.admissions.no_published_payment_method_guidance'))->required()->rows(3)->columnSpanFull(),
                Repeater::make($prefix.'.methods')
                    ->label(__('admin_pages.admissions.payment_methods'))
                    ->schema([
                        MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        Repeater::make('details')
                            ->label(__('admin_pages.admissions.details'))
                            ->schema([
                                TextInput::make('label')->required()->maxLength(120),
                                TextInput::make('value')->required()->maxLength(180),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        TextInput::make('cta')->label(__('admin_pages.admissions.cta_label'))->maxLength(120),
                        PageUrlSelect::make('ctaUrl', __('admin_pages.admissions.cta_page'), $locale),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin_pages.admissions.financial_notes'))->schema([
                TextInput::make($prefix.'.notes_title')->label(__('admin_pages.admissions.notes_title'))->required()->maxLength(180),
                Repeater::make($prefix.'.notes')
                    ->label(__('admin_pages.admissions.notes'))
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

    /**
     * One row per faculty, shared by the Arabic and English pages: only the faculty name is
     * entered per language, every number is entered once.
     */
    private function tuitionFeeRowsSection(): Section
    {
        $average = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->suffix('%')
            ->requiredWithoutAll(array_values(array_diff(['scientific_average', 'vocational_average', 'literary_average'], [$name])))
            ->validationMessages(['required_without_all' => __('admin_pages.admissions.at_least_one_track')])
            ->columnSpan(2);

        return Section::make(__('admin_pages.admissions.fees_by_faculty'))
            ->description(__('admin_pages.admissions.fees_by_faculty_help'))
            ->schema([
                Repeater::make('tuition_fees')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('faculty_ar')->label(__('admin_pages.admissions.faculty_name_ar'))->required()->maxLength(160)->live(onBlur: true)->columnSpan(3),
                        TextInput::make('faculty_en')->label(__('admin_pages.admissions.faculty_name_en'))->required()->maxLength(160)->columnSpan(3),
                        $average('scientific_average', __('admin_pages.admissions.min_average_scientific')),
                        $average('vocational_average', __('admin_pages.admissions.min_average_vocational')),
                        $average('literary_average', __('admin_pages.admissions.min_average_literary')),
                        TextInput::make('credit_hours')->label(__('admin_pages.admissions.total_credit_hours'))->required()->integer()->minValue(1)->columnSpan(2),
                        TextInput::make('syrian_fee')->label(__('admin_pages.admissions.fee_syrian_students'))->required()->numeric()->minValue(1)->columnSpan(2),
                        TextInput::make('foreign_fee')->label(__('admin_pages.admissions.fee_foreign_students'))->required()->numeric()->minValue(1)->columnSpan(2),
                    ])
                    ->columns(6)
                    ->itemLabel(fn (array $state): ?string => is_string($state['faculty_ar'] ?? null) && $state['faculty_ar'] !== '' ? $state['faculty_ar'] : null)
                    ->addActionLabel(__('admin_pages.admissions.add_faculty_fee_row'))
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<int, Section> */
    private function requirementsFields(string $locale): array
    {
        $prefix = $locale.'_requirements';

        return [
            Section::make(__('admin_pages.admissions.hero_and_labels'))->schema([
                TextInput::make($prefix.'.title')->label(__('admin_pages.admissions.page_title'))->required()->maxLength(180),
                MediaPicker::image($prefix.'.hero_image', __('admin_pages.admissions.hero_image'), true),
                TextInput::make($prefix.'.breadcrumb_home')->label(__('admin_pages.admissions.breadcrumb_home'))->required()->maxLength(80),
                TextInput::make($prefix.'.breadcrumb_parent')->label(__('admin_pages.admissions.breadcrumb_parent'))->required()->maxLength(120),
                TextInput::make($prefix.'.breadcrumb_current')->label(__('admin_pages.admissions.breadcrumb_current'))->required()->maxLength(120),
                TextInput::make($prefix.'.eligibility_title')->label(__('admin_pages.admissions.eligibility_section_title'))->required()->maxLength(160),
                TextInput::make($prefix.'.documents_title')->label(__('admin_pages.admissions.documents_section_title'))->required()->maxLength(160),
                TextInput::make($prefix.'.ready_title')->label(__('admin_pages.admissions.checklist_section_title'))->required()->maxLength(160),
                TextInput::make($prefix.'.notes_title')->label(__('admin_pages.admissions.notes_section_title'))->required()->maxLength(160),
                TextInput::make($prefix.'.required_label')->label(__('admin_pages.admissions.required_label'))->required()->maxLength(80),
                TextInput::make($prefix.'.optional_label')->label(__('admin_pages.admissions.optional_label'))->required()->maxLength(120),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.applicant_tabs'))->schema([
                Repeater::make($prefix.'.tabs')
                    ->label(__('admin_pages.admissions.tabs'))
                    ->schema([
                        TextInput::make('id')->label(__('admin_pages.admissions.tab_id'))->required()->maxLength(80),
                        TextInput::make('label')->label(__('admin_pages.admissions.tab_label'))->required()->maxLength(120),
                        Repeater::make('criteria')
                            ->label(__('admin_pages.admissions.eligibility_criteria'))
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
                            ->label(__('admin_pages.admissions.required_documents'))
                            ->schema([
                                TextInput::make('name')->required()->maxLength(180),
                                Toggle::make('required')->label(__('admin_pages.admissions.required')),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Repeater::make('checklist')
                            ->label(__('admin_pages.admissions.ready_checklist'))
                            ->schema([
                                TextInput::make('item')->required()->maxLength(180),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                        Textarea::make('note')->label(__('admin_pages.admissions.important_note'))->required()->rows(3)->columnSpanFull(),
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
            Section::make(__('admin_pages.admissions.hero'))->schema([
                TextInput::make($prefix.'.hero_title')->label(__('admin_pages.admissions.title'))->required()->maxLength(180),
                Textarea::make($prefix.'.hero_summary')->label(__('admin_pages.admissions.summary'))->required()->rows(3)->columnSpanFull(),
                TextInput::make($prefix.'.hero_cta_primary')->label(__('admin_pages.admissions.primary_cta'))->required()->maxLength(80),
                PageUrlSelect::make($prefix.'.hero_primary_url', __('admin_pages.admissions.primary_url'), $locale, true),
                TextInput::make($prefix.'.hero_cta_secondary')->label(__('admin_pages.admissions.secondary_cta'))->required()->maxLength(80),
                PageUrlSelect::make($prefix.'.hero_secondary_url', __('admin_pages.admissions.secondary_url'), $locale, true),
                TextInput::make($prefix.'.hero_badge_label')->label(__('admin_pages.admissions.badge_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.hero_badge_value')->label(__('admin_pages.admissions.badge_value'))->required()->maxLength(120),
                MediaPicker::image($prefix.'.hero_campus_image', __('admin_pages.admissions.campus_image'), true),
                TextInput::make($prefix.'.hero_campus_alt')->label(__('admin_pages.admissions.campus_image_alt_text'))->required()->maxLength(255),
                MediaPicker::image($prefix.'.hero_students_image', __('admin_pages.admissions.students_image'), true),
                TextInput::make($prefix.'.hero_students_alt')->label(__('admin_pages.admissions.students_image_alt_text'))->required()->maxLength(255),
                Repeater::make($prefix.'.hero_checklist_items')
                    ->label(__('admin_pages.admissions.checklist_items'))
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

            Section::make(__('admin_pages.admissions.trust_bar'))->schema([
                Repeater::make($prefix.'.trust_bar')
                    ->label(__('admin_pages.admissions.trust_items'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible(),
            ]),

            Section::make(__('admin_pages.admissions.admissions_journey'))->schema([
                TextInput::make($prefix.'.journey_eyebrow')->label(__('admin_pages.admissions.eyebrow'))->required()->maxLength(120),
                TextInput::make($prefix.'.journey_title')->label(__('admin_pages.admissions.title'))->required()->maxLength(180),
                Repeater::make($prefix.'.journey_steps')
                    ->label(__('admin_pages.admissions.journey_steps'))
                    ->schema([
                        TextInput::make('number')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2)->columnSpanFull(),
                        Toggle::make('active')->label(__('admin_pages.admissions.active')),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.timeline'))->schema([
                TextInput::make($prefix.'.timeline_eyebrow')->label(__('admin_pages.admissions.eyebrow'))->required()->maxLength(120),
                TextInput::make($prefix.'.timeline_title')->label(__('admin_pages.admissions.title'))->required()->maxLength(180),
                Textarea::make($prefix.'.timeline_summary')->label(__('admin_pages.admissions.summary'))->required()->rows(3)->columnSpanFull(),
                TextInput::make($prefix.'.timeline_primary_deadline')->label(__('admin_pages.admissions.primary_deadline'))->required()->maxLength(120),
                TextInput::make($prefix.'.timeline_primary_deadline_label')->label(__('admin_pages.admissions.deadline_label'))->required()->maxLength(120),
                Textarea::make($prefix.'.timeline_primary_deadline_desc')->label(__('admin_pages.admissions.deadline_description'))->required()->rows(3)->columnSpanFull(),
                MediaPicker::image($prefix.'.timeline_image', __('admin_pages.admissions.timeline_image'), true),
                TextInput::make($prefix.'.timeline_image_alt')->label(__('admin_pages.admissions.timeline_image_alt_text'))->required()->maxLength(255),
                Repeater::make($prefix.'.timeline_phases')
                    ->label(__('admin_pages.admissions.timeline_phases'))
                    ->schema([
                        TextInput::make('label')->required()->maxLength(80),
                        TextInput::make('title')->required()->maxLength(160),
                        TextInput::make('date')->required()->maxLength(120),
                        Toggle::make('active')->label(__('admin_pages.admissions.active')),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.resources'))->schema([
                TextInput::make($prefix.'.resources_eyebrow')->label(__('admin_pages.admissions.eyebrow'))->required()->maxLength(120),
                TextInput::make($prefix.'.resources_title')->label(__('admin_pages.admissions.title'))->required()->maxLength(180),
                Textarea::make($prefix.'.resources_subtitle')->label(__('admin_pages.admissions.subtitle'))->required()->rows(2)->columnSpanFull(),
                Repeater::make($prefix.'.resource_cards')
                    ->label(__('admin_pages.admissions.resource_cards'))
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        MediaPicker::icon('icon', __('admin_pages.admissions.icon'), true),
                        Textarea::make('desc')->required()->rows(2)->columnSpanFull(),
                        TextInput::make('link')->maxLength(120),
                        TextInput::make('slug')->required()->maxLength(80),
                        Toggle::make('active')->label(__('admin_pages.admissions.featured')),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make(__('admin_pages.admissions.seo'))->schema([
                TextInput::make($prefix.'.seoTitle')->label(__('admin_pages.admissions.seo_title'))->required()->maxLength(180),
                Textarea::make($prefix.'.seoDescription')->label(__('admin_pages.admissions.seo_description'))->required()->rows(2)->columnSpanFull(),
                MediaPicker::image($prefix.'.seoImage', __('admin_pages.admissions.seo_image'), true),
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
            'hero_campus_alt' => $this->stringValue($images, 'campusAlt'),
            'hero_students_image' => $this->stringValue($images, 'students'),
            'hero_students_alt' => $this->stringValue($images, 'studentsAlt'),
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
            'timeline_image_alt' => $this->stringValue($timeline, 'imageAlt'),
            'timeline_phases' => array_values(array_filter(is_array($timeline['phases'] ?? null) ? $timeline['phases'] : [], static fn (mixed $item): bool => is_array($item))),
            'resources_eyebrow' => $this->stringValue($resources, 'eyebrow'),
            'resources_title' => $this->stringValue($resources, 'title'),
            'resources_subtitle' => $this->stringValue($resources, 'subtitle'),
            'resource_cards' => array_values(array_filter(is_array($resources['cards'] ?? null) ? $resources['cards'] : [], static fn (mixed $item): bool => is_array($item))),
            'seoTitle' => $this->stringValue($payload, 'seoTitle') ?: $this->stringValue($hero, 'title'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription') ?: $this->stringValue($hero, 'summary'),
            'seoImage' => $this->stringValue($payload, 'seoImage') ?: $this->stringValue($images, 'campus'),
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
                    'campusAlt' => (string) ($data['hero_campus_alt'] ?? ''),
                    'students' => (string) ($data['hero_students_image'] ?? ''),
                    'studentsAlt' => (string) ($data['hero_students_alt'] ?? ''),
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
                'imageAlt' => (string) ($data['timeline_image_alt'] ?? ''),
                'phases' => $this->listValue($data, 'timeline_phases'),
            ],
            'resources' => [
                'eyebrow' => (string) ($data['resources_eyebrow'] ?? ''),
                'title' => (string) ($data['resources_title'] ?? ''),
                'subtitle' => (string) ($data['resources_subtitle'] ?? ''),
                'cards' => $this->listValue($data, 'resource_cards'),
            ],
            'seoTitle' => (string) ($data['seoTitle'] ?? ''),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
            'seoImage' => (string) ($data['seoImage'] ?? ''),
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
        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];
        $tracks = is_array($payload['trackLabels'] ?? null) ? $payload['trackLabels'] : [];
        $currencies = is_array($payload['currencies'] ?? null) ? $payload['currencies'] : [];

        return [
            'title' => $this->stringValue($payload, 'title'),
            'hero_image' => $this->stringValue($payload, 'heroImage'),
            'breadcrumb_home' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumb_parent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumb_current' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'faculty_filter_label' => $this->stringValue($filters, 'facultyLabel'),
            'student_type_filter_label' => $this->stringValue($filters, 'studentTypeLabel'),
            'overview_title' => $this->stringValue($payload, 'overviewTitle'),
            'fee_table_intro' => $this->stringValue($payload, 'feeTableIntro'),
            'fee_unit' => $this->stringValue($payload, 'feeUnit'),
            'column_faculty' => $this->stringValue($columns, 'faculty'),
            'column_certificate' => $this->stringValue($columns, 'certificate'),
            'column_credit_hours' => $this->stringValue($columns, 'creditHours'),
            'column_syrian_fee' => $this->stringValue($columns, 'syrianFee'),
            'column_foreign_fee' => $this->stringValue($columns, 'foreignFee'),
            'track_scientific' => $this->stringValue($tracks, 'scientific'),
            'track_vocational' => $this->stringValue($tracks, 'vocational'),
            'track_literary' => $this->stringValue($tracks, 'literary'),
            'currency_syrian' => $this->stringValue($currencies, 'syrian'),
            'currency_foreign' => $this->stringValue($currencies, 'foreign'),
            'empty_state' => $this->stringValue($payload, 'emptyState'),
            'availability_guidance' => $this->stringValue($payload, 'availabilityGuidance'),
            'payment_title' => $this->stringValue($payload, 'paymentTitle'),
            'payment_guidance' => $this->stringValue($payload, 'paymentGuidance'),
            'methods' => $this->listValue($payload, 'methods'),
            'notes_title' => $this->stringValue($payload, 'notesTitle'),
            'notes' => collect(is_array($payload['notes'] ?? null) ? $payload['notes'] : [])
                ->map(static fn (mixed $note): array => ['note' => is_string($note) || is_numeric($note) ? (string) $note : ''])
                ->filter(static fn (array $note): bool => $note['note'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * Pairs the Arabic and English fee rows by position into the single row list the editor shows.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, string>>
     */
    private function tuitionFeeRowsFormData(array $payload): array
    {
        $arabic = $this->listValue(is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [], 'feeRows');
        $english = $this->listValue(is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [], 'feeRows');
        $rows = [];

        for ($index = 0; $index < max(count($arabic), count($english)); $index++) {
            $numbers = $arabic[$index] ?? $english[$index];
            $rows[] = [
                'faculty_ar' => $this->stringValue($arabic[$index] ?? [], 'faculty'),
                'faculty_en' => $this->stringValue($english[$index] ?? [], 'faculty'),
                'scientific_average' => $this->stringValue($numbers, 'scientificAverage'),
                'vocational_average' => $this->stringValue($numbers, 'vocationalAverage'),
                'literary_average' => $this->stringValue($numbers, 'literaryAverage'),
                'credit_hours' => $this->stringValue($numbers, 'creditHours'),
                'syrian_fee' => $this->stringValue($numbers, 'syrianFee'),
                'foreign_fee' => $this->stringValue($numbers, 'foreignFee'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|int|float>
     */
    private function tuitionFeeRowPayload(array $row, string $locale): array
    {
        $number = static function (mixed $value): int|float|null {
            if (! is_numeric($value)) {
                return null;
            }

            return (float) $value === floor((float) $value) ? (int) $value : (float) $value;
        };

        return array_filter([
            'faculty' => trim((string) ($row['faculty_'.$locale] ?? '')),
            'scientificAverage' => $number($row['scientific_average'] ?? null),
            'vocationalAverage' => $number($row['vocational_average'] ?? null),
            'literaryAverage' => $number($row['literary_average'] ?? null),
            'creditHours' => $number($row['credit_hours'] ?? null),
            'syrianFee' => $number($row['syrian_fee'] ?? null),
            'foreignFee' => $number($row['foreign_fee'] ?? null),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $feeRows
     * @return array<string, mixed>
     */
    private function tuitionPayloadFromForm(array $data, array $feeRows, string $locale): array
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
            'feeTableIntro' => (string) ($data['fee_table_intro'] ?? ''),
            'feeUnit' => (string) ($data['fee_unit'] ?? ''),
            'columns' => [
                'faculty' => (string) ($data['column_faculty'] ?? ''),
                'certificate' => (string) ($data['column_certificate'] ?? ''),
                'creditHours' => (string) ($data['column_credit_hours'] ?? ''),
                'syrianFee' => (string) ($data['column_syrian_fee'] ?? ''),
                'foreignFee' => (string) ($data['column_foreign_fee'] ?? ''),
            ],
            'trackLabels' => [
                'scientific' => (string) ($data['track_scientific'] ?? ''),
                'vocational' => (string) ($data['track_vocational'] ?? ''),
                'literary' => (string) ($data['track_literary'] ?? ''),
            ],
            'currencies' => [
                'syrian' => (string) ($data['currency_syrian'] ?? ''),
                'foreign' => (string) ($data['currency_foreign'] ?? ''),
            ],
            'feeRows' => array_map(fn (array $row): array => $this->tuitionFeeRowPayload($row, $locale), $feeRows),
            'emptyState' => (string) ($data['empty_state'] ?? ''),
            'availabilityGuidance' => (string) ($data['availability_guidance'] ?? ''),
            'paymentTitle' => (string) ($data['payment_title'] ?? ''),
            'paymentGuidance' => (string) ($data['payment_guidance'] ?? ''),
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
            'application_title' => $this->stringValue($payload, 'applicationTitle'),
            'application_guidance' => $this->stringValue($payload, 'applicationGuidance'),
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
            'applicationTitle' => (string) ($data['application_title'] ?? ''),
            'applicationGuidance' => (string) ($data['application_guidance'] ?? ''),
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
            'schedule_guidance' => $this->stringValue($payload, 'scheduleGuidance'),
            'download_title' => $this->stringValue($download, 'title'),
            'download_desc' => $this->stringValue($download, 'desc'),
            'download_button' => $this->stringValue($download, 'button'),
            'download_href' => $this->stringValue($download, 'href'),
            'download_hrefMediaId' => is_numeric($download['mediaId'] ?? null) ? (int) $download['mediaId'] : null,
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
            'scheduleGuidance' => (string) ($data['schedule_guidance'] ?? ''),
            'download' => [
                'title' => (string) ($data['download_title'] ?? ''),
                'desc' => (string) ($data['download_desc'] ?? ''),
                'button' => (string) ($data['download_button'] ?? ''),
                'href' => (string) ($data['download_href'] ?? ''),
                'mediaId' => is_numeric($data['download_hrefMediaId'] ?? null) ? (int) $data['download_hrefMediaId'] : null,
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
            'download_guidance' => $this->stringValue($payload, 'downloadGuidance'),
            'last_reviewed_label' => $this->stringValue($payload, 'lastReviewedLabel'),
            'last_reviewed' => $this->stringValue($payload, 'lastReviewed'),
            'checklist_label' => $this->stringValue(is_array($checklist) ? $checklist : [], 'label'),
            'checklist_subtabs' => collect(is_array($checklist['subTabs'] ?? null) ? $checklist['subTabs'] : [])
                ->map(fn (array $subTab): array => [
                    'id' => $this->stringValue($subTab, 'id'),
                    'label' => $this->stringValue($subTab, 'label'),
                    'desc' => $this->stringValue($subTab, 'desc'),
                    'download_href' => $this->stringValue(is_array($subTab['download'] ?? null) ? $subTab['download'] : [], 'href'),
                    'download_hrefMediaId' => is_numeric($subTab['download']['mediaId'] ?? null) ? (int) $subTab['download']['mediaId'] : null,
                    'download_size' => $this->stringValue(is_array($subTab['download'] ?? null) ? $subTab['download'] : [], 'size'),
                    'items' => $this->listValue($subTab, 'items'),
                ])
                ->values()
                ->all(),
            'granted_label' => $this->stringValue(is_array($granted) ? $granted : [], 'label'),
            'granted_intro' => $this->stringValue(is_array($granted) ? $granted : [], 'intro'),
            'granted_items' => $this->listValue(is_array($granted) ? $granted : [], 'items'),
            'granted_sections' => $this->listValue(is_array($granted) ? $granted : [], 'sections'),
            'study_sections' => $this->listValue(is_array($study) ? $study : [], 'sections'),
            'warnings_sections' => $this->listValue(is_array($warnings) ? $warnings : [], 'sections'),
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
            'downloadGuidance' => (string) ($data['download_guidance'] ?? ''),
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
                                'mediaId' => is_numeric($subTab['download_hrefMediaId'] ?? null) ? (int) $subTab['download_hrefMediaId'] : null,
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
                    'sections' => $this->detailSectionsFromForm($data, 'granted_sections'),
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
                    'sections' => $this->detailSectionsFromForm($data, 'study_sections'),
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
                    'sections' => $this->detailSectionsFromForm($data, 'warnings_sections'),
                ],
            ],
        ];
    }

    /** @return list<array{title: string, body: string}> */
    private function detailSectionsFromForm(array $data, string $key): array
    {
        return collect($this->listValue($data, $key))
            ->map(static fn (array $section): array => ['title' => (string) ($section['title'] ?? ''), 'body' => (string) ($section['body'] ?? '')])
            ->filter(static fn (array $section): bool => trim($section['title'].$section['body']) !== '')
            ->values()
            ->all();
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

    /** @return array<string, mixed> */
    private function simplePageFormData(array $payload): array
    {
        $intro = $payload['intro'] ?? [];

        if (is_string($intro) && trim($intro) !== '') {
            $intro = [['paragraph' => $intro]];
        } elseif (is_array($intro)) {
            $intro = collect($intro)
                ->map(static fn (mixed $paragraph): array => ['paragraph' => is_string($paragraph) || is_numeric($paragraph) ? (string) $paragraph : ''])
                ->filter(static fn (array $paragraph): bool => trim((string) $paragraph['paragraph']) !== '')
                ->values()
                ->all();
        } else {
            $intro = [];
        }

        return [
            'title' => $this->stringValue($payload, 'title'),
            'heroImage' => $this->stringValue($payload, 'heroImage'),
            'breadcrumbHome' => $this->stringValue($payload, 'breadcrumbHome'),
            'breadcrumbParent' => $this->stringValue($payload, 'breadcrumbParent'),
            'breadcrumbCurrent' => $this->stringValue($payload, 'breadcrumbCurrent'),
            'seoDescription' => $this->stringValue($payload, 'seoDescription'),
            'intro' => $intro,
            'cardsTitle' => $this->stringValue($payload, 'cardsTitle'),
            'cards' => $this->listValue($payload, 'cards'),
            'stepsTitle' => $this->stringValue($payload, 'stepsTitle'),
            'steps' => $this->listValue($payload, 'steps'),
        ];
    }

    /** @return array<string, mixed> */
    private function simplePagePayloadFromForm(string $targetKey, array $data): array
    {
        return [
            'type' => $targetKey === 'admissions.filling-vacancies' ? 'simple-cards' : 'simple-steps',
            'heroImage' => (string) ($data['heroImage'] ?? ''),
            'breadcrumbHome' => (string) ($data['breadcrumbHome'] ?? ''),
            'breadcrumbParent' => (string) ($data['breadcrumbParent'] ?? ''),
            'breadcrumbCurrent' => (string) ($data['breadcrumbCurrent'] ?? ''),
            'title' => (string) ($data['title'] ?? ''),
            'intro' => collect($this->listValue($data, 'intro'))
                ->map(static fn (array $paragraph): string => (string) ($paragraph['paragraph'] ?? ''))
                ->filter(static fn (string $paragraph): bool => trim($paragraph) !== '')
                ->values()
                ->all(),
            'cardsTitle' => (string) ($data['cardsTitle'] ?? ''),
            'cards' => collect($this->listValue($data, 'cards'))
                ->map(static fn (array $card): array => [
                    'title' => (string) ($card['title'] ?? ''),
                    'body' => (string) ($card['body'] ?? ''),
                ])
                ->values()
                ->all(),
            'stepsTitle' => (string) ($data['stepsTitle'] ?? ''),
            'steps' => collect($this->listValue($data, 'steps'))
                ->map(static fn (array $step): array => [
                    'title' => (string) ($step['title'] ?? ''),
                    'body' => (string) ($step['body'] ?? ''),
                ])
                ->values()
                ->all(),
            'seoDescription' => (string) ($data['seoDescription'] ?? ''),
        ];
    }

    private function simplePageStateKey(string $targetKey): string
    {
        return match ($targetKey) {
            'admissions.filling-vacancies' => 'filling_vacancies',
            'admissions.graduation-exams' => 'graduation_exams',
            default => throw new \InvalidArgumentException(__('admin_pages.admissions.unsupported_admissions_simple_page_target')),
        };
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
