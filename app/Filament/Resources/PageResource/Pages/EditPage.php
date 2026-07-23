<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

use App\Contracts\Page\PageServiceInterface;
use App\Contracts\Shared\PreviewServiceInterface;
use App\DTOs\Page\PageDraftDataDTO;
use App\DTOs\Page\PageDraftDTO;
use App\DTOs\Page\PageMetadataDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\DTOs\Seo\PageSeoInputDTO;
use App\Exceptions\ConflictException;
use App\Filament\Resources\PageResource;
use App\Models\Page\Page;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    private PageServiceInterface $pageService;

    private PreviewServiceInterface $previewService;

    public ?int $draftVersion = null;

    public function boot(PageServiceInterface $pageService, PreviewServiceInterface $previewService): void
    {
        $this->pageService = $pageService;
        $this->previewService = $previewService;
    }

    public function getTitle(): string
    {
        return __('admin.page_resource.headings.edit', ['title' => $this->getRecordTitle()]);
    }

    public function getBreadcrumb(): string
    {
        return __('admin.page_resource.breadcrumbs.edit');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->record;
        Gate::authorize('view', $page);
        $this->draftVersion = $this->pageService->latestEditableDraftVersion((int) $page->getKey());

        $page->load(['translations', 'seoMeta']);

        $arTranslation = $page->translations->firstWhere('locale', 'ar');
        $enTranslation = $page->translations->firstWhere('locale', 'en');
        $arSeo = $page->seoMeta->firstWhere('locale', 'ar');
        $enSeo = $page->seoMeta->firstWhere('locale', 'en');

        // Arabic translation fields
        $data['faculty_scope_slug'] = $page->faculty_scope_slug ?? null;

        $data['ar_title'] = $arTranslation?->title ?? '';
        $data['ar_headline'] = $arTranslation?->headline ?? '';
        $data['ar_subheadline'] = $arTranslation?->subheadline ?? '';
        $data['ar_hero_content'] = $arTranslation?->hero_payload['content'] ?? '';
        $data['ar_body'] = $arTranslation?->body ?? '';
        $data['ar_cta_label'] = $arTranslation?->cta_payload['label'] ?? '';
        $data['ar_cta_url'] = $arTranslation?->cta_payload['url'] ?? '';
        $data['ar_sidebar_content'] = $arTranslation?->sidebar_payload['content'] ?? '';
        $data['ar_excerpt'] = $arTranslation?->excerpt ?? '';

        // English translation fields
        $data['en_title'] = $enTranslation?->title ?? '';
        $data['en_headline'] = $enTranslation?->headline ?? '';
        $data['en_subheadline'] = $enTranslation?->subheadline ?? '';
        $data['en_hero_content'] = $enTranslation?->hero_payload['content'] ?? '';
        $data['en_body'] = $enTranslation?->body ?? '';
        $data['en_cta_label'] = $enTranslation?->cta_payload['label'] ?? '';
        $data['en_cta_url'] = $enTranslation?->cta_payload['url'] ?? '';
        $data['en_sidebar_content'] = $enTranslation?->sidebar_payload['content'] ?? '';
        $data['en_excerpt'] = $enTranslation?->excerpt ?? '';

        // Arabic SEO fields
        $data['ar_seo_meta_title'] = $arSeo?->meta_title ?? '';
        $data['ar_seo_meta_description'] = $arSeo?->meta_description ?? '';
        $data['ar_seo_og_title'] = $arSeo?->og_title ?? '';
        $data['ar_seo_og_description'] = $arSeo?->og_description ?? '';
        $data['ar_seo_og_image'] = $arSeo?->og_image_url ?? '';
        $data['ar_seo_canonical_url'] = $arSeo?->canonical_url ?? '';
        $data['ar_seo_robots'] = $arSeo?->robots ?? '';

        // English SEO fields
        $data['en_seo_meta_title'] = $enSeo?->meta_title ?? '';
        $data['en_seo_meta_description'] = $enSeo?->meta_description ?? '';
        $data['en_seo_og_title'] = $enSeo?->og_title ?? '';
        $data['en_seo_og_description'] = $enSeo?->og_description ?? '';
        $data['en_seo_og_image'] = $enSeo?->og_image_url ?? '';
        $data['en_seo_canonical_url'] = $enSeo?->canonical_url ?? '';
        $data['en_seo_robots'] = $enSeo?->robots ?? '';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        Gate::authorize('update', $record);

        try {
            $this->saveDraftFromFormData($data, 'draft');
        } catch (ConflictException $exception) {
            $this->notifyDraftConflict($exception);
        }

        return $record->refresh();
    }

    // ──────────────────────────────────────────────
    // Header Actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->publicationStatusAction(),
            $this->saveDraftAction(),
            $this->previewAction('ar'),
            $this->previewAction('en'),
            $this->publishAction(),
            $this->scheduleAction(),
            $this->unpublishAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(__('admin.page_resource.actions.save_changes'));
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label(__('admin.page_resource.actions.cancel'));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('admin.page_resource.notifications.changes_saved');
    }

    private function publicationStatusAction(): Action
    {
        /** @var Page $page */
        $page = $this->record;
        $status = is_string($page->status) ? $page->status : null;

        return Action::make('publicationStatus')
            ->label(__('admin.page_resource.publication_status', [
                'status' => PageResource::getPublicationStatusLabel($status),
            ]))
            ->badge()
            ->color(PageResource::getPublicationStatusColor($status))
            ->icon(PageResource::getPublicationStatusIcon($status));
    }

    private function saveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label(__('admin.page_resource.actions.save_draft'))
            ->icon('heroicon-o-document')
            ->color('gray')
            ->action(function (): void {
                $this->saveDraft();
            });
    }

    private function previewAction(string $locale): Action
    {
        return Action::make("preview_{$locale}")
            ->label(__('admin.page_resource.actions.preview', [
                'locale' => __('admin.page_resource.locale_names.'.$locale),
            ]))
            ->icon('heroicon-o-eye')
            ->color('info')
            ->action(function () use ($locale): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('preview', $page);

                /** @var User $user */
                $user = auth()->user();
                try {
                    $this->saveDraftFromFormData($this->form->getState(), 'draft');
                } catch (ConflictException $exception) {
                    $this->notifyDraftConflict($exception);

                    return;
                }

                $preview = $this->previewService->createToken(
                    targetType: 'page',
                    targetId: $page->id,
                    locale: $locale,
                    userId: $user->id,
                );

                $this->redirect($preview->previewUrl);
            });
    }

    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label(__('admin.page_resource.actions.publish'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('admin.page_resource.modals.publish_heading'))
            ->modalDescription(__('admin.page_resource.modals.publish_description'))
            ->action(function (): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('publish', $page);

                /** @var User $user */
                $user = auth()->user();

                $formData = $this->form->getState();
                $validationErrors = $this->publishValidationErrors($formData);

                if ($validationErrors !== []) {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.publish_failed'))
                        ->body($this->formatValidationErrors($validationErrors))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                try {
                    $this->saveDraftFromFormData($formData, 'draft');
                } catch (ConflictException $exception) {
                    $this->notifyDraftConflict($exception);

                    return;
                }

                $result = $this->pageService->publish($page->id, $user->id);

                if ($result) {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.published'))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.publish_failed'))
                        ->body(__('admin.page_resource.notifications.publish_requirements'))
                        ->danger()
                        ->persistent()
                        ->send();
                }

                $this->refreshFormData(array_keys($this->data));
            });
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
            ->label(__('admin.page_resource.actions.schedule'))
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->form([
                DateTimePicker::make('publish_at')
                    ->label(__('admin.page_resource.schedule.publish_at'))
                    ->required()
                    ->minDate(now())
                    ->native(false),
            ])
            ->action(function (array $data): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('publish', $page);

                /** @var User $user */
                $user = auth()->user();

                $formData = $this->form->getState();
                $validationErrors = $this->publishValidationErrors($formData);

                if ($validationErrors !== []) {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.schedule_failed'))
                        ->body($this->formatValidationErrors($validationErrors))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                try {
                    $this->saveDraftFromFormData($formData, 'scheduled', (string) $data['publish_at']);
                } catch (ConflictException $exception) {
                    $this->notifyDraftConflict($exception);

                    return;
                }

                $result = $this->pageService->schedulePublish(
                    $page->id,
                    new \DateTimeImmutable($data['publish_at']),
                    $user->id,
                );

                if ($result) {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.scheduled'))
                        ->body(__('admin.page_resource.notifications.scheduled_for', ['date' => $data['publish_at']]))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.schedule_failed'))
                        ->danger()
                        ->send();
                }

                $this->refreshFormData(array_keys($this->data));
            });
    }

    private function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label(__('admin.page_resource.actions.unpublish'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('admin.page_resource.modals.unpublish_heading'))
            ->modalDescription(__('admin.page_resource.modals.unpublish_description'))
            ->action(function (): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('publish', $page);

                /** @var User $user */
                $user = auth()->user();

                $result = $this->pageService->unpublish($page->id, $user->id);

                if ($result) {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.unpublished'))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('admin.page_resource.notifications.unpublish_failed'))
                        ->danger()
                        ->send();
                }

                $this->refreshFormData(array_keys($this->data));
            });
    }

    // ──────────────────────────────────────────────
    // Draft Save
    // ──────────────────────────────────────────────

    private function saveDraft(): void
    {
        try {
            $this->saveDraftFromFormData($this->form->getState(), 'draft');
        } catch (ConflictException $exception) {
            $this->notifyDraftConflict($exception);

            return;
        }

        Notification::make()
            ->title(__('admin.page_resource.notifications.draft_saved'))
            ->success()
            ->send();
    }

    private function saveDraftFromFormData(array $formData, string $status, ?string $publishAt = null): PageDraftDTO
    {
        /** @var Page $page */
        $page = $this->record;
        Gate::authorize('update', $page);

        /** @var User $user */
        $user = auth()->user();

        $draftPayload = new PageDraftDataDTO(
            metadata: new PageMetadataDTO(
                slug: $formData['slug'],
                template: $formData['template'],
                isHomepageShell: false,
                status: $status,
                parentPageId: $formData['parent_id'] ?? null,
                publishAt: $publishAt,
                isEnabled: $formData['is_enabled'] ?? true,
                showInBreadcrumbs: $formData['show_in_breadcrumbs'] ?? true,
                showInNav: $formData['show_in_nav'] ?? true,
                facultyScopeSlug: $formData['faculty_scope_slug'] ?? (is_string($page->faculty_scope_slug) ? $page->faculty_scope_slug : null),
            ),
            arabicTranslation: self::buildTranslationDTO($formData, 'ar'),
            englishTranslation: self::buildTranslationDTO($formData, 'en'),
            arabicSeo: self::buildSeoDTO($formData, 'ar'),
            englishSeo: self::buildSeoDTO($formData, 'en'),
        );

        $draft = $this->pageService->saveDraft($page->id, $draftPayload, $user->id, $this->draftVersion);
        $this->draftVersion = $draft->version;

        return $draft;
    }

    private function notifyDraftConflict(ConflictException $exception): void
    {
        $this->draftVersion = $exception->currentVersion;

        Notification::make()
            ->title(__('admin.page_resource.notifications.draft_changed'))
            ->body(__('admin.page_resource.notifications.draft_changed_description'))
            ->warning()
            ->persistent()
            ->send();
    }

    /** @return list<string> */
    private function publishValidationErrors(array $formData): array
    {
        $errors = [];

        if (($formData['slug'] ?? '') === '') {
            $errors[] = __('admin.page_resource.validation.slug_required');
        }

        if (($formData['template'] ?? '') === '') {
            $errors[] = __('admin.page_resource.validation.template_required');
        }

        if (! (bool) ($formData['is_enabled'] ?? true)) {
            $errors[] = __('admin.page_resource.validation.enabled_required');
        }

        if (($formData['ar_title'] ?? '') === '') {
            $errors[] = __('admin.page_resource.validation.arabic_title_required');
        }

        if (($formData['en_title'] ?? '') === '') {
            $errors[] = __('admin.page_resource.validation.english_title_required');
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function formatValidationErrors(array $errors): string
    {
        return __('admin.page_resource.validation.publish_fields_heading', [
            'errors' => implode("\n- ", $errors),
        ]);
    }

    // ──────────────────────────────────────────────
    // DTO Builders
    // ──────────────────────────────────────────────

    private static function buildTranslationDTO(array $data, string $locale): PageTranslationDTO
    {
        $prefix = $locale === 'ar' ? 'ar_' : 'en_';

        return new PageTranslationDTO(
            title: $data["{$prefix}title"] ?? '',
            headline: $data["{$prefix}headline"] ?? null,
            subheadline: $data["{$prefix}subheadline"] ?? null,
            heroPayload: filled($data["{$prefix}hero_content"] ?? null)
                ? ['content' => $data["{$prefix}hero_content"]]
                : null,
            body: $data["{$prefix}body"] ?? null,
            ctaPayload: (filled($data["{$prefix}cta_label"] ?? null) || filled($data["{$prefix}cta_url"] ?? null))
                ? ['label' => $data["{$prefix}cta_label"] ?? null, 'url' => $data["{$prefix}cta_url"] ?? null]
                : null,
            sidebarPayload: filled($data["{$prefix}sidebar_content"] ?? null)
                ? ['content' => $data["{$prefix}sidebar_content"]]
                : null,
            excerpt: $data["{$prefix}excerpt"] ?? null,
        );
    }

    private static function buildSeoDTO(array $data, string $locale): PageSeoInputDTO
    {
        $prefix = $locale === 'ar' ? 'ar_seo_' : 'en_seo_';

        return new PageSeoInputDTO(
            locale: $locale,
            title: $data["{$prefix}meta_title"] ?? $data[($locale === 'ar' ? 'ar_' : 'en_').'title'] ?? '',
            metaDescription: $data["{$prefix}meta_description"] ?? null,
            ogTitle: $data["{$prefix}og_title"] ?? null,
            ogDescription: $data["{$prefix}og_description"] ?? null,
            ogImage: $data["{$prefix}og_image"] ?? null,
            canonicalUrl: $data["{$prefix}canonical_url"] ?? null,
            robots: $data["{$prefix}robots"] ?? null,
        );
    }
}
