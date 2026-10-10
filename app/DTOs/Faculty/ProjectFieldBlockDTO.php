<?php

declare(strict_types=1);

namespace App\DTOs\Faculty;

final readonly class ProjectFieldBlockDTO
{
    /**
     * @param  list<string>  $description  paragraphs that describe the project
     * @param  list<string>  $team  every name listed under the team label
     * @param  list<string>  $fieldLines  the label and value lines removed from the body
     */
    public function __construct(
        public array $description,
        public array $team,
        public ?string $supervisor,
        public ?string $year,
        public array $fieldLines,
    ) {}

    public function isFieldLine(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }

        foreach ($this->fieldLines as $fieldLine) {
            if ($fieldLine === $line || str_starts_with($fieldLine, $line)) {
                return true;
            }
        }

        return false;
    }
}
