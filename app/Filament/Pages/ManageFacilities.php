<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Exceptions\ConflictException;
use App\Filament\Support\MediaPicker;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
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

class ManageFacilities extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $slug = 'manage-facilities';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-facilities';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private FacultyPageServiceInterface $facultyPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

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

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.facilities_hub');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_facilities');
    }

    public function mount(): void
    {
        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload('facilities.landing', (int) auth()->id());
        $payload = is_array($draftPayload) ? $draftPayload : $this->facultyPageService->getEditablePayload('facilities.landing');
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion('facilities.landing', (int) auth()->id());

        $this->form->fill([
            'ar_content' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_content' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('facilities_locales')
                    ->tabs([
                        Tab::make(__('admin.locales.ar'))->extraAttributes(['dir' => 'rtl'])->schema($this->payloadFields('ar')),
                        Tab::make(__('admin.locales.en'))->extraAttributes(['dir' => 'ltr'])->schema($this->payloadFields('en')),
                    ])
                    ->persistTabInQueryString('locale')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label(__('admin.campus_workspace.actions.save_draft'))->icon('heroicon-o-check')->color('gray')->action(function (): void {
                $this->save();
            }),
            Action::make('preview_ar')->label(__('admin.campus_workspace.actions.preview_ar'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('ar');
            }),
            Action::make('preview_en')->label(__('admin.campus_workspace.actions.preview_en'))->icon('heroicon-o-eye')->color('info')->action(function (): void {
                $this->openPreview('en');
            }),
            Action::make('publish')->label(__('admin.campus_workspace.actions.publish'))->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))->action(function (): void {
                    $this->publish();
                }),
            Action::make('schedule')
                ->label(__('admin.campus_workspace.actions.schedule'))
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('publish_at')->label(__('admin.campus_workspace.publish_at'))->required()->minDate(now())->native(false),
                ])
                ->visible(fn (): bool => Gate::allows('publish-content'))
                ->action(function (array $data): void {
                    $this->schedule((string) $data['publish_at']);
                }),
            Action::make('unpublish')->label(__('admin.campus_workspace.actions.unpublish'))->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('publish-content'))->action(function (): void {
                    $this->unpublish();
                }),
        ];
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('facilities.landing', $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title(__('admin.campus_workspace.notifications.draft_saved'))->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.campus_workspace.notifications.conflict'))->body(__('admin.campus_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.campus_workspace.notifications.save_failed'))->body(__('admin.campus_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('facilities.landing', $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $preview = $this->cmsWorkflowService->preview('facilities.landing', $locale, (int) $user->id);

            $this->redirect($preview->previewUrl);
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title(__('admin.campus_workspace.notifications.conflict'))->body(__('admin.campus_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.campus_workspace.notifications.preview_failed'))->body(__('admin.campus_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function publish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('facilities.landing', $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->publish('facilities.landing', (int) $user->id);

            Notification::make()->title(__('admin.campus_workspace.notifications.published'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.campus_workspace.notifications.publish_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.campus_workspace.notifications.publish_failed'))->body(__('admin.campus_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function schedule(string $publishAt): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('facilities.landing', $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->schedule('facilities.landing', new \DateTimeImmutable($publishAt), (int) $user->id);

            Notification::make()->title(__('admin.campus_workspace.notifications.scheduled'))->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(__('admin.campus_workspace.notifications.schedule_failed'))->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('admin.campus_workspace.notifications.schedule_failed'))->body(__('admin.campus_workspace.notifications.safe_error'))->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish('facilities.landing', (int) $user->id);
        $notification = Notification::make()->title($result
            ? __('admin.campus_workspace.notifications.unpublished')
            : __('admin.campus_workspace.notifications.nothing_published'));

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        $prefix = $locale.'_content';

        return [
            Section::make(__('admin.facilities_editor.sections.intro'))->schema([
                TextInput::make($prefix.'.hero.title')->label(__('admin.facilities_editor.fields.title'))->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', __('admin.facilities_editor.fields.hero_image'), true),
                Textarea::make($prefix.'.hero.summary')->label(__('admin.facilities_editor.fields.summary'))->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.applyLabel')->label(__('admin.facilities_editor.fields.apply_label'))->required()->maxLength(120),
                TextInput::make($prefix.'.hero.applyUrl')->label(__('admin.facilities_editor.fields.apply_url'))->required()->maxLength(255),
                TextInput::make($prefix.'.hero.campusMapLabel')->label(__('admin.facilities_editor.fields.map_label'))->required()->maxLength(120),
            ])->columns(2),

            Section::make(__('admin.facilities_editor.sections.facts'))->schema([
                Repeater::make($prefix.'.facts')
                    ->label(__('admin.facilities_editor.sections.facts'))
                    ->schema([
                        TextInput::make('value')->label(__('admin.facilities_editor.fields.value'))->required()->maxLength(40),
                        TextInput::make('label')->label(__('admin.facilities_editor.fields.label'))->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->collapsed(),

            Section::make(__('admin.facilities_editor.sections.faculty_cards'))->schema([
                Repeater::make($prefix.'.facultyLinks')
                    ->label(__('admin.facilities_editor.sections.faculty_cards'))
                    ->schema([
                        TextInput::make('title')->label(__('admin.facilities_editor.fields.faculty_name'))->required()->maxLength(160),
                        Textarea::make('summary')->label(__('admin.facilities_editor.fields.summary'))->required()->rows(2),
                        Section::make(__('admin.facilities_editor.sections.advanced'))->collapsed()->schema([
                            TextInput::make('url')->label(__('admin.facilities_editor.fields.url'))->required()->maxLength(255),
                            TextInput::make('accentColor')->label(__('admin.facilities_editor.fields.accent_color'))->maxLength(20),
                        ]),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make(__('admin.facilities_editor.sections.academic_model'))->schema([
                TextInput::make($prefix.'.model.title')->label(__('admin.facilities_editor.fields.title'))->required()->maxLength(180),
                Repeater::make($prefix.'.model.cards')
                    ->label(__('admin.facilities_editor.sections.model_cards'))
                    ->schema([
                        TextInput::make('title')->label(__('admin.facilities_editor.fields.title'))->required()->maxLength(160),
                        Textarea::make('summary')->label(__('admin.facilities_editor.fields.summary'))->required()->rows(2),
                        Toggle::make('featured')->label(__('admin.facilities_editor.fields.featured')),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2)->collapsed(),
        ];
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function payloadFromForm(array $state): array
    {
        return [
            'translations' => [
                'ar' => $this->normalizeContent(is_array($state['ar_content'] ?? null) ? $state['ar_content'] : []),
                'en' => $this->normalizeContent(is_array($state['en_content'] ?? null) ? $state['en_content'] : []),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeContent(array $content): array
    {
        $content['facts'] = $this->listOfArrays($content['facts'] ?? []);
        $content['facultyLinks'] = $this->listOfArrays($content['facultyLinks'] ?? []);
        $content['model']['cards'] = $this->listOfArrays($content['model']['cards'] ?? []);

        return $content;
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

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
