<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Contracts\Content\ProfileAdminServiceInterface;
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

    /** @var array{faculty: array<int, string>, other: array<int, string>}|null */
    private ?array $boardTeacherCache = null;

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

        $choices = array_filter(
            $options,
            static fn (string $label, string $id): bool => $id !== $self
                && ($search === '' || in_array($id, $selected, true) || str_contains(mb_strtolower($label), $search)),
            ARRAY_FILTER_USE_BOTH,
        );

        // The saved prerequisites come first: the list is long and scrolls, so a
        // later term's ticked course would otherwise sit out of sight. The order
        // follows what is saved, not each tick, so rows don't jump while editing.
        $saved = array_flip($this->savedBoardPrerequisites((string) ($this->boardCourse['originalId'] ?? '')));
        $first = [];
        $rest = [];
        foreach ($choices as $id => $label) {
            if (isset($saved[(string) $id])) {
                $first[$id] = $label;
            } else {
                $rest[$id] = $label;
            }
        }

        return $first + $rest;
    }

    /** @return list<string> */
    private function savedBoardPrerequisites(string $courseId): array
    {
        if ($courseId === '') {
            return [];
        }

        foreach ($this->studyPlanBoardData()['terms'] as $term) {
            foreach ($term['courses'] as $card) {
                if ($card['id'] === $courseId) {
                    return $card['prerequisites'];
                }
            }
        }

        return [];
    }

    /**
     * Profiles for the theory and practical teacher pickers.
     *
     * @return array{faculty: array<int, string>, other: array<int, string>}
     */
    public function boardTeacherChoices(): array
    {
        return $this->boardTeacherCache ??= app(ProfileAdminServiceInterface::class)->teacherOptions($this->facultyScope, (int) auth()->id());
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

    /**
     * Adds a profile to the open course's theory or practical teachers. The
     * course is saved with the rest of the form.
     */
    public function addBoardTeacher(string $part, string $memberId): void
    {
        if (! is_array($this->boardCourse) || ! in_array($part, ['theory', 'practical'], true)) {
            return;
        }

        $choices = $this->boardTeacherChoices();
        $memberId = trim($memberId);
        if (! ctype_digit($memberId) || (! isset($choices['faculty'][(int) $memberId]) && ! isset($choices['other'][(int) $memberId]))) {
            return;
        }

        $ids = array_map('strval', (array) ($this->boardCourse[$part.'TeacherIds'] ?? []));
        if (! in_array($memberId, $ids, true)) {
            $ids[] = $memberId;
        }
        $this->boardCourse[$part.'TeacherIds'] = $ids;
    }

    public function removeBoardTeacher(string $part, string $memberId): void
    {
        if (! is_array($this->boardCourse) || ! in_array($part, ['theory', 'practical'], true)) {
            return;
        }

        $this->boardCourse[$part.'TeacherIds'] = array_values(array_filter(
            array_map('strval', (array) ($this->boardCourse[$part.'TeacherIds'] ?? [])),
            static fn (string $id): bool => $id !== $memberId,
        ));
    }

    public function saveBoardCourse(): void
    {
        $form = $this->boardCourse;

        if (! is_array($form)) {
            return;
        }

        // Lecture or specification PDFs chosen but not uploaded yet go in with the
        // save, so pressing Save never drops a file the editor picked.
        if (! $this->boardCourseIsNew && ($this->lectureUploads !== [] || $this->specificationUpload instanceof TemporaryUploadedFile)) {
            if ($this->saveBoardCourseWithFiles('course_saved')) {
                $this->closeBoardPanels();
            }

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
        if ($this->specificationUpload instanceof TemporaryUploadedFile) {
            $this->saveBoardCourseWithFiles('specification_added');
        }
    }

    /**
     * Stores every selected PDF in the media library and attaches it to the open course as a
     * lecture. The course form is saved at the same time, so nothing typed is lost.
     */
    public function uploadBoardLectures(): void
    {
        if ($this->lectureUploads !== []) {
            $this->saveBoardCourseWithFiles('lectures_added');
        }
    }

    /**
     * Saves the open course together with the chosen lecture PDFs and course
     * specification, then reloads the course into the panel.
     */
    private function saveBoardCourseWithFiles(string $successKey): bool
    {
        $courseId = (string) ($this->boardCourse['originalId'] ?? '');

        if ($courseId === '') {
            return false;
        }

        $rules = [];
        $attributes = [];
        if ($this->lectureUploads !== []) {
            $rules['lectureUploads.*'] = ['file', 'mimes:pdf', 'max:20480'];
            $attributes['lectureUploads.*'] = __('admin.faculty_workspace.board.lecture_file');
        }
        if ($this->specificationUpload instanceof TemporaryUploadedFile) {
            $rules['specificationUpload'] = ['file', 'mimes:pdf', 'max:20480'];
            $attributes['specificationUpload'] = __('admin.faculty_workspace.board.specification_file');
        }
        $this->validate($rules, [], $attributes);

        $lessons = [];
        $specificationUrl = null;

        try {
            foreach ($this->lectureUploads as $upload) {
                if ($upload instanceof TemporaryUploadedFile) {
                    $title = $this->lectureTitleFromFilename($upload->getClientOriginalName());
                    $lessons[] = ['titleAr' => $title, 'titleEn' => $title, 'pdfUrl' => $this->storeBoardPdf($upload)];
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->boardNotice('lecture_failed', 'danger');

            return false;
        }

        try {
            if ($this->specificationUpload instanceof TemporaryUploadedFile) {
                $specificationUrl = $this->storeBoardPdf($this->specificationUpload);
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->boardNotice('specification_failed', 'danger');

            return false;
        }

        $form = $this->boardCourse;
        $departmentId = $this->boardDepartmentIdOrFirst();
        $saved = $this->commitStudyPlanChange(function (array $payload) use ($departmentId, $form, $lessons, $specificationUrl, $courseId): array {
            $payload = $this->studyPlanBoardService->saveCourse($payload, $departmentId, $form);

            if ($lessons !== []) {
                $payload = $this->studyPlanBoardService->addLessons($payload, $departmentId, $courseId, $lessons);
            }

            return $specificationUrl !== null
                ? $this->studyPlanBoardService->setSpecification($payload, $departmentId, $courseId, $specificationUrl)
                : $payload;
        }, $successKey);

        if ($saved) {
            $this->lectureUploads = [];
            $this->specificationUpload = null;
            $this->boardCache = null;
            $this->boardCourse = $this->studyPlanBoardService->courseForm($this->studyPlanPayload(), $departmentId, $courseId);
        }

        return $saved;
    }

    /** Stores one PDF in the media library and returns its public URL. */
    private function storeBoardPdf(TemporaryUploadedFile $upload): string
    {
        $title = $this->lectureTitleFromFilename($upload->getClientOriginalName());

        return $this->boardMediaService->upload([
            'file' => new UploadedFile($upload->getRealPath(), $upload->getClientOriginalName(), $upload->getMimeType(), null, true),
            'original_name' => $upload->getClientOriginalName(),
            'directory' => 'media/document/'.now()->format('Y/m'),
            'title_ar' => $title,
            'title_en' => $title,
            'uploaded_by' => $this->authenticatedUserId(),
            'faculty_scope_slug' => $this->facultyScope,
        ])->url;
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
