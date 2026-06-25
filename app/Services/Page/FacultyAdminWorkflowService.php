<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\FacultyAdminWorkflowServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\Models\Career\Alumni;
use App\Models\Career\HonorStudent;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyHighlight;
use App\Models\Faculty\FacultyLab;
use App\Models\Faculty\FacultyPage;
use App\Models\Faculty\FacultyStudentProject;
use App\Models\User\User;
use InvalidArgumentException;

final class FacultyAdminWorkflowService implements FacultyAdminWorkflowServiceInterface
{
    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function facultyOptionsForCurrentUser(?int $userId): array
    {
        $query = Faculty::query()->orderBy('sort_order')->orderBy('public_slug');
        $user = $userId !== null ? User::query()->find($userId) : null;

        if ($user instanceof User && $user->role_slug === 'faculty_editor') {
            $scope = is_string($user->faculty_scope_slug) ? $user->faculty_scope_slug : '';
            $query->where('faculty_scope_slug', $scope === '' ? '__none__' : $scope);
        }

        return $query->pluck('public_slug', 'id')->all();
    }

    public function recordFacultyCreated(int $facultyId, ?int $userId): bool
    {
        $faculty = $this->faculty($facultyId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.created', $userId, Faculty::class, $facultyId, $this->facultyMetadata($faculty));
    }

    public function recordFacultyUpdated(int $facultyId, ?int $userId, array $before): bool
    {
        $faculty = $this->faculty($facultyId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.updated', $userId, Faculty::class, $facultyId, [
            'before' => $before,
            'after' => $this->facultyMetadata($faculty),
        ]);
    }

    public function deleteFaculty(int $facultyId, ?int $userId): bool
    {
        $faculty = $this->faculty($facultyId);
        $metadata = $this->facultyMetadata($faculty);
        $deleted = (bool) $faculty->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.deleted', $userId, Faculty::class, $facultyId, $metadata);
        }

        return $deleted;
    }

    public function recordFacultyPageCreated(int $pageId, ?int $userId): bool
    {
        $page = $this->facultyPage($pageId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.page.created', $userId, FacultyPage::class, $pageId, $this->pageMetadata($page));
    }

    public function recordFacultyPageUpdated(int $pageId, ?int $userId, array $before): bool
    {
        $page = $this->facultyPage($pageId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.page.updated', $userId, FacultyPage::class, $pageId, [
            'before' => $before,
            'after' => $this->pageMetadata($page),
        ]);
    }

    public function deleteFacultyPage(int $pageId, ?int $userId): bool
    {
        $page = $this->facultyPage($pageId);
        $metadata = $this->pageMetadata($page);
        $deleted = (bool) $page->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.page.deleted', $userId, FacultyPage::class, $pageId, $metadata);
        }

        return $deleted;
    }

    public function recordFacultyHighlightCreated(int $highlightId, ?int $userId): bool
    {
        $highlight = $this->facultyHighlight($highlightId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.highlight.created', $userId, FacultyHighlight::class, $highlightId, $this->highlightMetadata($highlight));
    }

    public function recordFacultyHighlightUpdated(int $highlightId, ?int $userId, array $before): bool
    {
        $highlight = $this->facultyHighlight($highlightId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.highlight.updated', $userId, FacultyHighlight::class, $highlightId, [
            'before' => $before,
            'after' => $this->highlightMetadata($highlight),
        ]);
    }

    public function deleteFacultyHighlight(int $highlightId, ?int $userId): bool
    {
        $highlight = $this->facultyHighlight($highlightId);
        $metadata = $this->highlightMetadata($highlight);
        $deleted = (bool) $highlight->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.highlight.deleted', $userId, FacultyHighlight::class, $highlightId, $metadata);
        }

        return $deleted;
    }

    public function recordFacultyLabCreated(int $labId, ?int $userId): bool
    {
        $lab = $this->facultyLab($labId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.lab.created', $userId, FacultyLab::class, $labId, $this->labMetadata($lab));
    }

    public function recordFacultyLabUpdated(int $labId, ?int $userId, array $before): bool
    {
        $lab = $this->facultyLab($labId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.lab.updated', $userId, FacultyLab::class, $labId, [
            'before' => $before,
            'after' => $this->labMetadata($lab),
        ]);
    }

    public function deleteFacultyLab(int $labId, ?int $userId): bool
    {
        $lab = $this->facultyLab($labId);
        $metadata = $this->labMetadata($lab);
        $deleted = (bool) $lab->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.lab.deleted', $userId, FacultyLab::class, $labId, $metadata);
        }

        return $deleted;
    }

    public function recordFacultyProjectCreated(int $projectId, ?int $userId): bool
    {
        $project = $this->facultyProject($projectId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.project.created', $userId, FacultyStudentProject::class, $projectId, $this->projectMetadata($project));
    }

    public function recordFacultyProjectUpdated(int $projectId, ?int $userId, array $before): bool
    {
        $project = $this->facultyProject($projectId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.project.updated', $userId, FacultyStudentProject::class, $projectId, [
            'before' => $before,
            'after' => $this->projectMetadata($project),
        ]);
    }

    public function deleteFacultyProject(int $projectId, ?int $userId): bool
    {
        $project = $this->facultyProject($projectId);
        $metadata = $this->projectMetadata($project);
        $deleted = (bool) $project->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.project.deleted', $userId, FacultyStudentProject::class, $projectId, $metadata);
        }

        return $deleted;
    }

    public function recordAlumniCreated(int $alumniId, ?int $userId): bool
    {
        $alumni = $this->alumni($alumniId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.alumni.created', $userId, Alumni::class, $alumniId, $this->alumniMetadata($alumni));
    }

    public function recordAlumniUpdated(int $alumniId, ?int $userId, array $before): bool
    {
        $alumni = $this->alumni($alumniId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.alumni.updated', $userId, Alumni::class, $alumniId, [
            'before' => $before,
            'after' => $this->alumniMetadata($alumni),
        ]);
    }

    public function deleteAlumni(int $alumniId, ?int $userId): bool
    {
        $alumni = $this->alumni($alumniId);
        $metadata = $this->alumniMetadata($alumni);
        $deleted = (bool) $alumni->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.alumni.deleted', $userId, Alumni::class, $alumniId, $metadata);
        }

        return $deleted;
    }

    public function recordHonorStudentCreated(int $honorStudentId, ?int $userId): bool
    {
        $student = $this->honorStudent($honorStudentId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.honor_student.created', $userId, HonorStudent::class, $honorStudentId, $this->honorStudentMetadata($student));
    }

    public function recordHonorStudentUpdated(int $honorStudentId, ?int $userId, array $before): bool
    {
        $student = $this->honorStudent($honorStudentId);
        $this->invalidateFacilitiesCache();

        return $this->auditService->log('faculty.honor_student.updated', $userId, HonorStudent::class, $honorStudentId, [
            'before' => $before,
            'after' => $this->honorStudentMetadata($student),
        ]);
    }

    public function deleteHonorStudent(int $honorStudentId, ?int $userId): bool
    {
        $student = $this->honorStudent($honorStudentId);
        $metadata = $this->honorStudentMetadata($student);
        $deleted = (bool) $student->delete();

        if ($deleted) {
            $this->invalidateFacilitiesCache();
            $this->auditService->log('faculty.honor_student.deleted', $userId, HonorStudent::class, $honorStudentId, $metadata);
        }

        return $deleted;
    }

    /** @return array<string, mixed> */
    private function facultyMetadata(Faculty $faculty): array
    {
        return [
            'slug' => $faculty->getAttribute('slug'),
            'public_slug' => $faculty->getAttribute('public_slug'),
            'faculty_scope_slug' => $faculty->getAttribute('faculty_scope_slug'),
            'sort_order' => $faculty->getAttribute('sort_order'),
            'is_enabled' => $faculty->getAttribute('is_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function pageMetadata(FacultyPage $page): array
    {
        return [
            'faculty_id' => $page->getAttribute('faculty_id'),
            'slug' => $page->getAttribute('slug'),
            'kind' => $page->getAttribute('kind'),
            'sort_order' => $page->getAttribute('sort_order'),
            'is_enabled' => $page->getAttribute('is_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function highlightMetadata(FacultyHighlight $highlight): array
    {
        return [
            'faculty_id' => $highlight->getAttribute('faculty_id'),
            'key' => $highlight->getAttribute('key'),
            'value' => $highlight->getAttribute('value'),
            'url' => $highlight->getAttribute('url'),
            'sort_order' => $highlight->getAttribute('sort_order'),
            'is_enabled' => $highlight->getAttribute('is_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function labMetadata(FacultyLab $lab): array
    {
        return [
            'faculty_id' => $lab->getAttribute('faculty_id'),
            'slug' => $lab->getAttribute('slug'),
            'image' => $lab->getAttribute('image'),
            'sort_order' => $lab->getAttribute('sort_order'),
            'is_enabled' => $lab->getAttribute('is_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function projectMetadata(FacultyStudentProject $project): array
    {
        return [
            'faculty_id' => $project->getAttribute('faculty_id'),
            'slug' => $project->getAttribute('slug'),
            'image' => $project->getAttribute('image'),
            'sort_order' => $project->getAttribute('sort_order'),
            'is_enabled' => $project->getAttribute('is_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function alumniMetadata(Alumni $alumni): array
    {
        return [
            'student_identifier' => $alumni->getAttribute('student_identifier'),
            'faculty_id' => $alumni->getAttribute('faculty_id'),
            'department_id' => $alumni->getAttribute('department_id'),
            'degree' => $alumni->getAttribute('degree'),
            'graduation_year' => $alumni->getAttribute('graduation_year'),
            'is_featured' => $alumni->getAttribute('is_featured'),
            'is_enabled' => $alumni->getAttribute('is_enabled'),
        ];
    }

    /** @return array<string, mixed> */
    private function honorStudentMetadata(HonorStudent $student): array
    {
        return [
            'student_identifier' => $student->getAttribute('student_identifier'),
            'faculty_id' => $student->getAttribute('faculty_id'),
            'department_id' => $student->getAttribute('department_id'),
            'academic_year' => $student->getAttribute('academic_year'),
            'gpa' => $student->getAttribute('gpa'),
            'sort_order' => $student->getAttribute('sort_order'),
            'is_enabled' => $student->getAttribute('is_enabled'),
        ];
    }

    private function faculty(int $facultyId): Faculty
    {
        $faculty = Faculty::query()->find($facultyId);

        if (! $faculty instanceof Faculty) {
            throw new InvalidArgumentException('Faculty not found.');
        }

        return $faculty;
    }

    private function facultyPage(int $pageId): FacultyPage
    {
        $page = FacultyPage::query()->find($pageId);

        if (! $page instanceof FacultyPage) {
            throw new InvalidArgumentException('Faculty page not found.');
        }

        return $page;
    }

    private function facultyHighlight(int $highlightId): FacultyHighlight
    {
        $highlight = FacultyHighlight::query()->find($highlightId);

        if (! $highlight instanceof FacultyHighlight) {
            throw new InvalidArgumentException('Faculty highlight not found.');
        }

        return $highlight;
    }

    private function facultyLab(int $labId): FacultyLab
    {
        $lab = FacultyLab::query()->find($labId);

        if (! $lab instanceof FacultyLab) {
            throw new InvalidArgumentException('Faculty lab not found.');
        }

        return $lab;
    }

    private function facultyProject(int $projectId): FacultyStudentProject
    {
        $project = FacultyStudentProject::query()->find($projectId);

        if (! $project instanceof FacultyStudentProject) {
            throw new InvalidArgumentException('Faculty project not found.');
        }

        return $project;
    }

    private function alumni(int $alumniId): Alumni
    {
        $alumni = Alumni::query()->find($alumniId);

        if (! $alumni instanceof Alumni) {
            throw new InvalidArgumentException('Alumni record not found.');
        }

        return $alumni;
    }

    private function honorStudent(int $honorStudentId): HonorStudent
    {
        $student = HonorStudent::query()->find($honorStudentId);

        if (! $student instanceof HonorStudent) {
            throw new InvalidArgumentException('Honor student not found.');
        }

        return $student;
    }

    private function invalidateFacilitiesCache(): void
    {
        if (! $this->cacheService->flushTags(['facilities', 'public-pages', 'public-shell', 'seo', 'sitemap', 'navigation'])) {
            $this->cacheService->flushAll();
        }
    }
}
