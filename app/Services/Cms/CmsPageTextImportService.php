<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Contracts\Cms\CmsPageTextImportServiceInterface;
use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\AboutPageServiceInterface;
use App\Contracts\Page\AdmissionsPageServiceInterface;
use App\Contracts\Page\CampusLifePageServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
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
        private readonly ContactPageServiceInterface $contactPageService,
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
            Arr::set($payload, $this->resolvePath($payload, (string) $dottedPath, true), $value);
            $changed++;
        }
        foreach (is_array($changes['remove'] ?? null) ? $changes['remove'] : [] as $dottedPath) {
            $changed += $this->removeListItem($payload, (string) $dottedPath) ? 1 : 0;
        }

        if (! $write) {
            $readiness = $this->cmsWorkflowService->readiness($targetKey, $payload);

            return 'dry run: '.$changed.' field(s)'.($readiness->isReady ? ', ready to publish' : ', NOT ready: '.json_encode($readiness->errors, JSON_UNESCAPED_UNICODE));
        }

        $this->cmsWorkflowService->saveDraft($targetKey, $payload, $userId, $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId));
        $this->cmsWorkflowService->publish($targetKey, $userId);

        return 'published ('.$changed.' field(s))';
    }

    /**
     * Drops the list item a path ending in "list[key=value]" names, keeping the list a list.
     * A missing item is not an error: the page may never have had it.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function removeListItem(array &$payload, string $path): bool
    {
        if (preg_match('/^(.*?)([^.]+)\[(\w+)=([^\]]+)\]$/', $path, $match) !== 1) {
            throw new InvalidArgumentException('A remove path must end in list[key=value]: '.$path);
        }

        $listPath = $this->resolvePath($payload, rtrim($match[1], '.').($match[1] === '' ? '' : '.').$match[2]);
        $list = Arr::get($payload, $listPath);
        if (! is_array($list)) {
            return false;
        }

        $kept = array_values(array_filter($list, static fn (mixed $item): bool => ! is_array($item) || (string) ($item[$match[3]] ?? '') !== $match[4]));
        if (count($kept) === count($list)) {
            return false;
        }

        Arr::set($payload, $listPath, $kept);

        return true;
    }

    /**
     * "translations.ar.tabs[id=granted].items" addresses a list item by its id rather than its position,
     * so the data does not depend on the order the page happens to have. With $create a missing item
     * is appended to its list (carrying just that key), so a "set" can add a tab the page lacks.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function resolvePath(array &$payload, string $path, bool $create = false): string
    {
        $resolved = [];
        $node = $payload;
        foreach (explode('.', $path) as $segment) {
            if (preg_match('/^(.+)\[(\w+)=([^\]]+)\]$/', $segment, $match) === 1) {
                $list = is_array($node[$match[1]] ?? null) ? $node[$match[1]] : [];
                $index = collect($list)->search(static fn (mixed $item): bool => is_array($item) && (string) ($item[$match[2]] ?? '') === $match[3]);
                if ($index === false && $create) {
                    $list = array_values($list);
                    $list[] = [$match[2] => $match[3]];
                    $index = count($list) - 1;
                    Arr::set($payload, implode('.', [...$resolved, $match[1]]), $list);
                }
                if ($index === false) {
                    throw new InvalidArgumentException('No '.$match[1].' item with '.$match[2].'='.$match[3].' in '.$path);
                }
                $resolved[] = $match[1];
                $resolved[] = (string) $index;
                $node = $list[$index];

                continue;
            }
            $resolved[] = $segment;
            $node = is_array($node) && is_array($node[$segment] ?? null) ? $node[$segment] : [];
        }

        return implode('.', $resolved);
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
            'contact' => $this->contactPageService->getEditablePayload(),
            // E-services detail pages render only their published content.
            'e_services' => $this->cmsWorkflowService->getPublishedPayload($targetKey) ?? ['translations' => ['ar' => [], 'en' => []]],
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
