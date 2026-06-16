<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Page\EServicesPageServiceInterface;
use App\DTOs\EServices\EServicesPageContentDTO;
use App\Models\User\User;
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

class ManageEServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'E-Services Page';

    protected static ?string $navigationGroup = 'E-Services';

    protected static ?string $title = 'Manage E-Services Page';

    protected static ?string $slug = 'manage-e-services-page';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-e-services-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private EServicesPageServiceInterface $eServicesPageService;

    public function boot(EServicesPageServiceInterface $eServicesPageService): void
    {
        $this->eServicesPageService = $eServicesPageService;
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
                ->label('Save E-Services Page')
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
                $this->eServicesPageService->updatePage(
                    $locale,
                    $this->contentFromForm(is_array($state[$locale] ?? null) ? $state[$locale] : []),
                    (int) $user->id,
                );
            }

            Notification::make()->title('E-Services page saved successfully')->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save E-Services page')->danger()->send();
        }
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
    private function formData(string $locale): array
    {
        $content = $this->eServicesPageService->getContent($locale);

        return [
            'hero_eyebrow' => $content->hero['eyebrow'],
            'hero_title' => $content->hero['title'],
            'hero_summary' => $content->hero['summary'],
            'image_hero' => $content->hero['imageHero'],
            'image_left' => $content->hero['imageLeft'],
            'image_right' => $content->hero['imageRight'],
            'digital_title' => $content->digitalServices['title'],
            'services' => $content->digitalServices['services'],
            'support_cards' => $content->supportCards,
            'seo_title' => $content->seoTitle,
            'seo_description' => $content->seoDescription,
            'seo_image' => $content->seoImage,
        ];
    }

    /** @param array<string, mixed> $data */
    private function contentFromForm(array $data): EServicesPageContentDTO
    {
        return new EServicesPageContentDTO(
            hero: [
                'eyebrow' => (string) ($data['hero_eyebrow'] ?? ''),
                'title' => (string) ($data['hero_title'] ?? ''),
                'summary' => (string) ($data['hero_summary'] ?? ''),
                'imageHero' => (string) ($data['image_hero'] ?? ''),
                'imageLeft' => (string) ($data['image_left'] ?? ''),
                'imageRight' => (string) ($data['image_right'] ?? ''),
            ],
            digitalServices: [
                'title' => (string) ($data['digital_title'] ?? ''),
                'services' => array_values(array_filter(is_array($data['services'] ?? null) ? $data['services'] : [], static fn (mixed $item): bool => is_array($item))),
            ],
            supportCards: array_values(array_filter(is_array($data['support_cards'] ?? null) ? $data['support_cards'] : [], static fn (mixed $item): bool => is_array($item))),
            seoTitle: (string) ($data['seo_title'] ?? ''),
            seoDescription: (string) ($data['seo_description'] ?? ''),
            seoImage: (string) ($data['seo_image'] ?? ''),
        );
    }
}
