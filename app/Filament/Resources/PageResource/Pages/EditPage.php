<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

use App\Contracts\PageServiceInterface;
use App\Contracts\PreviewServiceInterface;
use App\DTOs\PageDraftDataDTO;
use App\DTOs\PageMetadataDTO;
use App\DTOs\PageSeoInputDTO;
use App\DTOs\PageTranslationDTO;
use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Models\User;
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

    public function boot(PageServiceInterface $pageService, PreviewServiceInterface $previewService): void
    {
        $this->pageService = $pageService;
        $this->previewService = $previewService;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->record;
        Gate::authorize('view', $page);

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

        $this->pageService->updateBaseMetadata(
            $record->id,
            new PageMetadataDTO(
                slug: $data['slug'],
                template: $data['template'],
                isHomepageShell: false,
                status: $data['status'],
                parentPageId: $data['parent_id'] ?? null,
                isEnabled: $data['is_enabled'] ?? true,
                showInBreadcrumbs: $data['show_in_breadcrumbs'] ?? true,
                showInNav: $data['show_in_nav'] ?? true,
                facultyScopeSlug: $data['faculty_scope_slug'] ?? (is_string($record->faculty_scope_slug) ? $record->faculty_scope_slug : null),
            ),
            (int) auth()->id(),
        );

        $this->pageService->updateArabicTranslation(
            $record->id,
            self::buildTranslationDTO($data, 'ar'),
            (int) auth()->id(),
        );

        $this->pageService->updateEnglishTranslation(
            $record->id,
            self::buildTranslationDTO($data, 'en'),
            (int) auth()->id(),
        );

        $this->pageService->updateArabicSeo(
            $record->id,
            self::buildSeoDTO($data, 'ar'),
            (int) auth()->id(),
        );

        $this->pageService->updateEnglishSeo(
            $record->id,
            self::buildSeoDTO($data, 'en'),
            (int) auth()->id(),
        );

        return $record->refresh();
    }

    // ──────────────────────────────────────────────
    // Header Actions
    // ──────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->saveDraftAction(),
            $this->previewAction(),
            $this->publishAction(),
            $this->scheduleAction(),
            $this->unpublishAction(),
        ];
    }

    private function saveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label('Save Draft')
            ->icon('heroicon-o-document')
            ->color('gray')
            ->action(function (): void {
                $this->saveDraft();
            });
    }

    private function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->action(function (): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('preview', $page);

                /** @var User $user */
                $user = auth()->user();

                $preview = $this->previewService->createToken(
                    targetType: 'page',
                    targetId: $page->id,
                    locale: 'ar',
                    userId: $user->id,
                );

                $this->redirect($preview->previewUrl);
            });
    }

    private function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Publish Page')
            ->modalDescription('This will make the page live immediately.')
            ->action(function (): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('publish', $page);

                /** @var User $user */
                $user = auth()->user();

                $result = $this->pageService->publish($page->id, $user->id);

                if ($result) {
                    Notification::make()
                        ->title('Page published successfully')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Publish failed')
                        ->body('Please ensure all required content is filled in.')
                        ->danger()
                        ->send();
                }

                $this->refreshFormData(array_keys($this->data));
            });
    }

    private function scheduleAction(): Action
    {
        return Action::make('schedule')
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
            ->action(function (array $data): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('publish', $page);

                /** @var User $user */
                $user = auth()->user();

                $result = $this->pageService->schedulePublish(
                    $page->id,
                    new \DateTimeImmutable($data['publish_at']),
                    $user->id,
                );

                if ($result) {
                    Notification::make()
                        ->title('Page scheduled for publication')
                        ->body("Scheduled for: {$data['publish_at']}")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Schedule failed')
                        ->danger()
                        ->send();
                }

                $this->refreshFormData(array_keys($this->data));
            });
    }

    private function unpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Unpublish')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Unpublish Page')
            ->modalDescription('This will remove the page from public view.')
            ->action(function (): void {
                /** @var Page $page */
                $page = $this->record;
                Gate::authorize('publish', $page);

                /** @var User $user */
                $user = auth()->user();

                $result = $this->pageService->unpublish($page->id, $user->id);

                if ($result) {
                    Notification::make()
                        ->title('Page unpublished')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Unpublish failed')
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
        $formData = $this->form->getState();

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
                status: $formData['status'] ?? 'draft',
                parentPageId: $formData['parent_id'] ?? null,
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

        $this->pageService->saveDraft($page->id, $draftPayload, $user->id);

        Notification::make()
            ->title('Draft saved successfully')
            ->success()
            ->send();
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
