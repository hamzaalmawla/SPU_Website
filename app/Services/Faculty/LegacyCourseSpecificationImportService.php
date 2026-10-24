<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Faculty\FacultyStudyPlanBoardServiceInterface;
use App\Contracts\Faculty\LegacyCourseSpecificationImportServiceInterface;
use App\Contracts\Page\FacultyPageServiceInterface;
use App\DTOs\Faculty\LegacyCourseSpecificationImportResultDTO;
use App\Models\User\User;
use InvalidArgumentException;

/**
 * The old faculty sites had a "course specifications and lectures" page per
 * course. Its specification PDF is attached to the same course in the study
 * plan (matched on the Arabic title), through the CMS draft / publish workflow.
 * Lecture files are left out for now.
 */
final class LegacyCourseSpecificationImportService implements LegacyCourseSpecificationImportServiceInterface
{
    private const EASTERN_DIGITS = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];

    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly FacultyPageServiceInterface $facultyPageService,
        private readonly FacultyStudyPlanBoardServiceInterface $boardService,
    ) {}

    public function import(array $source, string $actor, bool $write): LegacyCourseSpecificationImportResultDTO
    {
        $userId = $this->actorId($actor);
        $byFaculty = [];

        foreach (is_array($source['courses'] ?? null) ? $source['courses'] : [] as $course) {
            if (is_array($course) && is_string($course['faculty'] ?? null) && preg_match('/^[a-z0-9-]+$/', $course['faculty']) === 1) {
                $byFaculty[$course['faculty']][] = $course;
            }
        }

        if ($byFaculty === []) {
            throw new InvalidArgumentException('The source file lists no courses.');
        }

        $rows = [];
        $published = [];
        $drafted = [];

        foreach ($byFaculty as $faculty => $courses) {
            $targetKey = 'facilities.'.$faculty.'.study_plan';

            // A faculty without a published plan only has the sample courses.
            if ($this->cmsWorkflowService->getPublishedPayload($targetKey) === null) {
                foreach ($courses as $course) {
                    $rows[] = $this->row($faculty, $course, 'no_plan');
                }

                continue;
            }

            $draftPayload = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $userId);
            $expectedVersion = $this->cmsWorkflowService->latestEditableDraftVersion($targetKey, $userId);
            $payload = is_array($draftPayload) ? $draftPayload : $this->facultyPageService->getEditablePayload($targetKey);
            $changed = false;

            foreach ($courses as $course) {
                [$payload, $row] = $this->attach($payload, $faculty, $course);
                $rows[] = $row;
                $changed = $changed || $row['status'] === 'attached';
            }

            if (! $write || ! $changed) {
                continue;
            }

            $this->cmsWorkflowService->saveDraft($targetKey, $payload, $userId, $expectedVersion);

            // An editor's unfinished draft is not published along with the import.
            if (is_array($draftPayload)) {
                $drafted[] = $faculty;
            } elseif ($this->cmsWorkflowService->publish($targetKey, $userId)) {
                $published[] = $faculty;
            }
        }

        return new LegacyCourseSpecificationImportResultDTO(
            rows: $rows,
            published: $published,
            drafted: $drafted,
            written: $write,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $course
     * @return array{0: array<string, mixed>, 1: array{faculty: string, title: string, status: string, courses: list<string>, specification: bool}}
     */
    private function attach(array $payload, string $faculty, array $course): array
    {
        $titles = array_map(fn (mixed $title): string => $this->normalize((string) $title), is_array($course['matchTitles'] ?? null) ? $course['matchTitles'] : [$course['titleAr'] ?? '']);
        $matches = $this->matchingCourses($payload, array_values(array_filter($titles)));

        if ($matches === []) {
            return [$payload, $this->row($faculty, $course, 'no_match')];
        }

        $specification = is_string($course['specification'] ?? null) ? $course['specification'] : '';
        $specificationSet = false;
        $ids = [];

        try {
            foreach ($matches as [$departmentId, $courseId]) {
                $form = $this->boardService->courseForm($payload, $departmentId, $courseId);

                if ($form === null) {
                    continue;
                }

                $ids[] = $departmentId.'/'.$courseId;

                if ($specification !== '' && trim((string) ($form['specificationUrl'] ?? '')) === '') {
                    $form['specificationUrl'] = $specification;
                    $specificationSet = true;
                    $payload = $this->boardService->saveCourse($payload, $departmentId, $form);
                }
            }
        } catch (InvalidArgumentException) {
            return [$payload, $this->row($faculty, $course, 'failed', $ids)];
        }

        return [$payload, $this->row($faculty, $course, $specificationSet ? 'attached' : 'unchanged', $ids, $specificationSet)];
    }

    /**
     * Every course (department id, course id) whose Arabic title matches one of the titles.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $titles
     * @return list<array{0: string, 1: string}>
     */
    private function matchingCourses(array $payload, array $titles): array
    {
        $matches = [];
        $departments = $payload['translations']['ar']['payload']['plan']['departments'] ?? [];

        foreach (is_array($departments) ? $departments : [] as $department) {
            foreach (is_array($department['terms'] ?? null) ? $department['terms'] : [] as $term) {
                foreach (is_array($term['courses'] ?? null) ? $term['courses'] : [] as $course) {
                    $title = $this->normalize((string) ($course['titleAr'] ?? $course['title'] ?? ''));

                    if ($title !== '' && in_array($title, $titles, true)) {
                        $matches[] = [(string) ($department['id'] ?? ''), (string) ($course['id'] ?? '')];
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Arabic titles are written many ways: with or without "ال", hamza forms,
     * taa marbuta, Eastern digits, "(1)" or "-1" or "1".
     */
    private function normalize(string $title): string
    {
        $title = mb_strtolower(strtr($title, self::EASTERN_DIGITS));
        $title = (string) preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $title);
        $title = (string) preg_replace('/[إأآا]/u', 'ا', $title);
        $title = str_replace(['ة', 'ى', 'ؤ', 'ئ'], ['ه', 'ي', 'و', 'ي'], $title);
        $title = str_replace('انكليزي', 'انجليزي', $title);
        $title = (string) preg_replace('/[()\-–_\/,.:،]/u', ' ', $title);
        $title = (string) preg_replace('/(\D)(\d)/u', '$1 $2', $title);
        $words = preg_split('/\s+/u', trim($title)) ?: [];

        return implode(' ', array_map(static function (string $word): string {
            if (str_starts_with($word, 'وال') && mb_strlen($word) > 4) {
                return 'و'.mb_substr($word, 3);
            }

            return str_starts_with($word, 'ال') && mb_strlen($word) > 3 ? mb_substr($word, 2) : $word;
        }, $words));
    }

    /**
     * @param  array<string, mixed>  $course
     * @param  list<string>  $ids
     * @return array{faculty: string, title: string, status: string, courses: list<string>, specification: bool}
     */
    private function row(string $faculty, array $course, string $status, array $ids = [], bool $specification = false): array
    {
        return [
            'faculty' => $faculty,
            'title' => (string) ($course['titleAr'] ?? ''),
            'status' => $status,
            'courses' => $ids,
            'specification' => $specification,
        ];
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
