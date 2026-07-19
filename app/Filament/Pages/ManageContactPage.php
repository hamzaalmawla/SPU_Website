<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
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
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageContactPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $slug = 'manage-contact-page';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-contact-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private ContactPageServiceInterface $contactPageService;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        ContactPageServiceInterface $contactPageService,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->contactPageService = $contactPageService;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.contact');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.contact_page');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_contact_page');
    }

    public function mount(): void
    {
        $userId = (int) auth()->id();
        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload('contact', $userId);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion('contact', $userId);

        $this->form->fill([
            'ar' => $this->formData('ar', $draftPayload),
            'en' => $this->formData('en', $draftPayload),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('contact_page_locales')
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
                'contact',
                $this->payloadFromForm($this->currentFormData()),
                (int) $user->id,
                $this->draftVersion,
            );
            $this->draftVersion = $draft->version;

            Notification::make()
                ->title('Contact page draft saved')
                ->success()
                ->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;

            Notification::make()
                ->title('Draft conflict detected')
                ->body('This contact page draft changed elsewhere. Reload the page before saving again.')
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to save contact page draft')
                ->danger()
                ->send();
        }
    }

    public function publish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('contact', $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->publish('contact', (int) $user->id);

            Notification::make()->title('Contact page published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Publish failed')
                ->body($this->formatValidationErrors($e->errors()))
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish contact page')->danger()->send();
        }
    }

    public function openPreview(string $locale): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('contact', $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $preview = $this->cmsWorkflowService->preview('contact', $locale, (int) $user->id);

            $this->redirect($preview->previewUrl);
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;

            Notification::make()
                ->title('Draft conflict detected')
                ->body('This contact page draft changed elsewhere. Reload the page before previewing again.')
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create contact page preview')->danger()->send();
        }
    }

    public function schedule(string $publishAt): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft('contact', $this->payloadFromForm($this->form->getState()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->cmsWorkflowService->schedule('contact', new \DateTimeImmutable($publishAt), (int) $user->id);

            Notification::make()->title('Contact page scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Schedule failed')
                ->body($this->formatValidationErrors($e->errors()))
                ->danger()
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule contact page')->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $result = $this->cmsWorkflowService->unpublish('contact', (int) $user->id);
        $notification = Notification::make()->title($result ? 'Contact page unpublished' : 'No published contact page found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    private function localeTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Section::make('Hero')->schema([
                TextInput::make("{$locale}.hero_title")->label('Hero Title')->required()->maxLength(160),
                MediaPicker::image("{$locale}.hero_bg_image", 'Hero Background Image', true),
            ])->columns(2),

            Section::make('Form')->schema([
                TextInput::make("{$locale}.form_title")->label('Form Title')->required()->maxLength(160),
                TextInput::make("{$locale}.field_name_label")->label('Name Label')->required()->maxLength(80),
                TextInput::make("{$locale}.field_email_label")->label('Email Label')->required()->maxLength(80),
                TextInput::make("{$locale}.field_subject_label")->label('Subject Label')->required()->maxLength(80),
                TextInput::make("{$locale}.field_message_label")->label('Message Label')->required()->maxLength(80),
                TextInput::make("{$locale}.submit_label")->label('Submit Button')->required()->maxLength(80),
            ])->columns(2),

            Section::make('Contact Information')->schema([
                TextInput::make("{$locale}.info_title")->label('Info Title')->required()->maxLength(160),
                TextInput::make("{$locale}.phone_label")->label('Phone Label')->required()->maxLength(80),
                TextInput::make("{$locale}.phone_value")->label('Phone Value')->required()->maxLength(120),
                MediaPicker::icon("{$locale}.phone_icon", 'Phone Icon', true),
                TextInput::make("{$locale}.address_label")->label('Address Label')->required()->maxLength(80),
                Textarea::make("{$locale}.address_value")->label('Address Value')->required()->rows(3),
                TextInput::make("{$locale}.email_label")->label('Email Label')->required()->maxLength(80),
                TextInput::make("{$locale}.email_value")->label('Email Value')->required()->maxLength(160),
                TextInput::make("{$locale}.hours_label")->label('Office Hours Label')->required()->maxLength(80),
                TextInput::make("{$locale}.hours_value")->label('Office Hours Value')->required()->maxLength(160),
            ])->columns(2),

            Section::make('Social Links')->schema([
                TextInput::make("{$locale}.socials_title")->label('Socials Title')->required()->maxLength(120),
                Repeater::make("{$locale}.socials")
                    ->label('Social Links')
                    ->schema([
                        MediaPicker::icon('icon', 'Icon', true),
                        TextInput::make('url')->required()->url()->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable(),
            ]),

            Section::make('Campus Map')->schema([
                TextInput::make("{$locale}.location_title")->label('Map Title')->required()->maxLength(160),
                TextInput::make("{$locale}.location_button")->label('Map Button')->required()->maxLength(120),
                TextInput::make("{$locale}.map_url")->label('External Map URL')->required()->url()->maxLength(500),
                TextInput::make("{$locale}.embed_url")->label('Embed Map URL')->required()->url()->maxLength(1000),
            ])->columns(2),

            Section::make('SEO')->schema([
                TextInput::make("{$locale}.seo_title")->label('SEO Title')->required()->maxLength(180),
                Textarea::make("{$locale}.seo_description")->label('SEO Description')->required()->rows(3),
                MediaPicker::image("{$locale}.seo_image", 'SEO Image', true),
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

        $content = $this->contactPageService->getContent($locale);

        return $this->contentArrayToFormData([
            'hero' => $content->hero,
            'info' => $content->info,
            'socialsTitle' => $content->socialsTitle,
            'socials' => $content->socials,
            'form' => $content->form,
            'location' => $content->location,
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
        $form = is_array($content['form'] ?? null) ? $content['form'] : [];
        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        $info = is_array($content['info'] ?? null) ? $content['info'] : [];
        $location = is_array($content['location'] ?? null) ? $content['location'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return [
            'hero_title' => $this->stringValue($hero, 'title'),
            'hero_bg_image' => $this->stringValue($hero, 'bgImage'),
            'form_title' => $this->stringValue($form, 'title'),
            'field_name_label' => $this->stringValue(is_array($fields['name'] ?? null) ? $fields['name'] : [], 'label'),
            'field_email_label' => $this->stringValue(is_array($fields['email'] ?? null) ? $fields['email'] : [], 'label'),
            'field_subject_label' => $this->stringValue(is_array($fields['subject'] ?? null) ? $fields['subject'] : [], 'label'),
            'field_message_label' => $this->stringValue(is_array($fields['message'] ?? null) ? $fields['message'] : [], 'label'),
            'submit_label' => $this->stringValue($form, 'submit'),
            'info_title' => $this->stringValue($info, 'title'),
            'phone_label' => $this->stringValue(is_array($info['callUs'] ?? null) ? $info['callUs'] : [], 'label'),
            'phone_value' => $this->stringValue(is_array($info['callUs'] ?? null) ? $info['callUs'] : [], 'value'),
            'phone_icon' => $this->stringValue(is_array($info['callUs'] ?? null) ? $info['callUs'] : [], 'icon'),
            'address_label' => $this->stringValue(is_array($info['address'] ?? null) ? $info['address'] : [], 'label'),
            'address_value' => $this->stringValue(is_array($info['address'] ?? null) ? $info['address'] : [], 'value'),
            'email_label' => $this->stringValue(is_array($info['emailUs'] ?? null) ? $info['emailUs'] : [], 'label'),
            'email_value' => $this->stringValue(is_array($info['emailUs'] ?? null) ? $info['emailUs'] : [], 'value'),
            'hours_label' => $this->stringValue(is_array($info['officeHours'] ?? null) ? $info['officeHours'] : [], 'label'),
            'hours_value' => $this->stringValue(is_array($info['officeHours'] ?? null) ? $info['officeHours'] : [], 'value'),
            'socials_title' => $this->stringValue($content, 'socialsTitle'),
            'socials' => array_values(array_filter(is_array($content['socials'] ?? null) ? $content['socials'] : [], static fn (mixed $item): bool => is_array($item))),
            'location_title' => $this->stringValue($location, 'title'),
            'location_button' => $this->stringValue($location, 'button'),
            'map_url' => $this->stringValue($location, 'mapUrl'),
            'embed_url' => $this->stringValue($location, 'embedUrl'),
            'seo_title' => $this->stringValue($seo, 'title'),
            'seo_description' => $this->stringValue($seo, 'description'),
            'seo_image' => $this->stringValue($seo, 'image'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function contentFromForm(array $data): array
    {
        return [
            'hero' => ['title' => (string) ($data['hero_title'] ?? ''), 'bgImage' => (string) ($data['hero_bg_image'] ?? '')],
            'info' => [
                'title' => (string) ($data['info_title'] ?? ''),
                'callUs' => ['label' => (string) ($data['phone_label'] ?? ''), 'value' => (string) ($data['phone_value'] ?? ''), 'icon' => (string) ($data['phone_icon'] ?? '')],
                'address' => ['label' => (string) ($data['address_label'] ?? ''), 'value' => (string) ($data['address_value'] ?? ''), 'icon' => '/images/icon-map-outline.svg'],
                'emailUs' => ['label' => (string) ($data['email_label'] ?? ''), 'value' => (string) ($data['email_value'] ?? ''), 'icon' => '/images/icon-envelope-outline.svg'],
                'officeHours' => ['label' => (string) ($data['hours_label'] ?? ''), 'value' => (string) ($data['hours_value'] ?? ''), 'icon' => '/images/time.svg'],
            ],
            'socialsTitle' => (string) ($data['socials_title'] ?? ''),
            'socials' => array_values(array_filter(is_array($data['socials'] ?? null) ? $data['socials'] : [], static fn (mixed $item): bool => is_array($item))),
            'form' => [
                'title' => (string) ($data['form_title'] ?? ''),
                'fields' => [
                    'name' => ['label' => (string) ($data['field_name_label'] ?? '')],
                    'email' => ['label' => (string) ($data['field_email_label'] ?? '')],
                    'subject' => ['label' => (string) ($data['field_subject_label'] ?? '')],
                    'message' => ['label' => (string) ($data['field_message_label'] ?? '')],
                ],
                'submit' => (string) ($data['submit_label'] ?? ''),
            ],
            'location' => [
                'title' => (string) ($data['location_title'] ?? ''),
                'button' => (string) ($data['location_button'] ?? ''),
                'mapUrl' => (string) ($data['map_url'] ?? ''),
                'embedUrl' => (string) ($data['embed_url'] ?? ''),
            ],
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
