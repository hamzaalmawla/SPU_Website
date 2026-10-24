<?php

declare(strict_types=1);

namespace App\DTOs\ErrorPage;

/**
 * Everything an error view needs, resolved without touching the database.
 *
 * Both languages are always carried so the dependency-free 500/503 views can
 * render Arabic and English side by side without consulting the translator or
 * the request locale.
 */
final readonly class ErrorPageContentDTO
{
    /**
     * @param  array<int, ErrorPageLinkDTO>  $links
     */
    public function __construct(
        public int $status,
        public string $locale,
        public string $direction,
        public string $title,
        public string $message,
        public string $arabicTitle,
        public string $arabicMessage,
        public string $englishTitle,
        public string $englishMessage,
        public string $homeUrl,
        public ?string $searchUrl,
        public string $logoUrl,
        public array $links = [],
    ) {}
}
