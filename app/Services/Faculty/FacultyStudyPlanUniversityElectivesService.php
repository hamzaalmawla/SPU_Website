<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanBoardServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanEditorServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanUniversityElectivesServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Faculty\UniversityElectiveBoxesResultDTO;
use App\Models\Faculty\Faculty;
use App\Models\User\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class FacultyStudyPlanUniversityElectivesService implements FacultyStudyPlanUniversityElectivesServiceInterface
{
    private const LOCALES = ['ar', 'en'];

    private const BOX_TITLE_AR = 'مقرر جامعة اختياري';

    private const BOX_TITLE_EN = 'University elective course';

    private const BOX_CREDITS = 2;

    public function __construct(
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly FacultyStudyPlanEditorServiceInterface $editorService,
        private readonly FacultyStudyPlanBoardServiceInterface $boardService,
        private readonly CmsWorkflowServiceInterface $workflow,
    ) {}

    public function replaceElectives(array $payload): array
    {
        // Both languages hold the same programs and courses: decide on one and apply to both.
        $reference = isset($payload['translations']['en']['payload']['plan']['departments']) ? 'en' : 'ar';
        $departments = $this->records($payload['translations'][$reference]['payload']['plan']['departments'] ?? []);
        $plans = [];
        $programs = [];
        $removedIds = [];

        foreach ($departments as $department) {
            $plan = $this->programPlan($department);
            $departmentId = (string) ($department['id'] ?? '');
            $plans[$departmentId] = $plan;
            $removedIds = [...$removedIds, ...$plan['removed']];

            if ($plan['removed'] !== [] || $plan['added'] !== []) {
                $programs[] = [
                    'program' => $departmentId,
                    'removed' => array_map(static fn (array $course): string => (string) ($course['code'] ?? '') ?: (string) $course['id'], $plan['removedCourses']),
                    'added' => array_map(static fn (array $box): string => $box['id'].' (term '.$box['term'].')', $plan['added']),
                    'kept' => $plan['kept'],
                ];
            }
        }

        if ($programs === []) {
            return ['payload' => $payload, 'programs' => []];
        }

        $removedIds = array_values(array_unique($removedIds));

        foreach (self::LOCALES as $locale) {
            $localeDepartments = $payload['translations'][$locale]['payload']['plan']['departments'] ?? null;

            if (! is_array($localeDepartments)) {
                continue;
            }

            foreach ($localeDepartments as $d => $department) {
                if (! is_array($department)) {
                    continue;
                }

                $plan = $plans[(string) ($department['id'] ?? '')] ?? null;
                $terms = is_array($department['terms'] ?? null) ? $department['terms'] : [];

                foreach ($terms as $t => $term) {
                    if (! is_array($term)) {
                        continue;
                    }

                    $courses = [];
                    foreach ($this->records($term['courses'] ?? []) as $course) {
                        if (in_array((string) ($course['id'] ?? ''), $plan['removed'] ?? [], true)) {
                            continue;
                        }

                        $course['prerequisites'] = array_values(array_diff($this->stringList($course['prerequisites'] ?? []), $removedIds));
                        $courses[] = $course;
                    }

                    foreach ($plan['added'] ?? [] as $box) {
                        if ((string) ($term['id'] ?? '') === $box['term']) {
                            $courses[] = $this->box($box['id'], $term['id'], $locale);
                        }
                    }

                    foreach ($courses as $c => $course) {
                        $courses[$c]['row'] = $c + 1;
                    }

                    $terms[$t]['courses'] = $courses;
                }

                $localeDepartments[$d]['terms'] = $terms;
            }

            $payload['translations'][$locale]['payload']['plan']['departments'] = $localeDepartments;
        }

        return ['payload' => $payload, 'programs' => $programs];
    }

    public function run(?string $facultySlug, bool $write, ?string $user, bool $publish = false): UniversityElectiveBoxesResultDTO
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

        $slugs = Faculty::query()
            ->whereNotNull('public_slug')
            ->when($facultySlug !== null, fn ($query) => $query->where('public_slug', $facultySlug))
            ->orderBy('id')
            ->pluck('public_slug')
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->all();

        if ($facultySlug !== null && $slugs === []) {
            throw new InvalidArgumentException('Unknown faculty: '.$facultySlug);
        }

        $changes = [];
        $unchanged = [];
        $saved = [];
        $published = [];
        $notPublished = [];

        foreach ($slugs as $slug) {
            $targetKey = 'facilities.'.$slug.'.study_plan';
            // A dry run without a user reads the published plan.
            $draft = $userId !== null ? $this->workflow->latestEditableDraftPayload($targetKey, $userId) : null;
            $hadOpenDraft = is_array($draft) && is_array($draft['translations']['ar'] ?? null);
            $current = $hadOpenDraft ? $draft : $this->facultyPageService->getEditablePayload($targetKey);
            $result = $this->replaceElectives($current);

            if ($result['programs'] === []) {
                $unchanged[] = $slug;

                continue;
            }

            foreach ($result['programs'] as $program) {
                $changes[] = [
                    'faculty' => $slug,
                    'program' => $program['program'],
                    'removed' => implode(', ', $program['removed']) ?: '-',
                    'added' => implode(', ', $program['added']) ?: '-',
                    'kept' => (string) $program['kept'],
                ];
            }

            if (! $write || $userId === null) {
                continue;
            }

            $errors = $this->editorService->validationErrors($result['payload'], $this->boardService->brokenPrerequisiteEdges($current));

            if ($errors !== []) {
                $notPublished[$slug] = 'not saved: '.implode(' ', array_map('strval', collect($errors)->flatten()->unique()->all()));

                continue;
            }

            $this->workflow->saveDraft($targetKey, $result['payload'], $userId);
            $saved[] = $slug;

            if (! $publish) {
                continue;
            }

            try {
                if ($hadOpenDraft) {
                    // An editor's unfinished work is never published from here.
                    $notPublished[$slug] = 'an editor already had a draft open on this plan';
                } elseif ($this->workflow->publish($targetKey, $userId)) {
                    $published[] = $slug;
                } else {
                    $notPublished[$slug] = 'the publish changed nothing';
                }
            } catch (ValidationException $exception) {
                $notPublished[$slug] = implode(' ', array_map('strval', collect($exception->errors())->flatten()->unique()->all()));
            }
        }

        return new UniversityElectiveBoxesResultDTO($changes, $unchanged, $saved, $write, $published, $notPublished);
    }

    /**
     * Which courses a program loses and which boxes it gains.
     *
     * @param  array<string, mixed>  $department
     * @return array{removed: list<string>, removedCourses: list<array<string, mixed>>, added: list<array{id: string, term: string}>, kept: int}
     */
    private function programPlan(array $department): array
    {
        $terms = $this->records($department['terms'] ?? []);
        $removed = [];
        $removedCourses = [];
        $perTerm = [];
        $universityPerTerm = [];
        $ids = [];
        $kept = 0;

        foreach ($terms as $term) {
            $termId = (string) ($term['id'] ?? '');

            foreach ($this->records($term['courses'] ?? []) as $course) {
                $ids[(string) ($course['id'] ?? '')] = true;

                if (($course['type'] ?? null) !== 'university') {
                    continue;
                }

                if (($course['required'] ?? true) !== false) {
                    $universityPerTerm[$termId] = ($universityPerTerm[$termId] ?? 0) + 1;

                    continue;
                }

                if (($course['electiveSlot'] ?? false) === true) {
                    $kept++;

                    continue;
                }

                $removed[] = (string) $course['id'];
                $removedCourses[] = $course;
                $perTerm[$termId] = ($perTerm[$termId] ?? 0) + 1;
            }
        }

        $needed = max(0, self::BOXES - $kept);
        $added = [];

        if ($needed > 0 && $terms !== []) {
            foreach ($this->boxTerms($perTerm, $universityPerTerm, (string) ($terms[0]['id'] ?? ''), $needed) as $termId) {
                $number = 1;
                while (isset($ids['university-elective-'.$number])) {
                    $number++;
                }

                $id = 'university-elective-'.$number;
                $ids[$id] = true;
                $added[] = ['id' => $id, 'term' => $termId];
            }
        }

        return ['removed' => $removed, 'removedCourses' => $removedCourses, 'added' => $added, 'kept' => $kept];
    }

    /**
     * The term of each new box: one per term that listed electives (in term
     * order), then the rest where most electives were listed.
     *
     * @param  array<string, int>  $perTerm  term id => electives listed there
     * @param  array<string, int>  $universityPerTerm  term id => required university courses there
     * @return list<string>
     */
    private function boxTerms(array $perTerm, array $universityPerTerm, string $firstTerm, int $needed): array
    {
        if ($perTerm === []) {
            arsort($universityPerTerm);
            $term = $universityPerTerm === [] ? $firstTerm : (string) array_key_first($universityPerTerm);

            return array_fill(0, $needed, $term);
        }

        $order = array_map('strval', array_keys($perTerm));
        $result = array_slice($order, 0, $needed);
        $left = array_map(static fn (string $term): int => $perTerm[$term] - 1, array_combine($result, $result));

        while (count($result) < $needed) {
            // Ties go to the earlier term (array order is term order).
            $term = (string) array_search(max($left), $left, true);
            $result[] = $term;
            $left[$term]--;
        }

        usort($result, static fn (string $a, string $b): int => array_search($a, $order, true) <=> array_search($b, $order, true));

        return $result;
    }

    /** @return array<string, mixed> */
    private function box(string $id, mixed $termId, string $locale): array
    {
        return [
            'id' => $id,
            'code' => '',
            'titleAr' => self::BOX_TITLE_AR,
            'titleEn' => self::BOX_TITLE_EN,
            'title' => $locale === 'ar' ? self::BOX_TITLE_AR : self::BOX_TITLE_EN,
            'descriptionAr' => '',
            'descriptionEn' => '',
            'description' => '',
            'term' => is_numeric($termId) ? (int) $termId : $termId,
            'type' => 'university',
            'required' => false,
            'electiveSlot' => true,
            'forced' => false,
            'credits' => self::BOX_CREDITS,
            'prerequisites' => [],
            'prerequisiteCredits' => 0,
            'instructor' => null,
            'hours' => ['theory' => 0, 'practical' => 0],
            'lessons' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => is_array($item))) : [];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter(array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value), static fn (string $item): bool => $item !== '')) : [];
    }
}
