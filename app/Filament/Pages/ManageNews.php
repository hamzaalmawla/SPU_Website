<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
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
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageNews extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $slug = 'manage-news';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.manage-news';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?int $draftVersion = null;

    private NewsServiceInterface $newsService;

    private CmsTargetRegistryInterface $targetRegistry;

    private CmsWorkflowServiceInterface $cmsWorkflowService;

    public function boot(
        NewsServiceInterface $newsService,
        CmsTargetRegistryInterface $targetRegistry,
        CmsWorkflowServiceInterface $cmsWorkflowService,
    ): void {
        $this->newsService = $newsService;
        $this->targetRegistry = $targetRegistry;
        $this->cmsWorkflowService = $cmsWorkflowService;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('manage-pages');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.news');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.items.news');
    }

    public function getTitle(): string
    {
        return __('admin.pages.manage_news');
    }

    public function mount(): void
    {
        $this->loadTarget('news.index');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('News Target')->schema([
                    Select::make('target_key')
                        ->label('Page / Subpage')
                        ->options($this->targetOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (?string $state): mixed => is_string($state) && $state !== '' ? $this->loadTarget($state) : null),
                ]),
                Tabs::make('news_locales')
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
                ->action(fn (array $data) => $this->schedule((string) $data['publish_at'])),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()->action(function (): void {
                $this->unpublish();
            }),
        ];
    }

    public function loadTarget(string $targetKey): void
    {
        $this->assertNewsTarget($targetKey);

        if ($targetKey !== 'news.index') {
            $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);
            $this->form->fill([
                'target_key' => $targetKey,
                'ar_index' => [],
                'en_index' => [],
            ]);

            return;
        }

        $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey);
        $payload = is_array($draftPayload) ? $draftPayload : $this->newsService->getEditablePayload($targetKey);
        $this->draftVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey);

        $this->form->fill([
            'target_key' => $targetKey,
            'ar_index' => is_array($payload['translations']['ar'] ?? null) ? $payload['translations']['ar'] : [],
            'en_index' => is_array($payload['translations']['en'] ?? null) ? $payload['translations']['en'] : [],
        ]);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $draft = $this->cmsWorkflowService->saveDraft($this->currentTargetKey(), $this->payloadFromForm($this->currentFormData()), (int) $user->id, $this->draftVersion);
            $this->draftVersion = $draft->version;

            Notification::make()->title('News draft saved')->success()->send();
        } catch (ConflictException $e) {
            $this->draftVersion = $e->currentVersion;
            Notification::make()->title('Draft conflict detected')->body('Reload this news target before saving again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to save news draft')->body($e->getMessage())->danger()->send();
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
            Notification::make()->title('Draft conflict detected')->body('Reload this news target before previewing again.')->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to create news preview')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('News target published')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Publish failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to publish news target')->body($e->getMessage())->danger()->send();
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

            Notification::make()->title('News target scheduled')->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title('Schedule failed')->body($this->formatValidationErrors($e->errors()))->danger()->persistent()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Failed to schedule news target')->body($e->getMessage())->danger()->send();
        }
    }

    public function unpublish(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $result = $this->cmsWorkflowService->unpublish($this->currentTargetKey(), (int) $user->id);
        $notification = Notification::make()->title($result ? 'News target unpublished' : 'No published news target found');

        ($result ? $notification->success() : $notification->warning())->send();
    }

    /** @return array<string, string> */
    private function targetOptions(): array
    {
        return $this->targetRegistry->forArea('news')
            ->mapWithKeys(fn (CmsTargetDTO $target): array => [$target->key => __($target->labelKey)])
            ->all();
    }

    private function currentTargetKey(): string
    {
        $targetKey = (string) ($this->data['target_key'] ?? 'news.index');
        $this->assertNewsTarget($targetKey);

        return $targetKey;
    }

    private function assertNewsTarget(string $targetKey): void
    {
        $target = $this->targetRegistry->find($targetKey);

        if (! $target instanceof CmsTargetDTO || $target->area !== 'news') {
            throw new \InvalidArgumentException('Unsupported news target.');
        }
    }

    /** @param array<string, mixed> $state */
    private function payloadFromForm(array $state): array
    {
        if (($state['target_key'] ?? null) === 'news.index') {
            $arabic = is_array($state['ar_index'] ?? null) ? $this->normalizeIndexPayload($state['ar_index']) : [];
            $english = is_array($state['en_index'] ?? null) ? $this->normalizeIndexPayload($state['en_index']) : [];

            return [
                'translations' => [
                    'ar' => $arabic,
                    'en' => $english,
                ],
            ];
        }

        throw new \InvalidArgumentException('This news target form will be structured next. Select the News landing page for now.');
    }

    /** @return array<string, mixed> */
    private function currentFormData(): array
    {
        return is_array($this->data) ? $this->data : [];
    }

    /** @return array<int, Section> */
    private function payloadFields(string $locale): array
    {
        if ($this->targetKeyForSchema() !== 'news.index') {
            return [
                Section::make('Target Schema Pending')
                    ->description('The News landing page is editable now. Article archive records stay in News Articles; this target will get its own curated shell only if needed.')
                    ->schema([
                        TextInput::make($locale.'_target_pending')->label('Status')->default('Structured form pending for this news target')->disabled(),
                    ]),
            ];
        }

        $prefix = $locale.'_index';

        return [
            Section::make('Hero')->schema([
                TextInput::make($prefix.'.pageTitle')->label('Page Title')->required()->maxLength(160),
                TextInput::make($prefix.'.heroTitle')->label('Hero Title')->required()->maxLength(160),
                Textarea::make($prefix.'.pageDescription')->label('Description')->required()->rows(2)->columnSpanFull(),
                TextInput::make($prefix.'.heroImage')->label('Hero Image')->required()->maxLength(255),
                Repeater::make($prefix.'.heroLinks')
                    ->label('Hero Links')
                    ->schema([
                        TextInput::make('id')->required()->maxLength(80),
                        TextInput::make('label')->required()->maxLength(120),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable()
                    ->collapsible()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Sections')->schema([
                TextInput::make($prefix.'.lastNewsTitle')->label('Last News Title')->required()->maxLength(160),
                TextInput::make($prefix.'.lastNewsViewAllLabel')->label('Last News View All')->required()->maxLength(160),
                TextInput::make($prefix.'.announcementsTitle')->label('Announcements Title')->required()->maxLength(160),
                TextInput::make($prefix.'.announcementsViewAllLabel')->label('Announcements View All')->required()->maxLength(160),
                TextInput::make($prefix.'.eventsTitle')->label('Events Title')->required()->maxLength(160),
                TextInput::make($prefix.'.eventsViewAllLabel')->label('Events View All')->required()->maxLength(160),
                TextInput::make($prefix.'.exploreMoreTitle')->label('Explore More Title')->required()->maxLength(160),
            ])->columns(2),

            Section::make('Cards and Labels')->schema([
                TextInput::make($prefix.'.archiveTitle')->label('Archive Card Title')->required()->maxLength(160),
                TextInput::make($prefix.'.archiveCta')->label('Archive Card CTA')->required()->maxLength(120),
                TextInput::make($prefix.'.announcementsCardTitle')->label('Announcements Card Title')->required()->maxLength(160),
                TextInput::make($prefix.'.announcementsCardCta')->label('Announcements Card CTA')->required()->maxLength(120),
                TextInput::make($prefix.'.readMoreLabel')->label('Read More Label')->required()->maxLength(80),
                TextInput::make($prefix.'.viewDetailsLabel')->label('View Details Label')->required()->maxLength(80),
                TextInput::make($prefix.'.newLabel')->label('New Badge')->required()->maxLength(80),
                TextInput::make($prefix.'.newsFallbackCategory')->label('News Fallback Category')->required()->maxLength(120),
                TextInput::make($prefix.'.universityNewsFallbackCategory')->label('University News Fallback Category')->required()->maxLength(120),
                Textarea::make($prefix.'.emptyAnnouncements')->label('Empty Announcements Text')->required()->rows(2)->columnSpanFull(),
            ])->columns(2),
        ];
    }

    private function targetKeyForSchema(): string
    {
        return is_string($this->data['target_key'] ?? null) && $this->data['target_key'] !== '' ? $this->data['target_key'] : 'news.index';
    }

    /** @return array<string, mixed> */
    private function normalizeIndexPayload(array $payload): array
    {
        $payload['title'] = (string) ($payload['pageTitle'] ?? ($payload['title'] ?? ''));
        $payload['headline'] = (string) ($payload['heroTitle'] ?? ($payload['headline'] ?? ''));
        $payload['summary'] = (string) ($payload['pageDescription'] ?? ($payload['summary'] ?? ''));

        return $payload;
    }

    /** @param array<string, array<int, string>> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return collect($errors)->flatten()->implode(PHP_EOL);
    }
}
