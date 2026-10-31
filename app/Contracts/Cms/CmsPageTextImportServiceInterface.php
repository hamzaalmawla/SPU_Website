<?php

declare(strict_types=1);

namespace App\Contracts\Cms;

/**
 * Publishes reviewed page text (the old site's content, laid into the new page structures) through
 * the normal CMS workflow: each target starts from its current editable payload, gets the given
 * fields replaced, and is saved as a draft and published (readiness, audit log, cache).
 */
interface CmsPageTextImportServiceInterface
{
    /**
     * Data file: {"targets": {"<target key>": {"ar": {field: value}, "en": {...}, "set": {"dotted.path": value}, "remove": ["path.list[key=value]"]}}}.
     * Locale blocks replace fields at translations.<locale>.<field>; "set" replaces any path, and a
     * "list[key=value]" segment it cannot find is appended; "remove" drops such list items.
     * A target with an editor's unpublished draft is skipped unless $overDrafts.
     *
     * @return list<array{target: string, result: string}>
     */
    public function import(string $path, string $actor, bool $write, bool $overDrafts = false, ?string $only = null): array;
}
