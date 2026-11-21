<?php

declare(strict_types=1);

namespace App\DTOs\Center;

/**
 * A center's page (`subpageSlug` empty) or one of its subpages: staff,
 * required-courses or elective-courses.
 */
final readonly class CenterPageDTO
{
    /**
     * @param  array{slug: string, name: string, url: string, heroImage: string, gallery: array<int, string>}  $center
     * @param  array<string, mixed>  $content  the page's CMS content in this language
     * @param  array{name: string, image: string|null, url: string, title: string, message: string}|null  $manager
     * @param  array<int, array{slug: string, name: string, position: string, image: string|null, profileUrl: string, isManager: bool}>  $staff
     * @param  array<int, array<string, mixed>>  $courses
     * @param  array<int, array{slug: string, label: string, summary: string, url: string, active: bool}>  $navigation
     * @param  array<string, string>  $filters
     * @param  array<string, string>  $filterOptions  faculty slug => name
     */
    public function __construct(
        public string $locale,
        public string $direction,
        public string $subpageSlug,
        public array $center,
        public array $content,
        public ?array $manager,
        public array $staff,
        public array $courses,
        public array $navigation,
        public array $filters,
        public array $filterOptions,
        public string $title,
        public string $path,
        public string $seoTitle,
        public string $seoDescription,
        public string $seoImage,
    ) {}
}
