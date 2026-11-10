<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

/**
 * One line of a dashboard list: a page and its views, a search term and how
 * often it was searched, a draft and when it was edited.
 */
final readonly class DashboardRowDTO
{
    public function __construct(
        public string $label,
        public int $value = 0,
        public ?string $hint = null,
        public ?string $url = null,
    ) {}
}
