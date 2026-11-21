<?php

declare(strict_types=1);

namespace App\Contracts\Page;

use App\DTOs\Admissions\AdmissionsTuitionFeeImportResultDTO;

interface AdmissionsTuitionFeeImportServiceInterface
{
    /**
     * Replaces the fee rows (and any table labels the source gives) of the Admissions tuition
     * page, starting from its published content, and validates the result with the CMS publish
     * readiness rules. When $publish is true and the result is valid, it is saved as a CMS draft
     * and published through the normal workflow (audit log and cache invalidation included).
     *
     * An unpublished draft that differs from the published page is an editor's pending work:
     * the import then leaves everything untouched and reports itself as skipped.
     *
     * @param  array<string, mixed>  $source  {"labels": {"ar": {...}, "en": {...}}, "rows": [{"faculty": {"ar": "", "en": ""}, "scientificAverage": 80, ...}]}
     */
    public function import(array $source, string $actor, bool $publish): AdmissionsTuitionFeeImportResultDTO;
}
