<?php

declare(strict_types=1);

namespace App\Contracts\News;

use App\DTOs\News\NewsRelatedLinkDTO;

/**
 * The optional button of an announcement: to an event article, a research
 * conference or an address. Kept apart from the article CMS services because
 * it reads research conferences, whose service depends on the CMS workflow.
 */
interface NewsRelatedLinkServiceInterface
{
    /** What an announcement's related link can point at. */
    public const RELATED_KINDS = ['article', 'conference', 'url'];

    /**
     * The button, or null when there is no link or its target is not public.
     * Without an editor label the text follows the target: "Register" while
     * registration is open, otherwise its details.
     */
    public function relatedLink(?string $kind, ?int $articleId, ?string $conferenceId, ?string $url, ?string $label, string $locale): ?NewsRelatedLinkDTO;

    /**
     * The button of an article draft payload, for the protected preview.
     *
     * @param  array<string, mixed>  $payload
     */
    public function forPayload(array $payload, string $locale): ?NewsRelatedLinkDTO;

    /**
     * Choices for the announcement editor: public Events and Society's
     * Events articles ("article"), or published research conferences
     * ("conference"). A current value that is no longer public stays listed.
     *
     * @return array<int|string, string>
     */
    public function relatedLinkOptions(string $kind, string $locale, int|string|null $current = null): array;
}
