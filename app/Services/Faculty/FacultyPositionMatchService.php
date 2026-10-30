<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyPositionMatchServiceInterface;
use App\Contracts\Faculty\FacultyPositionServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Faculty\FacultyPositionDTO;
use App\DTOs\Faculty\FacultyPositionMatchResultDTO;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\User\User;
use App\Support\SearchTextNormalizer;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class FacultyPositionMatchService implements FacultyPositionMatchServiceInterface
{
    /**
     * How profiles spell the built-in positions, checked in this order: a typed position
     * that is or starts with one of these words is that position.
     *
     * @var array<string, list<string>>
     */
    private const SPELLINGS = [
        FacultyPositionServiceInterface::HEAD_OF_DEPARTMENT => ['رئيس قسم', 'رئيس القسم', 'رئيسة قسم', 'رئيسة القسم', 'head of department', 'head of the department', 'department head', 'chair of the department', 'head,'],
        FacultyPositionServiceInterface::VICE_DEAN => ['نائب العميد', 'نائب عميد', 'نائبة العميد', 'نائبة عميد', 'vice dean', 'vice-dean', 'deputy dean', 'associate dean'],
        FacultyPositionServiceInterface::DEAN => ['عميد', 'عميدة', 'dean'],
    ];

    public function __construct(
        private readonly FacultyPositionServiceInterface $positionService,
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly CmsWorkflowServiceInterface $workflow,
    ) {}

    public function run(?string $facultySlug, bool $write, ?string $user, bool $publish = false): FacultyPositionMatchResultDTO
    {
        $userId = null;

        if ($user !== null && $user !== '') {
            $id = User::query()->where(is_numeric($user) ? 'id' : 'email', $user)->value('id');

            if (! is_numeric($id)) {
                throw new InvalidArgumentException('No CMS user '.$user.'.');
            }

            $userId = (int) $id;
        }

        if ($write && $userId === null) {
            throw new InvalidArgumentException('A write needs the CMS user the drafts are saved as.');
        }

        $faculties = Faculty::query()
            ->with('translations')
            ->when($facultySlug !== null, fn ($query) => $query->where('public_slug', $facultySlug))
            ->whereNotNull('public_slug')
            ->orderBy('id')
            ->get();

        if ($facultySlug !== null && $faculties->isEmpty()) {
            throw new InvalidArgumentException('Unknown faculty: '.$facultySlug);
        }

        $matched = [];
        $present = [];
        $heads = [];
        $skipped = [];
        $unmatched = [];
        $saved = [];
        $published = [];
        $notPublished = [];

        foreach ($faculties as $faculty) {
            $slug = (string) $faculty->public_slug;
            $targetKey = 'facilities.'.$slug.'.council';
            $payload = $this->basePayload($targetKey, $userId);
            $rows = $this->rows($payload);
            $hasDean = collect($rows)->contains(fn (array $row): bool => $row['positionSlug'] === FacultyPositionServiceInterface::DEAN);
            $added = [];

            $members = FacultyMember::query()
                ->public()
                ->where('faculty_id', $faculty->getKey())
                ->with('translations')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($members as $member) {
                $arabic = trim((string) ($member->translations->firstWhere('locale', 'ar')?->position ?? ''));
                $english = trim((string) ($member->translations->firstWhere('locale', 'en')?->position ?? ''));
                $typed = $arabic !== '' ? $arabic : $english;

                if ($typed === '') {
                    continue;
                }

                $name = (string) ($member->translations->firstWhere('locale', 'ar')?->full_name ?? $member->slug);
                $row = ['faculty' => $slug, 'member' => $name, 'typed' => $typed];
                $position = $this->match($arabic) ?? $this->match($english);

                if (! $position instanceof FacultyPositionDTO) {
                    $unmatched[] = $row;

                    continue;
                }

                if ($position->slug === FacultyPositionServiceInterface::HEAD_OF_DEPARTMENT) {
                    $heads[] = $row;

                    continue;
                }

                $row['position'] = $position->nameAr;
                $memberId = (int) $member->getKey();

                if (collect([...$rows, ...$added])->contains(fn (array $existing): bool => $existing['positionSlug'] === $position->slug && $existing['memberId'] === $memberId)) {
                    $present[] = $row;

                    continue;
                }

                if ($position->slug === FacultyPositionServiceInterface::DEAN && $hasDean) {
                    $skipped[] = [...$row, 'reason' => 'the council page already names a dean'];

                    continue;
                }

                $hasDean = $hasDean || $position->slug === FacultyPositionServiceInterface::DEAN;
                $added[] = ['id' => 'position-'.Str::lower(Str::random(8)), 'positionSlug' => $position->slug, 'memberId' => $memberId];
                $matched[] = $row;
            }

            if ($write && $added !== [] && $userId !== null) {
                foreach (['ar', 'en'] as $locale) {
                    $payload['translations'][$locale]['positions'] = [...$this->listOfArrays($payload['translations'][$locale]['positions'] ?? []), ...$added];
                    $payload['translations'][$locale]['includeDepartmentHeads'] ??= true;
                }

                // Checked before saving: an editor's unfinished work is never published from here.
                $hadOpenDraft = $this->workflow->latestEditableDraftPayload($targetKey, $userId) !== null;

                $this->workflow->saveDraft($targetKey, $payload, $userId);
                $saved[] = $slug;

                if ($publish) {
                    try {
                        if ($hadOpenDraft) {
                            $notPublished[$slug] = 'an editor already had a draft open on this page';
                        } elseif ($this->workflow->publish($targetKey, $userId)) {
                            $published[] = $slug;
                        } else {
                            $notPublished[$slug] = 'the publish changed nothing';
                        }
                    } catch (ValidationException $exception) {
                        $notPublished[$slug] = implode(' ', array_map('strval', collect($exception->errors())->flatten()->unique()->all()));
                    }
                }
            }
        }

        return new FacultyPositionMatchResultDTO($matched, $present, $heads, $skipped, $unmatched, $saved, $write, $published, $notPublished);
    }

    /**
     * The council page an editor would continue from: their latest draft, else the
     * published page, else the default.
     *
     * @return array<string, mixed>
     */
    private function basePayload(string $targetKey, ?int $userId): array
    {
        $draft = $userId !== null ? $this->workflow->latestEditableDraftPayload($targetKey, $userId) : null;
        $payload = is_array($draft) && is_array($draft['translations']['ar'] ?? null) ? $draft : $this->facultyPageService->getEditablePayload($targetKey);

        foreach (['ar', 'en'] as $locale) {
            $payload['translations'][$locale] = is_array($payload['translations'][$locale] ?? null) ? $payload['translations'][$locale] : [];
        }

        return $payload;
    }

    /**
     * The page's positions, read the way the council page reads them.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{positionSlug: string, memberId: int}>
     */
    private function rows(array $payload): array
    {
        $rows = [];

        foreach ($this->listOfArrays($payload['translations']['ar']['positions'] ?? []) as $position) {
            $slug = trim((string) ($position['positionSlug'] ?? ''));
            $slug = $slug !== '' ? $slug : (string) ($position['type'] ?? '');

            if (is_numeric($position['memberId'] ?? null)) {
                $rows[] = ['positionSlug' => $slug, 'memberId' => (int) $position['memberId']];
            }
        }

        return $rows;
    }

    private function match(string $typed): ?FacultyPositionDTO
    {
        $typed = $this->fold($typed);

        if ($typed === '') {
            return null;
        }

        // An added position matches by its exact name, before any built-in spelling.
        foreach ($this->positionService->all() as $position) {
            if (! $position->isBuiltIn && in_array($typed, [$this->fold($position->nameAr), $this->fold($position->nameEn)], true)) {
                return $position;
            }
        }

        foreach (self::SPELLINGS as $slug => $spellings) {
            foreach ($spellings as $spelling) {
                $spelling = $this->fold($spelling);

                if ($typed === $spelling || str_starts_with($typed, rtrim($spelling, ',').' ') || str_starts_with($typed, $spelling)) {
                    return $this->positionService->find($slug);
                }
            }
        }

        return null;
    }

    private function fold(string $value): string
    {
        return trim(Str::lower(SearchTextNormalizer::normalize($value)));
    }

    /** @return list<array<string, mixed>> */
    private function listOfArrays(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
