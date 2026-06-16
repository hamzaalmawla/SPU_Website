<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Contact\ContactPageDTO;
use App\DTOs\Contact\ContactPageContentDTO;
use App\DTOs\Contact\ContactSubmissionDataDTO;

interface ContactPageServiceInterface
{
    public function getPage(string $locale): ContactPageDTO;

    public function getContent(string $locale): ContactPageContentDTO;

    public function updatePage(string $locale, ContactPageContentDTO $content, int $userId): bool;

    public function submit(ContactSubmissionDataDTO $submission): bool;
}
