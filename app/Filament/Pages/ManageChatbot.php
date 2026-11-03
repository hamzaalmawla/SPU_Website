<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\Chatbot\ChatbotKnowledgeServiceInterface;
use App\Contracts\Chatbot\ChatbotServiceInterface;
use App\Contracts\Chatbot\ChatbotSettingsServiceInterface;
use App\Contracts\Chatbot\ChatbotUsageServiceInterface;
use App\DTOs\Chatbot\ChatbotDocumentDTO;
use App\DTOs\Chatbot\ChatbotPassageDTO;
use App\DTOs\Chatbot\ChatbotQuestionDTO;
use App\DTOs\Chatbot\ChatbotSettingsDTO;
use App\DTOs\Chatbot\ChatbotSourceDTO;
use App\Support\Chatbot\ExecutionTime;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Administration › AI assistant: the knowledge files the public assistant
 * answers from, its settings and OpenAI key, usage and cost, and a test box.
 *
 * Opening needs chatbot.view; every change needs chatbot.update. All reads
 * and writes go through the chatbot service contracts.
 */
class ManageChatbot extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'ai-assistant';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.manage-chatbot';

    /** @var array<string, mixed>|null */
    public ?array $testResult = null;

    private ChatbotKnowledgeServiceInterface $knowledge;

    private ChatbotSettingsServiceInterface $settings;

    private ChatbotUsageServiceInterface $usage;

    private ChatbotServiceInterface $chatbot;

    public function boot(
        ChatbotKnowledgeServiceInterface $knowledge,
        ChatbotSettingsServiceInterface $settings,
        ChatbotUsageServiceInterface $usage,
        ChatbotServiceInterface $chatbot,
    ): void {
        $this->knowledge = $knowledge;
        $this->settings = $settings;
        $this->usage = $usage;
        $this->chatbot = $chatbot;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('chatbot.view');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_chatbot.navigation');
    }

    public function getTitle(): string
    {
        return __('admin_chatbot.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin_chatbot.subtitle');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $settings = $this->settings->get();
        $stats = $this->knowledge->stats();

        $reasons = [];
        if (! (bool) config('chatbot.enabled', true)) {
            $reasons[] = __('admin_chatbot.reasons.env');
        }
        if (! $settings->enabled) {
            $reasons[] = __('admin_chatbot.reasons.disabled');
        }
        if (! $settings->hasApiKey) {
            $reasons[] = __('admin_chatbot.reasons.no_key');
        }
        if ($stats->searchableDocuments === 0) {
            $reasons[] = __('admin_chatbot.reasons.no_documents');
        }

        return [
            'settings' => $settings,
            'stats' => $stats,
            'usage' => $this->usage->summary(),
            'documents' => $this->knowledge->documents(),
            'reasons' => $reasons,
            'canUpdate' => $this->canUpdate(),
            'modelLabel' => (string) (((array) config('chatbot.models', []))[$settings->model]['label'] ?? $settings->model),
        ];
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            $this->uploadDocumentsAction(),
            $this->addTextAction(),
            $this->syncWebsiteAction(),
            $this->testAction(),
            $this->settingsAction(),
            $this->reindexStaleAction(),
        ];
    }

    /* ── Header actions ────────────────────────────────────────────────── */

    public function uploadDocumentsAction(): Action
    {
        return Action::make('uploadDocuments')
            ->label(__('admin_chatbot.actions.upload'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->visible(fn (): bool => $this->canUpdate())
            ->modalHeading(__('admin_chatbot.actions.upload_heading'))
            ->modalDescription(__('admin_chatbot.actions.upload_description'))
            ->modalSubmitActionLabel(__('admin_chatbot.actions.upload_submit'))
            ->form([
                FileUpload::make('files')
                    ->label(__('admin_chatbot.actions.files'))
                    ->helperText(__('admin_chatbot.actions.files_help'))
                    ->multiple()
                    ->maxFiles(10)
                    ->disk((string) config('chatbot.knowledge.disk', 'local'))
                    ->directory('chatbot/uploads')
                    ->visibility('private')
                    // Browsers report .md and .csv inconsistently; the service
                    // checks the extension and the contents either way.
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/zip',
                        'text/plain',
                        'text/markdown',
                        'text/x-markdown',
                        'text/csv',
                        'application/csv',
                        'application/vnd.ms-excel',
                    ])
                    ->maxSize((int) config('chatbot.knowledge.max_upload_kb', 12288))
                    ->storeFileNamesIn('names')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->authorizeUpdate();
                ExecutionTime::atLeast(600);

                $names = is_array($data['names'] ?? null) ? $data['names'] : [];
                $indexed = 0;

                foreach ((array) ($data['files'] ?? []) as $path) {
                    if (! is_string($path) || $path === '') {
                        continue;
                    }
                    $name = is_string($names[$path] ?? null) ? $names[$path] : basename($path);

                    try {
                        $document = $this->knowledge->addUpload($path, $name, null, $this->actorId());
                    } catch (InvalidArgumentException $exception) {
                        $this->failure($name, __($exception->getMessage()));

                        continue;
                    }

                    if ($document->status === 'ready') {
                        $indexed++;
                    } else {
                        $this->failure($name, (string) $document->error);
                    }
                }

                if ($indexed > 0) {
                    Notification::make()->title(__('admin_chatbot.notifications.uploaded', ['count' => $indexed]))->success()->send();
                }
            });
    }

    private function addTextAction(): Action
    {
        return Action::make('addText')
            ->label(__('admin_chatbot.actions.add_text'))
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate())
            ->modalHeading(__('admin_chatbot.actions.add_text_heading'))
            ->modalDescription(__('admin_chatbot.actions.add_text_description'))
            ->modalWidth(MaxWidth::ThreeExtraLarge)
            ->form([
                TextInput::make('title')
                    ->label(__('admin_chatbot.actions.text_title'))
                    ->helperText(__('admin_chatbot.actions.text_title_help'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('text')
                    ->label(__('admin_chatbot.actions.text_body'))
                    ->helperText(__('admin_chatbot.actions.text_body_help'))
                    ->rows(14)
                    ->required()
                    ->minLength(20)
                    ->maxLength(200_000)
                    ->extraInputAttributes(['dir' => 'auto']),
            ])
            ->action(function (array $data): void {
                $this->authorizeUpdate();

                try {
                    $document = $this->knowledge->addText((string) $data['title'], (string) $data['text'], $this->actorId());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title(__($exception->getMessage()))->danger()->send();

                    return;
                }

                $document->status === 'ready'
                    ? Notification::make()->title(__('admin_chatbot.notifications.text_added'))->success()->send()
                    : $this->failure($document->title, (string) $document->error);
            });
    }

    private function settingsAction(): Action
    {
        return Action::make('editSettings')
            ->label(__('admin_chatbot.actions.settings'))
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate())
            ->slideOver()
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->modalHeading(__('admin_chatbot.actions.settings_heading'))
            ->modalSubmitActionLabel(__('admin_chatbot.actions.save'))
            ->fillForm(function (): array {
                $settings = $this->settings->get();

                return [
                    'enabled' => $settings->enabled,
                    'model' => $settings->model,
                    'fallback_model' => $settings->fallbackModel ?? 'none',
                    'api_key' => null,
                    'remove_api_key' => false,
                    'assistant_name_ar' => $settings->assistantNameAr,
                    'assistant_name_en' => $settings->assistantNameEn,
                    'welcome_ar' => $settings->welcomeAr,
                    'welcome_en' => $settings->welcomeEn,
                    'suggestions_ar' => $settings->suggestionsAr,
                    'suggestions_en' => $settings->suggestionsEn,
                    'extra_instructions' => $settings->extraInstructions,
                    'daily_question_limit' => $settings->dailyQuestionLimit,
                    'visitor_hourly_limit' => $settings->visitorHourlyLimit,
                    'visitor_daily_limit' => $settings->visitorDailyLimit,
                ];
            })
            ->form(fn (): array => $this->settingsSchema())
            ->action(function (array $data): void {
                $this->authorizeUpdate();

                $settings = new ChatbotSettingsDTO(
                    enabled: (bool) ($data['enabled'] ?? false),
                    model: (string) ($data['model'] ?? ''),
                    hasApiKey: false,
                    apiKeyHint: null,
                    assistantNameAr: (string) ($data['assistant_name_ar'] ?? ''),
                    assistantNameEn: (string) ($data['assistant_name_en'] ?? ''),
                    welcomeAr: (string) ($data['welcome_ar'] ?? ''),
                    welcomeEn: (string) ($data['welcome_en'] ?? ''),
                    suggestionsAr: array_values(array_filter((array) ($data['suggestions_ar'] ?? []), 'is_string')),
                    suggestionsEn: array_values(array_filter((array) ($data['suggestions_en'] ?? []), 'is_string')),
                    extraInstructions: (string) ($data['extra_instructions'] ?? ''),
                    dailyQuestionLimit: (int) ($data['daily_question_limit'] ?? 0),
                    fallbackModel: is_string($data['fallback_model'] ?? null) && $data['fallback_model'] !== 'none' ? $data['fallback_model'] : null,
                    visitorHourlyLimit: (int) ($data['visitor_hourly_limit'] ?? 0),
                    visitorDailyLimit: (int) ($data['visitor_daily_limit'] ?? 0),
                );

                try {
                    $this->settings->save(
                        $settings,
                        is_string($data['api_key'] ?? null) ? $data['api_key'] : null,
                        (bool) ($data['remove_api_key'] ?? false),
                        $this->actorId(),
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title(__($exception->getMessage()))->danger()->send();

                    return;
                }

                Notification::make()->title(__('admin_chatbot.notifications.saved'))->success()->send();
            });
    }

    /** @return list<Section> */
    private function settingsSchema(): array
    {
        $settings = $this->settings->get();
        $models = [];
        foreach ((array) config('chatbot.models', []) as $id => $model) {
            $models[(string) $id] = sprintf('%s — $%s / $%s', $model['label'] ?? $id, $model['input'] ?? '?', $model['output'] ?? '?');
        }

        return [
            Section::make(__('admin_chatbot.settings.section_general'))->schema([
                Toggle::make('enabled')
                    ->label(__('admin_chatbot.settings.enabled'))
                    ->helperText(__('admin_chatbot.settings.enabled_help')),
                Select::make('model')
                    ->label(__('admin_chatbot.settings.model'))
                    ->helperText(__('admin_chatbot.settings.model_help'))
                    ->options($models)
                    ->native(false)
                    ->required(),
                Select::make('fallback_model')
                    ->label(__('admin_chatbot.settings.fallback_model'))
                    ->helperText(__('admin_chatbot.settings.fallback_model_help'))
                    ->options(['none' => __('admin_chatbot.settings.fallback_none')] + $models)
                    ->native(false)
                    ->required(),
                Placeholder::make('free_tier_note')
                    ->hiddenLabel()
                    ->content(__('admin_chatbot.settings.free_tier_note')),
            ]),
            Section::make(__('admin_chatbot.settings.section_key'))->schema([
                Placeholder::make('api_key_status')
                    ->hiddenLabel()
                    ->content($settings->hasApiKey
                        ? __('admin_chatbot.settings.api_key_current', ['hint' => (string) $settings->apiKeyHint])
                        : __('admin_chatbot.settings.api_key_none')),
                TextInput::make('api_key')
                    ->label(__('admin_chatbot.settings.api_key'))
                    ->helperText(__('admin_chatbot.settings.api_key_help'))
                    ->password()
                    ->revealable()
                    ->autocomplete('off')
                    ->maxLength(300)
                    ->extraInputAttributes(['dir' => 'ltr', 'spellcheck' => 'false']),
                Toggle::make('remove_api_key')
                    ->label(__('admin_chatbot.settings.remove_api_key'))
                    ->visible($settings->hasApiKey),
            ]),
            Section::make(__('admin_chatbot.settings.section_texts'))->columns(2)->schema([
                TextInput::make('assistant_name_ar')->label(__('admin_chatbot.settings.assistant_name_ar'))->required()->maxLength(60)->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('assistant_name_en')->label(__('admin_chatbot.settings.assistant_name_en'))->required()->maxLength(60)->extraInputAttributes(['dir' => 'ltr']),
                Textarea::make('welcome_ar')->label(__('admin_chatbot.settings.welcome_ar'))->rows(3)->required()->maxLength(600)->extraInputAttributes(['dir' => 'rtl']),
                Textarea::make('welcome_en')->label(__('admin_chatbot.settings.welcome_en'))->rows(3)->required()->maxLength(600)->extraInputAttributes(['dir' => 'ltr']),
                TagsInput::make('suggestions_ar')->label(__('admin_chatbot.settings.suggestions_ar'))->placeholder(__('admin_chatbot.settings.suggestion'))->reorderable()->splitKeys(['Enter']),
                TagsInput::make('suggestions_en')->label(__('admin_chatbot.settings.suggestions_en'))->placeholder(__('admin_chatbot.settings.suggestion'))->reorderable()->splitKeys(['Enter']),
            ]),
            Section::make(__('admin_chatbot.settings.section_behaviour'))->schema([
                Textarea::make('extra_instructions')
                    ->label(__('admin_chatbot.settings.extra_instructions'))
                    ->helperText(__('admin_chatbot.settings.extra_instructions_help'))
                    ->rows(4)
                    ->maxLength(2000)
                    ->extraInputAttributes(['dir' => 'auto']),
                TextInput::make('daily_question_limit')
                    ->label(__('admin_chatbot.settings.daily_limit'))
                    ->helperText(__('admin_chatbot.settings.daily_limit_help'))
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1_000_000)
                    ->required(),
            ]),
            Section::make(__('admin_chatbot.settings.section_fair_use'))
                ->description(__('admin_chatbot.settings.fair_use_help', [
                    'minute' => (int) config('chatbot.fair_use.per_minute', 5),
                    'multiplier' => (int) config('chatbot.fair_use.ip_multiplier', 8),
                    'strikes' => (int) config('chatbot.guard.strikes', 4),
                ]))
                ->columns(2)
                ->schema([
                    TextInput::make('visitor_hourly_limit')
                        ->label(__('admin_chatbot.settings.visitor_hourly_limit'))
                        ->numeric()->integer()->minValue(0)->maxValue(1000)->required(),
                    TextInput::make('visitor_daily_limit')
                        ->label(__('admin_chatbot.settings.visitor_daily_limit'))
                        ->numeric()->integer()->minValue(0)->maxValue(5000)->required(),
                ]),
        ];
    }

    private function syncWebsiteAction(): Action
    {
        return Action::make('syncWebsite')
            ->label(__('admin_chatbot.actions.sync_website'))
            ->icon('heroicon-o-globe-alt')
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate() && (bool) config('chatbot.website.enabled', true))
            ->requiresConfirmation()
            ->modalHeading(__('admin_chatbot.actions.sync_website_heading'))
            ->modalDescription(__('admin_chatbot.actions.sync_website_description'))
            ->modalSubmitActionLabel(__('admin_chatbot.actions.sync_website_submit'))
            ->action(function (): void {
                $this->authorizeUpdate();
                $result = $this->knowledge->syncWebsite($this->actorId());

                if (! $result->reachable) {
                    Notification::make()->title(__('admin_chatbot.notifications.website_unreachable'))->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin_chatbot.notifications.website_synced', [
                        'pages' => $result->pages, 'added' => $result->added, 'updated' => $result->updated,
                        'unchanged' => $result->unchanged, 'removed' => $result->removed, 'failed' => $result->failed,
                    ]))
                    ->color($result->failed > 0 ? 'warning' : 'success')
                    ->success()
                    ->send();
            });
    }

    private function testAction(): Action
    {
        return Action::make('testAssistant')
            ->label(__('admin_chatbot.actions.test'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate())
            ->modalHeading(__('admin_chatbot.actions.test_heading'))
            ->modalDescription(__('admin_chatbot.actions.test_description'))
            ->modalSubmitActionLabel(__('admin_chatbot.actions.test_submit'))
            ->form([
                Textarea::make('question')
                    ->label(__('admin_chatbot.actions.test_question'))
                    ->rows(3)
                    ->required()
                    ->maxLength((int) config('chatbot.conversation.max_message_characters', 1000))
                    ->extraInputAttributes(['dir' => 'auto']),
                ToggleButtons::make('locale')
                    ->label(__('admin_chatbot.actions.test_language'))
                    ->options(['ar' => __('admin_chatbot.test.ar'), 'en' => __('admin_chatbot.test.en')])
                    ->default(app()->getLocale() === 'en' ? 'en' : 'ar')
                    ->inline()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->authorizeUpdate();
                $question = trim((string) ($data['question'] ?? ''));
                $locale = ($data['locale'] ?? 'ar') === 'en' ? 'en' : 'ar';

                $passages = [];
                $apiKey = $this->settings->apiKey();
                if ($apiKey !== null) {
                    try {
                        $passages = $this->knowledge->retrieve($question, $apiKey)->passages;
                    } catch (\Throwable) {
                        $passages = [];
                    }
                }

                $reply = $this->chatbot->ask(new ChatbotQuestionDTO(locale: $locale, message: $question, adminTest: true));

                $this->testResult = [
                    'question' => $question,
                    'status' => $reply->status,
                    'reason' => $reply->reason,
                    'answer' => $reply->answer,
                    'sources' => array_map(static fn (ChatbotSourceDTO $source): array => ['title' => $source->title, 'pages' => $source->pages], $reply->sources),
                    'passages' => array_map(static fn (ChatbotPassageDTO $passage): array => [
                        'title' => $passage->documentTitle,
                        'page' => $passage->page,
                        'heading' => $passage->heading,
                        'score' => $passage->score,
                        'excerpt' => mb_substr($passage->content, 0, 420),
                    ], $passages),
                ];

                $this->replaceMountedAction('testAssistantResult');
            });
    }

    public function testAssistantResultAction(): Action
    {
        return Action::make('testAssistantResult')
            ->visible(fn (): bool => $this->canUpdate())
            ->modalHeading(__('admin_chatbot.actions.test_heading'))
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin_chatbot.actions.close'))
            ->modalContent(fn (): View => view('filament.chatbot.test-result', ['result' => $this->testResult ?? []]));
    }

    private function reindexStaleAction(): Action
    {
        return Action::make('reindexStale')
            ->label(__('admin_chatbot.actions.reindex_all'))
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(function (): bool {
                if (! $this->canUpdate()) {
                    return false;
                }
                $stats = $this->knowledge->stats();

                return $stats->failedDocuments + $stats->staleDocuments > 0;
            })
            ->requiresConfirmation()
            ->modalDescription(__('admin_chatbot.actions.reindex_all_description'))
            ->action(function (): void {
                $this->authorizeUpdate();
                $count = $this->knowledge->reindexStale($this->actorId());
                Notification::make()->title(__('admin_chatbot.notifications.reindexed_all', ['count' => $count]))->success()->send();
            });
    }

    /* ── Row actions (rendered in the documents table) ─────────────────── */

    public function previewDocumentAction(): Action
    {
        return Action::make('previewDocument')
            ->label(__('admin_chatbot.actions.preview'))
            ->icon('heroicon-o-eye')
            ->iconButton()
            ->color('gray')
            ->modalHeading(fn (array $arguments): string => __('admin_chatbot.actions.preview_heading').' — '.($this->document((int) ($arguments['document'] ?? 0))?->title ?? ''))
            ->modalWidth(MaxWidth::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin_chatbot.actions.close'))
            ->modalContent(fn (array $arguments): View => view('filament.chatbot.preview', [
                'text' => $this->knowledge->preview((int) ($arguments['document'] ?? 0), 6000),
            ]));
    }

    public function renameDocumentAction(): Action
    {
        return Action::make('renameDocument')
            ->label(__('admin_chatbot.actions.rename'))
            ->icon('heroicon-o-pencil')
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate())
            ->modalWidth(MaxWidth::Large)
            ->fillForm(fn (array $arguments): array => ['title' => $this->document((int) ($arguments['document'] ?? 0))?->title])
            ->form([
                TextInput::make('title')->label(__('admin_chatbot.actions.text_title'))->required()->maxLength(255),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->authorizeUpdate();
                $this->knowledge->rename((int) ($arguments['document'] ?? 0), (string) $data['title'], $this->actorId());
                Notification::make()->title(__('admin_chatbot.notifications.renamed'))->success()->send();
            });
    }

    public function reindexDocumentAction(): Action
    {
        return Action::make('reindexDocument')
            ->label(__('admin_chatbot.actions.reindex'))
            ->icon('heroicon-o-arrow-path')
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate())
            ->action(function (array $arguments): void {
                $this->authorizeUpdate();
                $document = $this->knowledge->reindex((int) ($arguments['document'] ?? 0), $this->actorId());

                $document->status === 'ready'
                    ? Notification::make()->title(__('admin_chatbot.notifications.reindexed'))->success()->send()
                    : Notification::make()->title(__('admin_chatbot.notifications.reindex_failed', ['reason' => (string) $document->error]))->danger()->send();
            });
    }

    public function toggleDocumentAction(): Action
    {
        return Action::make('toggleDocument')
            ->label(fn (array $arguments): string => ($arguments['active'] ?? false) ? __('admin_chatbot.actions.disable') : __('admin_chatbot.actions.enable'))
            ->icon(fn (array $arguments): string => ($arguments['active'] ?? false) ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->canUpdate())
            ->action(function (array $arguments): void {
                $this->authorizeUpdate();
                $id = (int) ($arguments['document'] ?? 0);
                $active = ! ($this->document($id)?->isActive ?? true);
                $this->knowledge->setActive($id, $active, $this->actorId());
                Notification::make()->title(__($active ? 'admin_chatbot.notifications.enabled' : 'admin_chatbot.notifications.disabled'))->success()->send();
            });
    }

    public function deleteDocumentAction(): Action
    {
        return Action::make('deleteDocument')
            ->label(__('admin_chatbot.actions.delete'))
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->visible(fn (): bool => $this->canUpdate())
            ->requiresConfirmation()
            ->modalHeading(__('admin_chatbot.actions.delete_heading'))
            ->modalDescription(__('admin_chatbot.actions.delete_description'))
            ->action(function (array $arguments): void {
                $this->authorizeUpdate();
                $this->knowledge->delete((int) ($arguments['document'] ?? 0), $this->actorId());
                Notification::make()->title(__('admin_chatbot.notifications.deleted'))->success()->send();
            });
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    private function document(int $id): ?ChatbotDocumentDTO
    {
        return $this->knowledge->documents()->first(fn (ChatbotDocumentDTO $document): bool => $document->id === $id);
    }

    private function canUpdate(): bool
    {
        return Gate::allows('chatbot.update');
    }

    private function authorizeUpdate(): void
    {
        abort_unless($this->canUpdate(), 403);
    }

    private function actorId(): int
    {
        return (int) Auth::id();
    }

    private function failure(string $name, string $reason): void
    {
        Notification::make()
            ->title(__('admin_chatbot.notifications.upload_failed', ['name' => $name, 'reason' => $reason]))
            ->danger()
            ->persistent()
            ->send();
    }
}
