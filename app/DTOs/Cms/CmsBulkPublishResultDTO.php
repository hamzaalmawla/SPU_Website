<?php

declare(strict_types=1);

namespace App\DTOs\Cms;

final readonly class CmsBulkPublishResultDTO
{
    /**
     * @param  list<string>  $published  Targets whose pending draft went live
     * @param  list<string>  $unchanged  Targets with no pending draft to publish
     * @param  array<string, list<string>>  $failed  Targets whose draft is not ready, with the reasons
     */
    public function __construct(
        public array $published = [],
        public array $unchanged = [],
        public array $failed = [],
    ) {}
}
