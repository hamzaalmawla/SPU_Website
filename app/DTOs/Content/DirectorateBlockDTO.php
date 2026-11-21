<?php

declare(strict_types=1);

namespace App\DTOs\Content;

/**
 * One block of a directorate's page, in one language: a heading or text box
 * ($text), an image ($image, $alt, $caption), or a button ($label) that opens
 * a web address ($url) or one of the site's forms ($formKey).
 */
final readonly class DirectorateBlockDTO
{
    public function __construct(
        public string $type,
        public string $text = '',
        public ?string $image = null,
        public string $alt = '',
        public string $caption = '',
        public string $label = '',
        public ?string $url = null,
        public ?string $formKey = null,
        public bool $isExternal = false,
    ) {}
}
