<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\ContactPageServiceInterface;
use App\DTOs\ContactPageContentDTO;
use App\Models\User;
use Filament\Actions\Action;
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

class ManageContactPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Contact Page';

    protected static ?string $navigationGroup = 'Contact';

    protected static ?string $title = 'Manage Contact Page';

    protected static ?string $slug = 'manage-contact-page';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-contact-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private ContactPageServiceInterface $contactPageService;

    public function boot(ContactPageServiceInterface $contactPageService): void
    {
        $this->contactPageService = $contactPageService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public function mount(): void
    {
        $this->form->fill([
            'ar' => $this->formData('ar'),
            'en' => $this->formData('en'),
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
                ->label('Save Contact Page')
                ->icon('heroicon-o-check')
                ->color('success')
                ->action(function (): void {
                    $this->save();
                }),
        ];
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $state = $this->form->getState();

        try {
            foreach (['ar', 'en'] as $locale) {
                $this->contactPageService->updatePage(
                    $locale,
                    $this->contentFromForm(is_array($state[$locale] ?? null) ? $state[$locale] : []),
                    (int) $user->id,
                );
            }

            Notification::make()
                ->title('Contact page saved successfully')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Failed to save contact page')
                ->body('Please review the contact page fields and try again.')
                ->danger()
                ->send();
        }
    }

    private function localeTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Section::make('Hero')->schema([
                TextInput::make("{$locale}.hero_title")->label('Hero Title')->required()->maxLength(160),
                TextInput::make("{$locale}.hero_bg_image")->label('Hero Background Image')->required()->maxLength(255),
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
                TextInput::make("{$locale}.phone_icon")->label('Phone Icon')->required()->maxLength(255),
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
                        TextInput::make('icon')->required()->maxLength(255),
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
                TextInput::make("{$locale}.seo_image")->label('SEO Image')->required()->maxLength(255),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(string $locale): array
    {
        $content = $this->contactPageService->getContent($locale);

        return [
            'hero_title' => $content->hero['title'],
            'hero_bg_image' => $content->hero['bgImage'],
            'form_title' => $content->form['title'],
            'field_name_label' => $content->form['fields']['name']['label'],
            'field_email_label' => $content->form['fields']['email']['label'],
            'field_subject_label' => $content->form['fields']['subject']['label'],
            'field_message_label' => $content->form['fields']['message']['label'],
            'submit_label' => $content->form['submit'],
            'info_title' => $content->info['title'],
            'phone_label' => $content->info['callUs']['label'],
            'phone_value' => $content->info['callUs']['value'],
            'phone_icon' => $content->info['callUs']['icon'],
            'address_label' => $content->info['address']['label'],
            'address_value' => $content->info['address']['value'],
            'email_label' => $content->info['emailUs']['label'],
            'email_value' => $content->info['emailUs']['value'],
            'hours_label' => $content->info['officeHours']['label'],
            'hours_value' => $content->info['officeHours']['value'],
            'socials_title' => $content->socialsTitle,
            'socials' => $content->socials,
            'location_title' => $content->location['title'],
            'location_button' => $content->location['button'],
            'map_url' => $content->location['mapUrl'],
            'embed_url' => $content->location['embedUrl'],
            'seo_title' => $content->seoTitle,
            'seo_description' => $content->seoDescription,
            'seo_image' => $content->seoImage,
        ];
    }

    /** @param array<string, mixed> $data */
    private function contentFromForm(array $data): ContactPageContentDTO
    {
        return new ContactPageContentDTO(
            hero: ['title' => (string) ($data['hero_title'] ?? ''), 'bgImage' => (string) ($data['hero_bg_image'] ?? '')],
            info: [
                'title' => (string) ($data['info_title'] ?? ''),
                'callUs' => ['label' => (string) ($data['phone_label'] ?? ''), 'value' => (string) ($data['phone_value'] ?? ''), 'icon' => (string) ($data['phone_icon'] ?? '')],
                'address' => ['label' => (string) ($data['address_label'] ?? ''), 'value' => (string) ($data['address_value'] ?? ''), 'icon' => '/images/icon-map-outline.svg'],
                'emailUs' => ['label' => (string) ($data['email_label'] ?? ''), 'value' => (string) ($data['email_value'] ?? ''), 'icon' => '/images/icon-envelope-outline.svg'],
                'officeHours' => ['label' => (string) ($data['hours_label'] ?? ''), 'value' => (string) ($data['hours_value'] ?? ''), 'icon' => '/images/time.svg'],
            ],
            socialsTitle: (string) ($data['socials_title'] ?? ''),
            socials: array_values(array_filter(is_array($data['socials'] ?? null) ? $data['socials'] : [], static fn (mixed $item): bool => is_array($item))),
            form: [
                'title' => (string) ($data['form_title'] ?? ''),
                'fields' => [
                    'name' => ['label' => (string) ($data['field_name_label'] ?? '')],
                    'email' => ['label' => (string) ($data['field_email_label'] ?? '')],
                    'subject' => ['label' => (string) ($data['field_subject_label'] ?? '')],
                    'message' => ['label' => (string) ($data['field_message_label'] ?? '')],
                ],
                'submit' => (string) ($data['submit_label'] ?? ''),
            ],
            location: [
                'title' => (string) ($data['location_title'] ?? ''),
                'button' => (string) ($data['location_button'] ?? ''),
                'mapUrl' => (string) ($data['map_url'] ?? ''),
                'embedUrl' => (string) ($data['embed_url'] ?? ''),
            ],
            seoTitle: (string) ($data['seo_title'] ?? ''),
            seoDescription: (string) ($data['seo_description'] ?? ''),
            seoImage: (string) ($data['seo_image'] ?? ''),
        );
    }
}
