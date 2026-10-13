<?php

declare(strict_types=1);

namespace App\Contracts\Faculty;

use App\DTOs\Faculty\ProjectFieldBlockDTO;

interface ProjectFieldBlockParserInterface
{
    /**
     * Separate a student project body into its description and the labelled
     * fields the legacy site wrote inline (team, supervisor, academic year).
     *
     * @param  array<int, mixed>  $paragraphs
     */
    public function parse(array $paragraphs): ProjectFieldBlockDTO;

    /**
     * Split a stored team string into individual names.
     *
     * @return list<string>
     */
    public function splitNames(?string $value): array;
}
