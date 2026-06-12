<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ContactPageDTO;
use App\DTOs\ContactPageContentDTO;
use App\DTOs\ContactSubmissionDataDTO;

interface ContactPageServiceInterface
{
    public function getPage(string $locale): ContactPageDTO;

    public function getContent(string $locale): ContactPageContentDTO;

    public function updatePage(string $locale, ContactPageContentDTO $content, int $userId): bool;

    public function submit(ContactSubmissionDataDTO $submission): bool;
}
