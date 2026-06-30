<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\EServicesPageServiceInterface;
use App\Exceptions\ConflictException;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageEServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $slug = 'manage-e-services-page';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-e-services-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private EServicesPageServiceInterface $eServicesPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        EServicesPageServiceInterface $eServicesPageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->eServicesPageService = $eServicesPageService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.e_services');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.e_services_page');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_e_services_page');
    }

    public function mount(): void
    {
        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload('e_services');
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion('e_services');

        $this->form->fill([
            'ar' => $this->formData('ar', $draftPayload),
            'en' => $this->formData('en', $draftPayload),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('e_services_page_locales')
                    ->tabs([
                        $this->localeTab('ar', 'Arabic'),
                        $this->localeTab('en', 'English'),
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

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft(
                'e_services',
                $this->payloadFromForm($this->currentFormData()),
                (int) $user->id,
                $this->draftVersion,
            );
            $this->draftVersion = $draft->version;

            Notification::make()->title('E-Services page draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;

            Notification::make()
                ->title('Draft conflict detected')
                ->body('This E-Services draft changed elsewhere. Reload the page before saving again.')
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save E-Services page draft')->danger()->send();
        }
    }

    public function publish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('e_services', $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->publish('e_services', (int) $user->id);

            Notification::make()->title('E-Services page published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Publish failed')
                ->body($this->formatValidationErrors($e->errors()))
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish E-Services page')->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('e_services', $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $preview = $this->cmsWorkflowService->preview('e_services', $locale, (int) $user->id);

            $this->redirect($preview->previewUrl);
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;

            Notification::make()
                ->title('Draft conflict detected')
                ->body('This E-Services draft changed elsewhere. Reload the page before previewing again.')
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create E-Services preview')->danger()->send();
        }
    }

    public function schedule(string $publishAt): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('e_services', $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->schedule('e_services', new \DateTimeImmutable($publishAt), (int) $user->id);

            Notification::make()->title('E-Services page scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Schedule failed')
                ->body($this->formatValidationErrors($e->errors()))
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule E-Services page')->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->cmsWorkflowService->unpublish('e_services', (int) $user->id);
        $notification = Notification::make()->title($result ? 'E-Services page unpublished' : 'No published E-Services page found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    private function localeTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Section::make('Hero')->schema([
                TextInput::make("{$locale}.hero_eyebrow")->label('Eyebrow')->required()->maxLength(160),
                TextInput::make("{$locale}.hero_title")->label('Title')->required()->maxLength(180),
                Textarea::make("{$locale}.hero_summary")->label('Summary')->required()->rows(3)->columnSpanFull(),
                TextInput::make("{$locale}.image_hero")->label('Hero Image')->required()->maxLength(255),
                TextInput::make("{$locale}.image_left")->label('Left Background Image')->required()->maxLength(255),
                TextInput::make("{$locale}.image_right")->label('Right Background Image')->required()->maxLength(255),
            ])->columns(2),

            Section::make('Digital Services')->schema([
                TextInput::make("{$locale}.digital_title")->label('Section Title')->required()->maxLength(160),
                Repeater::make("{$locale}.services")
                    ->schema([
                        TextInput::make('id')->required()->maxLength(20),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                        TextInput::make('icon')->required()->maxLength(255),
                        TextInput::make('url')->required()->maxLength(500),
                        TextInput::make('button')->required()->maxLength(80),
                    ])
                    ->columns(2)
                    ->reorderable()
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]),

            Section::make('Support Cards')->schema([
                Repeater::make("{$locale}.support_cards")
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('eyebrow')->required()->maxLength(120),
                        TextInput::make('title')->required()->maxLength(160),
                        Textarea::make('summary')->required()->rows(3)->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->reorderable()
                    ->defaultItems(0),
            ]),

            Section::make('SEO')->schema([
                TextInput::make("{$locale}.seo_title")->label('SEO Title')->required()->maxLength(180),
                Textarea::make("{$locale}.seo_description")->label('SEO Description')->required()->rows(3),
                TextInput::make("{$locale}.seo_image")->label('SEO Image')->required()->maxLength(255),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(string $locale, ?array $draftPayload): array
    {
        $draftContent = is_array($draftPayload['translations'][$locale] ?? null)
            ? $draftPayload['translations'][$locale]
            : null;

        if (is_array($draftContent)) {
            return $this->contentArrayToFormData($draftContent);
        }

        $content = $this->eServicesPageService->getContent($locale);

        return $this->contentArrayToFormData([
            'hero' => $content->hero,
            'digitalServices' => $content->digitalServices,
            'supportCards' => $content->supportCards,
            'seo' => [
                'title' => $content->seoTitle,
                'description' => $content->seoDescription,
                'image' => $content->seoImage,
            ],
        ]);
    }

    /** @param array<string, mixed> $content */
    private function contentArrayToFormData(array $content): array
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $digitalServices = is_array($content['digitalServices'] ?? null) ? $content['digitalServices'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return [
            'hero_eyebrow' => $this->stringValue($hero, 'eyebrow'),
            'hero_title' => $this->stringValue($hero, 'title'),
            'hero_summary' => $this->stringValue($hero, 'summary'),
            'image_hero' => $this->stringValue($hero, 'imageHero'),
            'image_left' => $this->stringValue($hero, 'imageLeft'),
            'image_right' => $this->stringValue($hero, 'imageRight'),
            'digital_title' => $this->stringValue($digitalServices, 'title'),
            'services' => array_values(array_filter(is_array($digitalServices['services'] ?? null) ? $digitalServices['services'] : [], static fn (mixed $item): bool => is_array($item))),
            'support_cards' => array_values(array_filter(is_array($content['supportCards'] ?? null) ? $content['supportCards'] : [], static fn (mixed $item): bool => is_array($item))),
            'seo_title' => $this->stringValue($seo, 'title'),
            'seo_description' => $this->stringValue($seo, 'description'),
            'seo_image' => $this->stringValue($seo, 'image'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function contentFromForm(array $data): array
    {
        return [
            'hero' => [
                'eyebrow' => (string) ($data['hero_eyebrow'] ?? ''),
                'title' => (string) ($data['hero_title'] ?? ''),
                'summary' => (string) ($data['hero_summary'] ?? ''),
                'imageHero' => (string) ($data['image_hero'] ?? ''),
                'imageLeft' => (string) ($data['image_left'] ?? ''),
                'imageRight' => (string) ($data['image_right'] ?? ''),
            ],
            'digitalServices' => [
                'title' => (string) ($data['digital_title'] ?? ''),
                'services' => array_values(array_filter(is_array($data['services'] ?? null) ? $data['services'] : [], static fn (mixed $item): bool => is_array($item))),
            ],
            'supportCards' => array_values(array_filter(is_array($data['support_cards'] ?? null) ? $data['support_cards'] : [], static fn (mixed $item): bool => is_array($item))),
            'seo' => [
                'title' => (string) ($data['seo_title'] ?? ''),
                'description' => (string) ($data['seo_description'] ?? ''),
                'image' => (string) ($data['seo_image'] ?? ''),
            ],
        ];
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        return [
            'translations' => [
                'ar' => $this->contentFromForm(is_array($state['ar'] ?? null) ? $state['ar'] : []),
                'en' => $this->contentFromForm(is_array($state['en'] ?? null) ? $state['en'] : []),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
