<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Exceptions\ConflictException;
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
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
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

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_content' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_content' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
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
                TextInput::make($prefix.'.faculty.heroImage')->label('Hero Image')->maxLength(255),
                TextInput::make($prefix.'.faculty.logoImage')->label('Logo Image')->maxLength(255),
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
                TextInput::make($prefix.'.dean.image')->label('Dean Image')->maxLength(255),
                Textarea::make($prefix.'.dean.message')->label('Message')->rows(4)->columnSpanFull(),
            ])->columns(2),

            Section::make('Gallery')->schema([
                Repeater::make($prefix.'.gallery')
                    ->label('Gallery Images')
                    ->schema([
                        TextInput::make('image')->label('Image')->maxLength(255),
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
                        TextInput::make('icon')->maxLength(255),
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
                        TextInput::make('image')->maxLength(255),
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
                    : $this->normalizeSubpageContent($targetKey, is_array($state['ar_content'] ?? null) ? $state['ar_content'] : []),
                'en' => $targetKey === $this->defaultTargetKey()
                    ? $this->normalizeContent(is_array($state['en_content'] ?? null) ? $state['en_content'] : [])
                    : $this->normalizeSubpageContent($targetKey, is_array($state['en_content'] ?? null) ? $state['en_content'] : []),
            ],
        ];
    }

    /** @return array<int, Section> */
    private function subpageFields(string $locale): array
    {
        $prefix = $locale.'_content';
        $targetKey = $this->currentTargetKeyForSchema();

        $sections = [
            Section::make('Subpage Content')->schema([
                TextInput::make($prefix.'.title')->label('Title')->required()->maxLength(180),
                TextInput::make($prefix.'.heroImage')->label('Hero Image')->maxLength(255),
                Textarea::make($prefix.'.summary')->label('Summary')->rows(2)->columnSpanFull(),
                Textarea::make($prefix.'.body')->label('Body')->rows(4)->columnSpanFull(),
            ])->columns(2),
        ];

        if (str_ends_with($targetKey, '.overview')) {
            $sections[] = Section::make('Overview Sections')->schema([
                Repeater::make($prefix.'.sections')
                    ->label('Sections')
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
            ]);

            $sections[] = Section::make('Overview Stats')->schema([
                Repeater::make($prefix.'.stats')
                    ->label('Stats')
                    ->schema([
                        TextInput::make('value')->maxLength(40),
                        TextInput::make('label')->maxLength(120),
                        TextInput::make('icon')->maxLength(255),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ]);

            $sections[] = Section::make('Dean Message')->schema([
                TextInput::make($prefix.'.dean.nameAr')->label('Dean Name AR')->maxLength(160),
                TextInput::make($prefix.'.dean.nameEn')->label('Dean Name EN')->maxLength(160),
                TextInput::make($prefix.'.dean.roleAr')->label('Dean Role AR')->maxLength(160),
                TextInput::make($prefix.'.dean.roleEn')->label('Dean Role EN')->maxLength(160),
                TextInput::make($prefix.'.dean.image')->label('Dean Image')->maxLength(255),
                Textarea::make($prefix.'.dean.messageAr')->label('Message AR')->rows(4),
                Textarea::make($prefix.'.dean.messageEn')->label('Message EN')->rows(4),
            ])->columns(2);

            return $sections;
        }

        if (str_ends_with($targetKey, '.study_plan')) {
            $sections[] = Section::make('Study Plan Labels')->schema([
                TextInput::make($prefix.'.payload.labels.title')->label('Title')->maxLength(160),
                TextInput::make($prefix.'.payload.labels.home')->label('Home Label')->maxLength(120),
                TextInput::make($prefix.'.payload.labels.faculties')->label('Facilities Label')->maxLength(120),
                TextInput::make($prefix.'.payload.labels.empty')->label('Empty Label')->maxLength(160),
            ])->columns(2);

            $sections[] = Section::make('Study Plan Departments')->schema([
                TextInput::make($prefix.'.payload.plan.faculty')->label('Plan Faculty Name')->maxLength(180),
                TextInput::make($prefix.'.payload.plan.heroImage')->label('Plan Hero Image')->maxLength(255),
                Repeater::make($prefix.'.payload.plan.departments')
                    ->label('Departments')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('name')->required()->maxLength(160),
                        Repeater::make('terms')
                            ->label('Terms')
                            ->schema([
                                TextInput::make('id')->required()->maxLength(80),
                                TextInput::make('label')->required()->maxLength(120),
                                Repeater::make('courses')
                                    ->label('Courses')
                                    ->schema([
                                        TextInput::make('id')->required()->maxLength(80),
                                        TextInput::make('code')->maxLength(80),
                                        TextInput::make('title')->required()->maxLength(180),
                                        TextInput::make('hours')->numeric(),
                                        TextInput::make('type')->maxLength(80),
                                    ])
                                    ->columns(3)
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
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2);

            return $sections;
        }

        $sections[] = Section::make('Subpage Items')->schema([
            Repeater::make($prefix.'.items')
                ->label('Items')
                ->schema([
                    TextInput::make('slug')->maxLength(100),
                    TextInput::make('title')->maxLength(180),
                    TextInput::make('code')->maxLength(40),
                    TextInput::make('degrees')->maxLength(160),
                    TextInput::make('department')->maxLength(160),
                    TextInput::make('instructor')->maxLength(160),
                    TextInput::make('tag')->maxLength(120),
                    TextInput::make('team')->maxLength(180),
                    TextInput::make('supervisor')->maxLength(180),
                    TextInput::make('graduationYear')->maxLength(20),
                    TextInput::make('academicYear')->maxLength(40),
                    TextInput::make('gpa')->maxLength(20),
                    TextInput::make('semester')->maxLength(80),
                    TextInput::make('image')->maxLength(255),
                    TextInput::make('detailRoute')->maxLength(255),
                    Textarea::make('summary')->rows(2)->columnSpanFull(),
                ])
                ->columns(3)
                ->defaultItems(0)
                ->reorderable()
                ->collapsible()
                ->columnSpanFull(),
        ]);

        return $sections;
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
    private function normalizeSubpageContent(string $targetKey, array $content): array
    {
        $content['sections'] = $this->listOfArrays($content['sections'] ?? []);

        if (str_ends_with($targetKey, '.overview')) {
            $content['stats'] = $this->listOfArrays($content['stats'] ?? []);
            $content['dean'] = is_array($content['dean'] ?? null) ? $content['dean'] : [];

            return $content;
        }

        if (str_ends_with($targetKey, '.study_plan')) {
            $content['payload'] = is_array($content['payload'] ?? null) ? $content['payload'] : [];
            $content['payload']['plan'] = is_array($content['payload']['plan'] ?? null) ? $content['payload']['plan'] : [];
            $content['payload']['labels'] = is_array($content['payload']['labels'] ?? null) ? $content['payload']['labels'] : [];
            $content['payload']['plan']['departments'] = array_map(function (array $department): array {
                $department['terms'] = array_map(function (array $term): array {
                    $term['courses'] = $this->listOfArrays($term['courses'] ?? []);

                    return $term;
                }, $this->listOfArrays($department['terms'] ?? []));

                return $department;
            }, $this->listOfArrays($content['payload']['plan']['departments'] ?? []));
            unset($content['items'], $content['stats'], $content['dean']);

            return $content;
        }

        $content['items'] = $this->listOfArrays($content['items'] ?? []);
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
