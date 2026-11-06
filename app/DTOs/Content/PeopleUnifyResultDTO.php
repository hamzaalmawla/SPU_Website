<?php

declare(strict_types=1);

namespace App\DTOs\Content;

final readonly class PeopleUnifyResultDTO
{
    /**
     * @param  list<string>  $lines  One block per leadership row: the profile it gets, what is filled and what differs
     * @param  list<string>  $skipped  Rows left for an editor, with the reason
     */
    public function __construct(
        public bool $written,
        public int $linkedExisting,
        public int $linkedByName,
        public int $profilesCreated,
        public int $alreadyUnified,
        public int $fieldsFilled,
        public array $lines,
        public array $skipped,
        public ?string $snapshotPath,
    ) {}
}
