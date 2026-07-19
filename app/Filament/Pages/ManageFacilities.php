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
                ->action(function (array $data): void {
                    $this->schedule((string) $data['publish_at']);
                }),
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
            $draft = $this->cmsWorkflowService->saveDraft('facilities.landing', $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('Facilities draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this facilities target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save facilities draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this facilities target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create facilities preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Facilities hub published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish facilities hub')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('Facilities hub scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule facilities hub')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish('facilities.landing', (int) $user->id);
        $notification = Notification::make()->title($result ? 'Facilities hub unpublished' : 'No published facilities hub found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        $prefix = $locale.'_content';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.hero.title')->label('Title')->required()->maxLength(160),
                MediaPicker::image($prefix.'.hero.image', 'Hero Image', true),
                Textarea::make($prefix.'.hero.summary')->label('Summary')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.hero.applyLabel')->label('Apply Label')->required()->maxLength(120),
                TextInput::make($prefix.'.hero.applyUrl')->label('Apply URL')->required()->maxLength(255),
                TextInput::make($prefix.'.hero.campusMapLabel')->label('Campus Map Label')->required()->maxLength(120),
            ])->columns(2),

            Section::make('Facts')->schema([
                Repeater::make($prefix.'.facts')
                    ->label('Facts')
                    ->schema([
                        TextInput::make('value')->required()->maxLength(40),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Faculty Buttons')->schema([
                Repeater::make($prefix.'.facultyLinks')
                    ->label('Faculty Buttons')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2),
                        TextInput::make('url')->required()->maxLength(255),
                        TextInput::make('accentColor')->label('Accent Color')->maxLength(20),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]),

            Section::make('Academic Model')->schema([
                TextInput::make($prefix.'.model.title')->label('Title')->required()->maxLength(180),
                Repeater::make($prefix.'.model.cards')
                    ->label('Model Cards')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(2),
                        Toggle::make('featured')->label('Featured'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),
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
