<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * @param  array<int, string>  $services
 * @param  array<int, array{title: string, url: string}>  $links
 */
final readonly class DirectorateDTO
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $summary,
        public string $description,
        public array $services,
        public array $links,
        public ?string $icon,
        public ?string $email,
        public ?string $location,
    ) {}
}
