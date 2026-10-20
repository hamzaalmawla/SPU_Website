<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Faculty\FacultyStudyPlanBoardServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use App\Exceptions\ConflictException;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * The visual study plan editor of the faculty admin pages. Every action loads the latest draft,
 * applies one change through the board service, validates the whole plan and saves a new draft
 * version, so the board, the form and the CMS workflow always share one source of truth.
 *
 * Relies on the properties and helpers of ManagesFacultyHomepage.
 */
trait ManagesStudyPlanBoard
{
    public string $boardDepartmentId = '';

    /** @var array<string, mixed>|null */
    public ?array $boardCourse = null;

    public bool $boardCourseIsNew = false;

    public string $boardPrerequisiteSearch = '';

    /** @var array<string, mixed>|null */
    public ?array $boardDepartmentForm = null;

    public bool $boardDepartmentIsNew = false;

    /** @var array<int, mixed> */
    public array $lectureUploads = [];

    public mixed $specificationUpload = null;

    private FacultyStudyPlanBoardServiceInterface $studyPlanBoardService;

    private MediaServiceInterface $boardMediaService;

    /** @var array<string, mixed>|null */
    private ?array $boardCache = null;

    public function isStudyPlanTarget(): bool
    {
        return $this->subpageSlugFromTarget($this->currentTargetKeyForSchema()) === 'study-plan';
    }

    /** @return array<string, mixed> */
    public function studyPlanBoardData(): array
    {
        if ($this->boardCache === null) {
            $this->boardCache = $this->studyPlanBoardService->board($this->studyPlanPayload(), app()->getLocale(), $this->boardDepartmentId);
            $this->boardDepartmentId = (string) $this->boardCache['departmentId'];
        }

        return $this->boardCache;
    }

    /** @return array<string, string> */
    public function boardPrerequisiteChoices(): array
    {
        $options = $this->studyPlanBoardData()['prerequisiteOptions'];
        $self = (string) ($this->boardCourse['id'] ?? '');
        $search = mb_strtolower(trim($this->boardPrerequisiteSearch));
        $selected = array_map('strval', (array) ($this->boardCourse['prerequisites'] ?? []));

        return array_filter(
            $options,
            static fn (string $label, string $id): bool => $id !== $self
                && ($search === '' || in_array($id, $selected, true) || str_contains(mb_strtolower($label), $search)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function selectBoardDepartment(string $departmentId): void
    {
        $this->boardDepartmentId = $departmentId;
        $this->closeBoardPanels();
        $this->boardCache = null;
    }

    public function openBoardCourse(string $courseId): void
    {
        $form = $this->studyPlanBoardService->courseForm($this->studyPlanPayload(), $this->boardDepartmentIdOrFirst(), $courseId);

        if ($form === null) {
            $this->boardNotice('course_missing', 'warning');

            return;
        }

        $this->closeBoardPanels();
        $this->boardCourse = $form;
        $this->boardCourseIsNew = false;
    }

    public function newBoardCourse(string $termId): void
    {
        $this->closeBoardPanels();
        $this->boardCourse = $this->studyPlanBoardService->emptyCourseForm($termId);
        $this->boardCourseIsNew = true;
    }

    public function closeBoardPanels(): void
    {
        $this->boardCourse = null;
        $this->boardCourseIsNew = false;
        $this->boardDepartmentForm = null;
        $this->boardDepartmentIsNew = false;
        $this->boardPrerequisiteSearch = '';
        $this->lectureUploads = [];
        $this->specificationUpload = null;
    }

    public function saveBoardCourse(): void
    {
        $form = $this->boardCourse;

        if (! is_array($form)) {
            return;
        }

        $departmentId = $this->boardDepartmentIdOrFirst();

        if ($this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->saveCourse($payload, $departmentId, $form), 'course_saved')) {
            $this->closeBoardPanels();
        }
    }

    public function deleteBoardCourse(): void
    {
        $courseId = (string) ($this->boardCourse['originalId'] ?? '');

        if ($courseId === '') {
            return;
        }

        $departmentId = $this->boardDepartmentIdOrFirst();

        if ($this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->deleteCourse($payload, $departmentId, $courseId), 'course_deleted')) {
            $this->closeBoardPanels();
        }
    }

    public function moveBoardCourse(string $courseId, string $termId): void
    {
        $departmentId = $this->boardDepartmentIdOrFirst();

        $this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->moveCourse($payload, $departmentId, $courseId, $termId), 'course_moved');
    }

    public function addBoardTerm(): void
    {
        $departmentId = $this->boardDepartmentIdOrFirst();

        $this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->addTerm($payload, $departmentId), 'term_added');
    }

    public function removeBoardTerm(string $termId): void
    {
        $departmentId = $this->boardDepartmentIdOrFirst();

        $this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->removeTerm($payload, $departmentId, $termId), 'term_removed');
    }

    public function editBoardDepartment(): void
    {
        $form = $this->studyPlanBoardService->departmentForm($this->studyPlanPayload(), $this->boardDepartmentIdOrFirst());
        $this->closeBoardPanels();
        $this->boardDepartmentForm = $form;
    }

    public function addBoardPool(): void
    {
        $this->boardDepartmentForm['electivePools'][] = ['id' => 'department', 'requiredHours' => 0, 'descriptionAr' => '', 'descriptionEn' => ''];
    }

    public function removeBoardPool(int $index): void
    {
        unset($this->boardDepartmentForm['electivePools'][$index]);
        $this->boardDepartmentForm['electivePools'] = array_values($this->boardDepartmentForm['electivePools'] ?? []);
    }

    public function addBoardPromotion(): void
    {
        $this->boardDepartmentForm['promotionRequirements'][] = ['fromYear' => 1, 'toYear' => 2, 'requiredCredits' => 0, 'descriptionAr' => '', 'descriptionEn' => ''];
    }

    public function removeBoardPromotion(int $index): void
    {
        unset($this->boardDepartmentForm['promotionRequirements'][$index]);
        $this->boardDepartmentForm['promotionRequirements'] = array_values($this->boardDepartmentForm['promotionRequirements'] ?? []);
    }

    public function newBoardDepartment(): void
    {
        $this->closeBoardPanels();
        $this->boardDepartmentForm = ['nameAr' => '', 'nameEn' => '', 'totalCredits' => 0, 'electivePools' => [], 'promotionRequirements' => []];
        $this->boardDepartmentIsNew = true;
    }

    public function useCalculatedBoardCredits(): void
    {
        if (is_array($this->boardDepartmentForm)) {
            $this->boardDepartmentForm['totalCredits'] = $this->studyPlanBoardData()['department']['calculatedCredits'];
        }
    }

    public function saveBoardDepartment(): void
    {
        $fields = $this->boardDepartmentForm;

        if (! is_array($fields)) {
            return;
        }

        if ($this->boardDepartmentIsNew) {
            $before = $this->boardDepartmentIds();

            if ($this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->addDepartment($payload, $fields), 'department_saved')) {
                $new = array_values(array_diff($this->boardDepartmentIds(), $before));
                $this->boardDepartmentId = (string) ($new[0] ?? $this->boardDepartmentId);
                $this->closeBoardPanels();
            }

            return;
        }

        $departmentId = $this->boardDepartmentIdOrFirst();

        if ($this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->saveDepartment($payload, $departmentId, $fields), 'department_saved')) {
            $this->closeBoardPanels();
        }
    }

    public function deleteBoardDepartment(): void
    {
        $departmentId = $this->boardDepartmentIdOrFirst();

        if ($this->commitStudyPlanChange(fn (array $payload): array => $this->studyPlanBoardService->removeDepartment($payload, $departmentId), 'department_deleted')) {
            $this->boardDepartmentId = '';
            $this->closeBoardPanels();
        }
    }

    public function removeBoardLecture(int $index): void
    {
        if (! is_array($this->boardCourse) || ! isset($this->boardCourse['lessons'][$index])) {
            return;
        }

        unset($this->boardCourse['lessons'][$index]);
        $this->boardCourse['lessons'] = array_values($this->boardCourse['lessons']);
    }

    public function moveBoardLecture(int $index, int $direction): void
    {
        $lessons = $this->boardCourse['lessons'] ?? null;
        $target = $index + $direction;

        if (! is_array($lessons) || ! isset($lessons[$index], $lessons[$target])) {
            return;
        }

        [$lessons[$index], $lessons[$target]] = [$lessons[$target], $lessons[$index]];
        $this->boardCourse['lessons'] = $lessons;
    }

    public function removeBoardSpecification(): void
    {
        if (is_array($this->boardCourse)) {
            $this->boardCourse['specificationUrl'] = '';
        }
    }

    /**
     * Stores the selected PDF in the media library as the course specification of the open
     * course, replacing any earlier one. The course form is saved at the same time.
     */
    public function uploadBoardSpecification(): void
    {
        $courseId = (string) ($this->boardCourse['originalId'] ?? '');

        if ($courseId === '' || ! $this->specificationUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate(
            ['specificationUpload' => ['file', 'mimes:pdf', 'max:20480']],
            [],
            ['specificationUpload' => __('admin.faculty_workspace.board.specification_file')],
        );

        $upload = $this->specificationUpload;

        try {
            $title = $this->lectureTitleFromFilename($upload->getClientOriginalName());
            $result = $this->boardMediaService->upload([
                'file' => new UploadedFile($upload->getRealPath(), $upload->getClientOriginalName(), $upload->getMimeType(), null, true),
                'original_name' => $upload->getClientOriginalName(),
                'directory' => 'media/document/'.now()->format('Y/m'),
                'title_ar' => $title,
                'title_en' => $title,
                'uploaded_by' => $this->authenticatedUserId(),
                'faculty_scope_slug' => $this->facultyScope,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->boardNotice('specification_failed', 'danger');

            return;
        }

        $form = $this->boardCourse;
        $departmentId = $this->boardDepartmentIdOrFirst();
        $saved = $this->commitStudyPlanChange(function (array $payload) use ($departmentId, $form, $result, $courseId): array {
            $payload = $this->studyPlanBoardService->saveCourse($payload, $departmentId, $form);

            return $this->studyPlanBoardService->setSpecification($payload, $departmentId, $courseId, $result->url);
        }, 'specification_added');

        if ($saved) {
            $this->specificationUpload = null;
            $this->boardCache = null;
            $this->boardCourse = $this->studyPlanBoardService->courseForm($this->studyPlanPayload(), $departmentId, $courseId);
        }
    }

    /**
     * Stores every selected PDF in the media library and attaches it to the open course as a
     * lecture. The course form is saved at the same time, so nothing typed is lost.
     */
    public function uploadBoardLectures(): void
    {
        $courseId = (string) ($this->boardCourse['originalId'] ?? '');

        if ($courseId === '' || $this->lectureUploads === []) {
            return;
        }

        $this->validate(
            ['lectureUploads.*' => ['file', 'mimes:pdf', 'max:20480']],
            [],
            ['lectureUploads.*' => __('admin.faculty_workspace.board.lecture_file')],
        );

        $lessons = [];

        try {
            foreach ($this->lectureUploads as $upload) {
                if (! $upload instanceof TemporaryUploadedFile) {
                    continue;
                }

                $title = $this->lectureTitleFromFilename($upload->getClientOriginalName());
                $result = $this->boardMediaService->upload([
                    'file' => new UploadedFile($upload->getRealPath(), $upload->getClientOriginalName(), $upload->getMimeType(), null, true),
                    'original_name' => $upload->getClientOriginalName(),
                    'directory' => 'media/document/'.now()->format('Y/m'),
                    'title_ar' => $title,
                    'title_en' => $title,
                    'uploaded_by' => $this->authenticatedUserId(),
                    'faculty_scope_slug' => $this->facultyScope,
                ]);
                $lessons[] = ['titleAr' => $title, 'titleEn' => $title, 'pdfUrl' => $result->url];
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->boardNotice('lecture_failed', 'danger');

            return;
        }

        $form = $this->boardCourse;
        $departmentId = $this->boardDepartmentIdOrFirst();
        $saved = $this->commitStudyPlanChange(function (array $payload) use ($departmentId, $form, $lessons, $courseId): array {
            $payload = $this->studyPlanBoardService->saveCourse($payload, $departmentId, $form);

            return $this->studyPlanBoardService->addLessons($payload, $departmentId, $courseId, $lessons);
        }, 'lectures_added');

        if ($saved) {
            $this->lectureUploads = [];
            $this->boardCache = null;
            $this->boardCourse = $this->studyPlanBoardService->courseForm($this->studyPlanPayload(), $departmentId, $courseId);
        }
    }

    /** @param Closure(array<string, mixed>): array<string, mixed> $change */
    private function commitStudyPlanChange(Closure $change, string $successKey): bool
    {
        try {
            $targetKey = $this->currentTargetKey();
            $current = $this->studyPlanPayload();
            $payload = $change($current);
            $errors = $this->studyPlanEditorService->validationErrors($payload, $this->studyPlanBoardService->brokenPrerequisiteEdges($current));

            if ($errors !== []) {
                Notification::make()->title(__('admin.faculty_workspace.board.invalid'))->body($this->formatValidationErrors($errors))->danger()->send();

                return false;
            }

            $draft = $this->cmsWorkflowService->saveDraft($targetKey, $payload, $this->authenticatedUserId(), $this->draftVersion);
            $this->draftVersion = $draft->version;
            $this->boardCache = null;
            $this->boardNotice($successKey, 'success');

            return true;
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title(__('admin.faculty_workspace.board.cannot_apply'))->body($exception->getMessage())->warning()->send();
        } catch (ConflictException $exception) {
            $this->draftVersion = $exception->currentVersion;
            Notification::make()->title(__('admin.faculty_workspace.notifications.conflict'))->body(__('admin.faculty_workspace.notifications.conflict_description'))->danger()->persistent()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title(__('admin.faculty_workspace.notifications.save_failed'))->body(__('admin.faculty_workspace.notifications.safe_error'))->danger()->send();
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function studyPlanPayload(): array
    {
        $targetKey = $this->currentTargetKey();
        $draft = $this->cmsWorkflowService->latestEditableDraftPayload($targetKey, $this->authenticatedUserId());

        return is_array($draft) ? $draft : $this->facultyPageService->getEditablePayload($targetKey);
    }

    private function boardDepartmentIdOrFirst(): string
    {
        return $this->boardDepartmentId !== '' ? $this->boardDepartmentId : (string) $this->studyPlanBoardData()['departmentId'];
    }

    /** @return list<string> */
    private function boardDepartmentIds(): array
    {
        $this->boardCache = null;

        return array_map(static fn (array $department): string => $department['id'], $this->studyPlanBoardData()['departments']);
    }

    private function boardNotice(string $key, string $level): void
    {
        $notification = Notification::make()->title(__('admin.faculty_workspace.board.notices.'.$key));
        match ($level) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            default => $notification->danger(),
        };
        $notification->send();
    }

    private function lectureTitleFromFilename(string $filename): string
    {
        $name = trim((string) preg_replace('/[\s_\-]+/u', ' ', pathinfo($filename, PATHINFO_FILENAME)));

        return $name !== '' ? mb_substr($name, 0, 180) : 'Lecture';
    }
}
