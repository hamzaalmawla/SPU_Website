<?php

declare(strict_types=1);

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page\Page;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPage extends ViewRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Page $page */
        $page = $this->record;
        $page->load(['translations', 'seoMeta']);

        $arTranslation = $page->translations->firstWhere('locale', 'ar');
        $enTranslation = $page->translations->firstWhere('locale', 'en');
        $arSeo = $page->seoMeta->firstWhere('locale', 'ar');
        $enSeo = $page->seoMeta->firstWhere('locale', 'en');

        // Arabic translation fields
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
