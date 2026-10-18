<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Contact\ContactPageContentDTO;
use App\DTOs\Contact\ContactPageDTO;
use App\DTOs\Contact\ContactSubmissionDataDTO;

interface ContactPageServiceInterface
{
    public function getPage(string $locale): ContactPageDTO;

    public function getContent(string $locale): ContactPageContentDTO;

    /**
     * The page content as the CMS edits it: the published payload, else the content settings.
     *
     * @return array{translations: array{ar: array<string, mixed>, en: array<string, mixed>}}
     */
    public function getEditablePayload(): array;

    /** @param array<string, mixed> $content */
    public function buildPreviewPage(string $locale, array $content): ContactPageDTO;

    public function updatePage(string $locale, ContactPageContentDTO $content, int $userId): bool;

    public function submit(ContactSubmissionDataDTO $submission): bool;
}
