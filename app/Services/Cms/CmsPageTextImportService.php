<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\Contracts\Research\ResearchPageServiceInterface;
use App\Models\User\User;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class CmsPageTextImportService implements CmsPageTextImportServiceInterface
{
    private const LOCALES = ['ar', 'en'];

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly AboutPageServiceInterface $aboutPageService,
        private readonly AdmissionsPageServiceInterface $admissionsPageService,
        private readonly CampusLifePageServiceInterface $campusLifePageService,
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly ResearchPageServiceInterface $researchPageService,
    ) {}

    public function import(string $path, string $actor, bool $write, bool $overDrafts = false, ?string $only = null): array
    {
        $targets = $this->targets($path);
        $userId = $this->actorId($actor);
        $results = [];

        foreach ($targets as $targetKey => $changes) {
            if ($only !== null && $only !== $targetKey) {
                continue;
            }

            try {
                $results[] = ['target' => $targetKey, 'result' => $this->importTarget($targetKey, $changes, $userId, $write, $overDrafts)];
            } catch (Throwable $exception) {
                $results[] = ['target' => $targetKey, 'result' => 'FAILED: '.$exception->getMessage()];
            }
        }

        return $results;
    }

    /** @param array<string, mixed> $changes */
    private function importTarget(string $targetKey, array $changes, int $userId, bool $write, bool $overDrafts): string
    {
        $openDraft = $this->cmsWorkflowService->latestEditableDraftForReport($targetKey);
        if ($openDraft !== null && ! $overDrafts) {
            return 'SKIPPED: an editor has an unpublished draft of this page; publish or discard it first';
        }

        $payload = $this->editablePayload($targetKey);
        $changed = 0;
        foreach (self::LOCALES as $locale) {
            foreach (is_array($changes[$locale] ?? null) ? $changes[$locale] : [] as $field => $value) {
                Arr::set($payload, 'translations.'.$locale.'.'.$field, $value);
                $changed++;
            }
        }
        foreach (is_array($changes['set'] ?? null) ? $changes['set'] : [] as $dottedPath => $value) {
            Arr::set($payload, (string) $dottedPath, $value);
            $changed++;
        }

        if (! $write) {
            $readiness = $this->cmsWorkflowService->readiness($targetKey, $payload);

            return 'dry run: '.$changed.' field(s)'.($readiness->isReady ? ', ready to publish' : ', NOT ready: '.json_encode($readiness->errors, JSON_UNESCAPED_UNICODE));
        }

        $this->cmsWorkflowService->saveDraft($targetKey, $payload, $userId, $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId));
        $this->cmsWorkflowService->publish($targetKey, $userId);

        return 'published ('.$changed.' field(s))';
    }

    /** @return array<string, mixed> */
    private function editablePayload(string $targetKey): array
    {
        $area = strstr($targetKey, '.', true) ?: $targetKey;

        return match ($area) {
            'about' => $this->aboutPageService->getEditablePayload($targetKey),
            'admissions' => $this->admissionsPageService->getEditablePayload($targetKey),
            'campus_life' => $this->campusLifePageService->getEditablePayload($targetKey),
            'facilities' => $this->facultyPageService->getEditablePayload($targetKey),
            'research' => $this->researchPageService->getEditablePayload($targetKey),
            default => throw new InvalidArgumentException('No page text import for '.$targetKey),
        };
    }

    /** @return array<string, array<string, mixed>> */
    private function targets(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('The page text file could not be read: '.$path);
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The page text file is not valid JSON: '.$exception->getMessage());
        }

        $targets = is_array($data['targets'] ?? null) ? $data['targets'] : [];

        return array_filter($targets, 'is_array');
    }

    private function actorId(string $actor): int
    {
        $query = User::query();
        $user = ctype_digit($actor) ? $query->find((int) $actor) : $query->where('email', $actor)->first();

        if (! $user instanceof User) {
            throw new InvalidArgumentException('No user matches "'.$actor.'".');
        }

        return (int) $user->getKey();
    }
}
