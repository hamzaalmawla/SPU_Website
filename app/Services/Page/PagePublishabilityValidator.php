<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\DTOs\Page\PageDTO;
use App\Models\Page\Page;

/**
 * Validates the minimum content required before a page can become public.
 */
final class PagePublishabilityValidator
{
    public function isPublishablePage(Page $page): bool
    {
        if (empty($page->slug) || empty($page->template) || ! (bool) $page->is_enabled) {
            return false;
        }

        $page->loadMissing('translations');

        $localesWithTitle = $page->translations
            ->filter(fn ($translation): bool => in_array((string) $translation->locale, ['ar', 'en'], true) && ! empty($translation->title))
            ->pluck('locale')
            ->all();

        return in_array('ar', $localesWithTitle, true) && in_array('en', $localesWithTitle, true);
    }

    public function isPublishableDraft(PageDTO $page): bool
    {
        return $page->metadata->slug !== ''
            && $page->metadata->template !== ''
            && $page->metadata->isEnabled
            && $page->arabicTranslation->title !== ''
            && $page->englishTranslation->title !== '';
    }
}
