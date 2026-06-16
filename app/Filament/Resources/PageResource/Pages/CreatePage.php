<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

use App\Contracts\Page\PageServiceInterface;
use App\DTOs\Page\PageMetadataDTO;
use App\DTOs\Seo\PageSeoInputDTO;
use App\DTOs\Page\PageShellDataDTO;
use App\DTOs\Page\PageTranslationDTO;
use App\Filament\Resources\PageResource;
use App\Models\Page\Page;
use App\Models\User\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    private PageServiceInterface $pageService;

    public function boot(PageServiceInterface $pageService): void
    {
        $this->pageService = $pageService;
    }

    protected function handleRecordCreation(array $data): Model
    {
        Gate::authorize('create', Page::class);

        /** @var User $user */
        $user = auth()->user();

        $shellData = new PageShellDataDTO(
            slug: $data['slug'],
            template: $data['template'],
            isHomepageShell: false,
            status: 'draft',
            parentPageId: $data['parent_id'] ?? null,
            facultyScopeSlug: $data['faculty_scope_slug'] ?? null,
        );

        $pageDTO = $this->pageService->createPageShell($shellData, $user->id);

        $this->pageService->updateBaseMetadata(
            $pageDTO->id,
            new PageMetadataDTO(
                slug: $data['slug'],
                template: $data['template'],
                isHomepageShell: false,
                status: 'draft',
                parentPageId: $data['parent_id'] ?? null,
                isEnabled: $data['is_enabled'] ?? true,
                showInBreadcrumbs: $data['show_in_breadcrumbs'] ?? true,
                showInNav: $data['show_in_nav'] ?? true,
                facultyScopeSlug: $data['faculty_scope_slug'] ?? null,
            ),
            $user->id,
        );

        // Save Arabic translation
        $this->pageService->updateArabicTranslation(
            $pageDTO->id,
            self::buildTranslationDTO($data, 'ar'),
            $user->id,
        );

        // Save English translation
        $this->pageService->updateEnglishTranslation(
            $pageDTO->id,
            self::buildTranslationDTO($data, 'en'),
            $user->id,
        );

        // Save Arabic SEO
        $this->pageService->updateArabicSeo(
            $pageDTO->id,
            self::buildSeoDTO($data, 'ar'),
            $user->id,
        );

        // Save English SEO
        $this->pageService->updateEnglishSeo(
            $pageDTO->id,
            self::buildSeoDTO($data, 'en'),
            $user->id,
        );

        Notification::make()
            ->title('Page created successfully')
            ->success()
            ->send();

        return Page::findOrFail($pageDTO->id)->refresh();
    }

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
