<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class LegacyCourseSpecificationImportResultDTO
{
    /**
     * @param  list<array{faculty: string, title: string, status: string, courses: list<string>, specification: bool}>  $rows  status: attached | unchanged | no_match | no_plan | failed
     * @param  list<string>  $published  Faculties whose plan was saved and published
     * @param  list<string>  $drafted  Faculties whose open editor draft received the changes (not published)
     */
    public function __construct(
        public array $rows,
        public array $published,
        public array $drafted,
        public bool $written,
    ) {}
}
