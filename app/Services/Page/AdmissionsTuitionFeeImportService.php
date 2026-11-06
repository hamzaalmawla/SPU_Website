<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\AdmissionsTuitionFeeImportServiceInterface;
use App\DTOs\Admissions\AdmissionsTuitionFeeImportResultDTO;
use App\Models\User\User;
use InvalidArgumentException;

final class AdmissionsTuitionFeeImportService implements AdmissionsTuitionFeeImportServiceInterface
{
    private const TARGET_KEY = 'admissions.tuition';

    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    /** Number fields a fee row carries, identical in both locales. */
    private const NUMBER_FIELDS = ['scientificAverage', 'vocationalAverage', 'literaryAverage', 'creditHours', 'syrianFee', 'foreignFee'];

    /** Text labels the source may set per locale; anything else in it is ignored. */
    private const TEXT_LABELS = ['overviewTitle', 'feeTableIntro', 'feeUnit'];

    private const LABEL_GROUPS = ['columns', 'trackLabels', 'currencies'];

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly AdmissionsPageServiceInterface $admissionsPageService,
    ) {}

    public function import(array $source, string $actor, bool $publish): AdmissionsTuitionFeeImportResultDTO
    {
        $userId = $this->actorId($actor);
        $sourceRows = array_values(array_filter(
            is_array($source['rows'] ?? null) ? $source['rows'] : [],
            static fn (mixed $row): bool => is_array($row),
        ));

        if ($sourceRows === []) {
            throw new InvalidArgumentException('The source file contains no fee rows.');
        }

        $payload = $this->admissionsPageService->getEditablePayload(self::TARGET_KEY);
        $labels = is_array($source['labels'] ?? null) ? $source['labels'] : [];

        foreach (self::LOCALES as $locale) {
            $translation = $payload['translations'][$locale];
            $localeLabels = is_array($labels[$locale] ?? null) ? $labels[$locale] : [];

            foreach (self::TEXT_LABELS as $key) {
                if (is_string($localeLabels[$key] ?? null)) {
                    $translation[$key] = trim($localeLabels[$key]);
                }
            }

            foreach (self::LABEL_GROUPS as $group) {
                if (is_array($localeLabels[$group] ?? null)) {
                    $translation[$group] = array_merge(
                        is_array($translation[$group] ?? null) ? $translation[$group] : [],
                        array_filter($localeLabels[$group], static fn (mixed $label): bool => is_string($label) && trim($label) !== ''),
                    );
                }
            }

            $translation['feeRows'] = array_map(fn (array $row): array => $this->localizedRow($row, $locale), $sourceRows);
            $payload['translations'][$locale] = $translation;
        }

        $readiness = $this->cmsWorkflowService->readiness(self::TARGET_KEY, $payload);
        $summary = array_map(fn (array $row): array => $this->summaryRow($row), $payload['translations']['en']['feeRows']);

        if (! $readiness->isReady) {
            return new AdmissionsTuitionFeeImportResultDTO(rows: $summary, valid: false, errors: $readiness->errors, published: false);
        }

        $published = $this->cmsWorkflowService->getPublishedPayload(self::TARGET_KEY);

        if (is_array($published['translations'] ?? null) && $this->sameTranslations($published['translations'], $payload['translations'])) {
            return new AdmissionsTuitionFeeImportResultDTO(rows: $summary, valid: true, errors: [], published: false, unchanged: true);
        }

        $pendingDraft = $this->cmsWorkflowService->latestEditableDraftForReport(self::TARGET_KEY);

        if ($pendingDraft !== null && ! $this->sameTranslations(
            is_array($pendingDraft['payload']['translations'] ?? null) ? $pendingDraft['payload']['translations'] : [],
            is_array($published['translations'] ?? null) ? $published['translations'] : [],
        )) {
            return new AdmissionsTuitionFeeImportResultDTO(
                rows: $summary,
                valid: true,
                errors: [],
                published: false,
                skipReason: 'An editor has an unpublished draft of the tuition page (version '.$pendingDraft['version'].'). Publish or discard it first; nothing was changed.',
            );
        }

        if (! $publish) {
            return new AdmissionsTuitionFeeImportResultDTO(rows: $summary, valid: true, errors: [], published: false);
        }

        $this->cmsWorkflowService->saveDraft(self::TARGET_KEY, $payload, $userId, $this->cmsWorkflowService->latestEditableDraftVersion(self::TARGET_KEY, $userId));

        return new AdmissionsTuitionFeeImportResultDTO(
            rows: $summary,
            valid: true,
            errors: [],
            published: $this->cmsWorkflowService->publish(self::TARGET_KEY, $userId),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|int|float>
     */
    private function localizedRow(array $row, string $locale): array
    {
        $faculty = is_array($row['faculty'] ?? null) ? ($row['faculty'][$locale] ?? '') : '';
        $localized = ['faculty' => is_string($faculty) ? trim($faculty) : ''];

        foreach (self::NUMBER_FIELDS as $field) {
            $value = $row[$field] ?? null;

            if (is_int($value) || is_float($value)) {
                $localized[$field] = $value;
            } elseif ($value !== null) {
                throw new InvalidArgumentException('Row "'.$localized['faculty'].'": '.$field.' must be a JSON number.');
            }
        }

        return $localized;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{faculty: string, tracks: string, creditHours: string, syrianFee: string, foreignFee: string}
     */
    private function summaryRow(array $row): array
    {
        $tracks = [];

        foreach (['scientific', 'vocational', 'literary'] as $track) {
            if (isset($row[$track.'Average'])) {
                $tracks[] = $track.' '.$row[$track.'Average'].'%';
            }
        }

        return [
            'faculty' => (string) $row['faculty'],
            'tracks' => implode(', ', $tracks),
            'creditHours' => (string) ($row['creditHours'] ?? ''),
            'syrianFee' => (string) ($row['syrianFee'] ?? ''),
            'foreignFee' => (string) ($row['foreignFee'] ?? ''),
        ];
    }

    /**
     * @param  array<mixed>  $left
     * @param  array<mixed>  $right
     */
    private function sameTranslations(array $left, array $right): bool
    {
        return json_encode($left) === json_encode($right);
    }

    private function actorId(string $actor): int
    {
        $query = User::query();
        $user = ctype_digit($actor) ? $query->find((int) $actor) : $query->where('email', $actor)->first();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('No user matches "'.$actor.'". Pass a user id or email with --user.');
        }

        return (int) $user->getKey();
    }
}
