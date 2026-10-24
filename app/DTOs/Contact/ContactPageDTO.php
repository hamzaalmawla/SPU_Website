<?php

declare(strict_types=1);

namespace App\DTOs\Contact;

final readonly class ContactPageDTO
{
    /**
     * @param  array{title: string, bgImage: string}  $hero
     * @param  array<string, mixed>  $info
     * @param  array{title: string, fields: array<string, array{label: string}>, submit: string}  $form
     * @param  array<int, array{icon: string, url: string}>  $socials
     * @param  array{title: string, button: string, mapUrl: string, embedUrl: string}  $location
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $dateLabel,
        public array $hero,
        public array $info,
        public string $socialsTitle,
        public array $socials,
        public array $form,
        public array $location,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
