<?php

declare(strict_types=1);

namespace App\Services\Career;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Career\StudentListImportServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Career\StudentListImportChoicesDTO;
use App\DTOs\Career\StudentListImportPreviewDTO;
use App\DTOs\Career\StudentListImportResultDTO;
use App\DTOs\Career\StudentListImportRowDTO;
use App\DTOs\Career\StudentListImportSummaryDTO;
use App\DTOs\Career\StudentListSpecializationDTO;
use App\Enums\AcademicSemester;
use App\Models\Career\Alumni;
use App\Models\Career\AlumniTranslation;
use App\Models\Career\HonorStudent;
use App\Models\Career\HonorStudentTranslation;
use App\Models\Career\StudentListImport;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyTranslation;
use App\Support\PersonName;
use App\Support\StudentLists\DecisionListParser;
use App\Support\StudentLists\DepartmentMatcher;
use App\Support\StudentLists\DocxTableReader;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class StudentListImportService implements StudentListImportServiceInterface
{
    private const DISK = 'local';

    private const UPLOAD_PATTERN = '/^student-list-imports\/[A-Za-z0-9][A-Za-z0-9._-]{0,200}\.docx$/';

    /** Fields of an alumni row an import writes, kept before an update for undo. */
    private const ALUMNI_FIELDS = ['department_id', 'graduation_year', 'gpa_points', 'gpa_percent', 'grade', 'academic_year', 'semester', 'is_enabled', 'student_list_import_id'];

    private const HONOR_FIELDS = ['department_id', 'academic_year', 'study_year', 'semester', 'gpa', 'sort_order', 'is_enabled', 'student_list_import_id'];

    public function __construct(
        private readonly AdminAccessServiceInterface $accessService,
        private readonly CmsTargetRegistryInterface $targetRegistry,
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function canImport(Authenticatable $user, string $facultySlug, string $kind): bool
    {
        if (! in_array($kind, [self::KIND_ALUMNI, self::KIND_HONOR_STUDENTS], true)) {
            return false;
        }

        $target = $this->targetRegistry->find($this->targetKey($facultySlug, $kind));

        return $target !== null && $this->accessService->canManageTarget($user, $target, 'publish');
    }

    public function preview(string $facultySlug, string $kind, string $storedPath, string $originalName, ?StudentListImportChoicesDTO $choices = null): StudentListImportPreviewDTO
    {
        $this->pruneStaleUploads($storedPath);
        $faculty = $this->faculty($facultySlug);
        $parsed = $this->parse($kind, $this->absolutePath($storedPath));

        return $this->buildPreview($faculty, $kind, $parsed, $this->displayName($originalName), $choices);
    }

    public function import(string $facultySlug, string $kind, string $storedPath, string $originalName, StudentListImportChoicesDTO $choices, Authenticatable $user): StudentListImportResultDTO
    {
        if (! $this->canImport($user, $facultySlug, $kind)) {
            throw new InvalidArgumentException('student_lists.errors.forbidden');
        }

        $faculty = $this->faculty($facultySlug);
        $this->assertChoices($faculty, $choices);
        $preview = $this->buildPreview($faculty, $kind, $this->parse($kind, $this->absolutePath($storedPath)), $this->displayName($originalName), $choices);
        $userId = (int) $user->getAuthIdentifier();

        $import = DB::transaction(function () use ($faculty, $kind, $preview, $choices, $userId): StudentListImport {
            $import = StudentListImport::query()->create([
                'faculty_id' => $faculty->getKey(),
                'kind' => $kind,
                'original_filename' => $preview->fileName,
                'decision_number' => $preview->decisionNumber,
                'academic_year' => $choices->academicYear,
                'semester' => $choices->semester,
                'department_map' => collect($preview->specializations)
                    ->mapWithKeys(fn (StudentListSpecializationDTO $specialization): array => [$specialization->text => $specialization->departmentId])
                    ->all(),
                'imported_by' => $userId,
            ]);

            $previous = [];
            $created = 0;
            $updated = 0;

            foreach ($preview->rows as $row) {
                if ($row->action === StudentListImportRowDTO::ACTION_SKIP) {
                    continue;
                }

                $before = $kind === self::KIND_ALUMNI
                    ? $this->writeGraduate($faculty, $import, $row, $choices)
                    : $this->writeTopStudent($faculty, $import, $row, $choices);

                if ($before === null) {
                    $created++;
                } else {
                    $previous[(string) $before['id']] = $before;
                    $updated++;
                }
            }

            $import->forceFill([
                'created_count' => $created,
                'updated_count' => $updated,
                'skipped_count' => $preview->skipCount,
                'previous_values' => $previous,
            ])->save();

            return $import;
        });

        $this->auditService->log('student_list.imported', $userId, StudentListImport::class, (int) $import->getKey(), [
            'faculty' => $facultySlug,
            'kind' => $kind,
            'file' => $preview->fileName,
            'decision_number' => $preview->decisionNumber,
            'academic_year' => $choices->academicYear,
            'semester' => $choices->semester,
            'created' => $import->created_count,
            'updated' => $import->updated_count,
            'skipped' => $import->skipped_count,
        ]);

        $this->flushPublicCaches();
        $this->discardUpload($storedPath);

        return new StudentListImportResultDTO(
            importId: (int) $import->getKey(),
            created: (int) $import->created_count,
            updated: (int) $import->updated_count,
            skipped: (int) $import->skipped_count,
        );
    }

    public function undo(int $importId, Authenticatable $user): bool
    {
        $import = StudentListImport::query()->with('faculty')->find($importId);

        if (! $import instanceof StudentListImport || ! $import->faculty instanceof Faculty || $import->undone_at !== null) {
            throw new InvalidArgumentException('student_lists.errors.import_not_found');
        }

        $facultySlug = $this->publicSlug($import->faculty);

        if (! $this->canImport($user, $facultySlug, (string) $import->kind)) {
            throw new InvalidArgumentException('student_lists.errors.forbidden');
        }

        // A later import may have updated rows this one wrote; undoing out of
        // order would restore values that are no longer the previous ones.
        $newer = StudentListImport::query()
            ->active()
            ->where('faculty_id', $import->faculty_id)
            ->where('kind', $import->kind)
            ->where('id', '>', $import->getKey())
            ->exists();

        if ($newer) {
            throw new InvalidArgumentException('student_lists.errors.undo_newer_first');
        }

        $userId = (int) $user->getAuthIdentifier();

        DB::transaction(function () use ($import, $userId): void {
            $previous = is_array($import->previous_values) ? $import->previous_values : [];
            $isAlumni = $import->kind === self::KIND_ALUMNI;
            $query = $isAlumni ? Alumni::query() : HonorStudent::query();

            foreach ($query->where('student_list_import_id', $import->getKey())->get() as $record) {
                $before = $previous[(string) $record->getKey()] ?? null;

                if (! is_array($before)) {
                    $record->translations()->delete();
                    $record->forceDelete();

                    continue;
                }

                $record->forceFill(array_intersect_key($before, array_flip($isAlumni ? self::ALUMNI_FIELDS : self::HONOR_FIELDS)))->save();

                if (is_string($before['name'] ?? null)) {
                    $record->translations()->where('locale', 'ar')->update(['full_name' => $before['name']]);
                }
            }

            $import->forceFill(['undone_at' => now(), 'undone_by' => $userId])->save();
        });

        $this->auditService->log('student_list.undone', $userId, StudentListImport::class, (int) $import->getKey(), [
            'faculty' => $facultySlug,
            'kind' => $import->kind,
            'file' => $import->original_filename,
        ]);

        $this->flushPublicCaches();

        return true;
    }

    public function history(string $facultySlug, string $kind, int $limit = 20): Collection
    {
        $faculty = $this->faculty($facultySlug);

        return StudentListImport::query()
            ->with('importer:id,name')
            ->where('faculty_id', $faculty->getKey())
            ->where('kind', $kind)
            ->latest('id')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (StudentListImport $import): StudentListImportSummaryDTO => new StudentListImportSummaryDTO(
                id: (int) $import->getKey(),
                kind: (string) $import->kind,
                fileName: (string) $import->original_filename,
                decisionNumber: $import->decision_number,
                academicYear: (string) $import->academic_year,
                semester: (string) $import->semester,
                created: (int) $import->created_count,
                updated: (int) $import->updated_count,
                skipped: (int) $import->skipped_count,
                importedBy: $import->importer?->name,
                importedAt: (string) $import->created_at?->format('Y-m-d H:i'),
                undoneAt: $import->undone_at?->format('Y-m-d H:i'),
            ))
            ->values();
    }

    public function discardUpload(string $storedPath): void
    {
        if (preg_match(self::UPLOAD_PATTERN, $storedPath) === 1) {
            Storage::disk(self::DISK)->delete($storedPath);
        }
    }

    /** Uploads previewed but never imported or cancelled are removed after a day. */
    private function pruneStaleUploads(string $keep): void
    {
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->files(self::UPLOAD_DIRECTORY) as $file) {
            if ($file !== $keep && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    /**
     * @param  array{meta: array{decisionNumber: ?string, academicYear: ?string, semester: ?string}, rows: list<array<string, mixed>>}  $parsed
     */
    private function buildPreview(Faculty $faculty, string $kind, array $parsed, string $fileName, ?StudentListImportChoicesDTO $choices): StudentListImportPreviewDTO
    {
        $academicYear = $choices->academicYear ?? $parsed['meta']['academicYear'];
        $semester = $choices->semester ?? $parsed['meta']['semester'];
        $departments = $this->departments($faculty);
        $departmentNames = $departments->mapWithKeys(fn (Department $department): array => [(int) $department->getKey() => $this->departmentName($department, 'ar')])->all();
        $remembered = $this->rememberedDepartments($faculty);

        $specializations = collect($parsed['rows'])
            ->pluck('specialization')
            ->filter(fn (mixed $text): bool => is_string($text) && $text !== '')
            ->countBy()
            ->map(function (int $count, string $text) use ($choices, $remembered, $departmentNames): StudentListSpecializationDTO {
                if ($choices !== null && array_key_exists($text, $choices->departments)) {
                    return new StudentListSpecializationDTO($text, $count, $choices->departments[$text], false);
                }

                if (array_key_exists($text, $remembered) && ($remembered[$text] === null || isset($departmentNames[$remembered[$text]]))) {
                    return new StudentListSpecializationDTO($text, $count, $remembered[$text], false, true);
                }

                $match = DepartmentMatcher::match($text, $departmentNames);

                return new StudentListSpecializationDTO($text, $count, $match['departmentId'], ! $match['confident']);
            })
            ->values()
            ->all();

        $departmentFor = collect($specializations)->mapWithKeys(fn (StudentListSpecializationDTO $specialization): array => [$specialization->text => $specialization->departmentId])->all();
        $identifiers = array_values(array_filter(array_column($parsed['rows'], 'studentIdentifier')));
        $existing = $this->existingIdentifiers($faculty, $kind, $identifiers, $academicYear, $semester);
        $legacyNames = $kind === self::KIND_ALUMNI ? $this->legacyAlumniNames($faculty) : [];
        $seen = [];
        $rows = [];

        foreach ($parsed['rows'] as $row) {
            $issues = $row['issues'];
            $identifier = $row['studentIdentifier'];

            if ($identifier !== null && isset($seen[$identifier])) {
                $issues[] = 'duplicate_in_file';
            }
            if ($identifier !== null) {
                $seen[$identifier] = true;
            }

            $departmentId = is_string($row['specialization']) ? ($departmentFor[$row['specialization']] ?? null) : null;
            $action = match (true) {
                $issues !== [] => StudentListImportRowDTO::ACTION_SKIP,
                isset($existing[$identifier]) => StudentListImportRowDTO::ACTION_UPDATE,
                default => StudentListImportRowDTO::ACTION_CREATE,
            };

            $rows[] = new StudentListImportRowDTO(
                line: (int) $row['line'],
                studentIdentifier: $identifier,
                name: (string) $row['name'],
                specialization: $row['specialization'],
                departmentId: $departmentId,
                departmentName: $departmentId !== null ? ($departmentNames[$departmentId] ?? null) : null,
                action: $action,
                issues: array_values(array_unique($issues)),
                gpaPoints: $row['gpaPoints'] ?? null,
                gpaPercent: $row['gpaPercent'] ?? null,
                grade: $row['grade'] ?? null,
                gpa: $row['gpa'] ?? null,
                rank: $row['rank'] ?? null,
                studyYear: $row['studyYear'] ?? null,
                possibleDuplicate: $action === StudentListImportRowDTO::ACTION_CREATE ? $this->possibleDuplicate((string) $row['name'], $legacyNames) : null,
            );
        }

        $warnings = [];
        if ($academicYear === null || $semester === null) {
            $warnings[] = 'missing_semester';
        }
        if (collect($specializations)->contains(fn (StudentListSpecializationDTO $specialization): bool => $specialization->needsChoice)) {
            $warnings[] = 'departments_need_choice';
        }
        if (collect($rows)->contains(fn (StudentListImportRowDTO $row): bool => $row->possibleDuplicate !== null)) {
            $warnings[] = 'possible_duplicates';
        }

        $count = static fn (string $action): int => count(array_filter($rows, static fn (StudentListImportRowDTO $row): bool => $row->action === $action));
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return new StudentListImportPreviewDTO(
            kind: $kind,
            facultySlug: $this->publicSlug($faculty),
            facultyName: $this->facultyName($faculty, $locale),
            fileName: $fileName,
            decisionNumber: $parsed['meta']['decisionNumber'],
            academicYear: $academicYear,
            semester: $semester,
            rows: $rows,
            specializations: $specializations,
            departmentOptions: $departments->mapWithKeys(fn (Department $department): array => [(int) $department->getKey() => $this->departmentName($department, $locale)])->all(),
            createCount: $count(StudentListImportRowDTO::ACTION_CREATE),
            updateCount: $count(StudentListImportRowDTO::ACTION_UPDATE),
            skipCount: $count(StudentListImportRowDTO::ACTION_SKIP),
            warnings: $warnings,
        );
    }

    /** @return array<string, mixed>|null the row's values before the update, null when it was created */
    private function writeGraduate(Faculty $faculty, StudentListImport $import, StudentListImportRowDTO $row, StudentListImportChoicesDTO $choices): ?array
    {
        $record = Alumni::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('student_identifier', $row->studentIdentifier)
            ->lockForUpdate()
            ->first();
        $before = $record instanceof Alumni ? $this->snapshot($record, self::ALUMNI_FIELDS) : null;

        $record ??= new Alumni([
            'student_identifier' => $row->studentIdentifier,
            'faculty_id' => $faculty->getKey(),
            'is_featured' => false,
        ]);

        $record->fill([
            'department_id' => $row->departmentId,
            'graduation_year' => (int) substr($choices->academicYear, -4),
            'gpa_points' => $row->gpaPoints,
            'gpa_percent' => $row->gpaPercent,
            'grade' => $row->grade,
            'academic_year' => $choices->academicYear,
            'semester' => $choices->semester,
            'is_enabled' => true,
            'student_list_import_id' => $import->getKey(),
        ])->save();

        AlumniTranslation::query()->updateOrCreate(
            ['alumni_id' => $record->getKey(), 'locale' => 'ar'],
            ['full_name' => $row->name],
        );

        return $before;
    }

    /** @return array<string, mixed>|null the row's values before the update, null when it was created */
    private function writeTopStudent(Faculty $faculty, StudentListImport $import, StudentListImportRowDTO $row, StudentListImportChoicesDTO $choices): ?array
    {
        $record = HonorStudent::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('student_identifier', $row->studentIdentifier)
            ->where('academic_year', $choices->academicYear)
            ->where('semester', $choices->semester)
            ->lockForUpdate()
            ->first();
        $before = $record instanceof HonorStudent ? $this->snapshot($record, self::HONOR_FIELDS) : null;

        $record ??= new HonorStudent([
            'student_identifier' => $row->studentIdentifier,
            'faculty_id' => $faculty->getKey(),
        ]);

        $record->fill([
            'department_id' => $row->departmentId,
            'academic_year' => $choices->academicYear,
            'study_year' => $row->studyYear,
            'semester' => $choices->semester,
            'gpa' => $row->gpa,
            'sort_order' => $row->rank ?? 0,
            'is_enabled' => true,
            'student_list_import_id' => $import->getKey(),
        ])->save();

        HonorStudentTranslation::query()->updateOrCreate(
            ['honor_student_id' => $record->getKey(), 'locale' => 'ar'],
            ['full_name' => $row->name],
        );

        return $before;
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function snapshot(Alumni|HonorStudent $record, array $fields): array
    {
        $record->loadMissing('translations');

        return [
            'id' => (int) $record->getKey(),
            ...collect($fields)->mapWithKeys(fn (string $field): array => [$field => $record->getRawOriginal($field)])->all(),
            'name' => $record->translations->firstWhere('locale', 'ar')?->full_name,
        ];
    }

    /**
     * @return array{meta: array{decisionNumber: ?string, academicYear: ?string, semester: ?string}, rows: list<array<string, mixed>>}
     */
    private function parse(string $kind, string $absolutePath): array
    {
        $document = DocxTableReader::read($absolutePath);

        $parsed = match ($kind) {
            self::KIND_ALUMNI => DecisionListParser::graduates($document),
            self::KIND_HONOR_STUDENTS => DecisionListParser::topStudents($document),
            default => throw new InvalidArgumentException('student_lists.errors.unknown_kind'),
        };

        if ($parsed['rows'] === []) {
            throw new InvalidArgumentException('student_lists.errors.no_rows');
        }

        // A top-students file uploaded as graduates (or the other way round) still
        // has university IDs and names; what gives it away is the missing columns.
        $readable = array_filter($parsed['rows'], static fn (array $row): bool => $row['issues'] === []);
        $looksRight = $kind === self::KIND_ALUMNI
            ? array_filter($parsed['rows'], static fn (array $row): bool => ($row['grade'] ?? null) !== null || ($row['gpaPercent'] ?? null) !== null) !== []
            : $readable !== [];

        if (! $looksRight) {
            throw new InvalidArgumentException('student_lists.errors.wrong_kind');
        }

        return $parsed;
    }

    private function absolutePath(string $storedPath): string
    {
        if (preg_match(self::UPLOAD_PATTERN, $storedPath) !== 1 || ! Storage::disk(self::DISK)->exists($storedPath)) {
            throw new InvalidArgumentException('student_lists.errors.upload_missing');
        }

        return Storage::disk(self::DISK)->path($storedPath);
    }

    private function assertChoices(Faculty $faculty, StudentListImportChoicesDTO $choices): void
    {
        if (DecisionListParser::academicYear($choices->academicYear) !== $choices->academicYear) {
            throw new InvalidArgumentException('student_lists.errors.bad_academic_year');
        }

        if (AcademicSemester::tryFrom($choices->semester) === null) {
            throw new InvalidArgumentException('student_lists.errors.bad_semester');
        }

        $allowed = $this->departments($faculty)->map(fn (Department $department): int => (int) $department->getKey())->all();

        foreach ($choices->departments as $departmentId) {
            if ($departmentId !== null && ! in_array($departmentId, $allowed, true)) {
                throw new InvalidArgumentException('student_lists.errors.bad_department');
            }
        }
    }

    /**
     * @param  list<string>  $identifiers
     * @return array<string, true>
     */
    private function existingIdentifiers(Faculty $faculty, string $kind, array $identifiers, ?string $academicYear, ?string $semester): array
    {
        if ($identifiers === []) {
            return [];
        }

        $query = $kind === self::KIND_ALUMNI
            ? Alumni::query()
            : HonorStudent::query()->where('academic_year', $academicYear)->where('semester', $semester);

        return $query
            ->where('faculty_id', $faculty->getKey())
            ->whereIn('student_identifier', $identifiers)
            ->pluck('student_identifier')
            ->mapWithKeys(fn (mixed $identifier): array => [(string) $identifier => true])
            ->all();
    }

    /**
     * Arabic names of this faculty's alumni that came from the old site and
     * have no university ID, so a new graduate cannot be matched to them by ID.
     *
     * @return array<string, string> name => label with graduation year
     */
    private function legacyAlumniNames(Faculty $faculty): array
    {
        return Alumni::query()
            ->where('faculty_id', $faculty->getKey())
            ->whereNull('student_identifier')
            ->with('translations:id,alumni_id,locale,full_name')
            ->get(['id', 'graduation_year'])
            ->mapWithKeys(function (Alumni $alumni): array {
                $name = (string) $alumni->translations->firstWhere('locale', 'ar')?->full_name;

                return $name === '' ? [] : [$name => trim($name.' ('.$alumni->graduation_year.')')];
            })
            ->all();
    }

    /** @param array<string, string> $legacyNames */
    private function possibleDuplicate(string $name, array $legacyNames): ?string
    {
        foreach ($legacyNames as $legacyName => $label) {
            if (PersonName::same($name, $legacyName)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Departments editors confirmed in earlier imports of this faculty, newest first.
     *
     * @return array<string, ?int>
     */
    private function rememberedDepartments(Faculty $faculty): array
    {
        $map = [];

        StudentListImport::query()
            ->active()
            ->where('faculty_id', $faculty->getKey())
            ->whereNotNull('department_map')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'department_map'])
            ->each(function (StudentListImport $import) use (&$map): void {
                foreach (is_array($import->department_map) ? $import->department_map : [] as $text => $departmentId) {
                    if (! array_key_exists((string) $text, $map)) {
                        $map[(string) $text] = is_numeric($departmentId) ? (int) $departmentId : null;
                    }
                }
            });

        return $map;
    }

    /** @return Collection<int, Department> */
    private function departments(Faculty $faculty): Collection
    {
        return Department::query()
            ->where('faculty_id', $faculty->getKey())
            ->where('is_enabled', true)
            ->with('translations:id,department_id,locale,name')
            ->orderBy('sort_order')
            ->get();
    }

    private function departmentName(Department $department, string $locale): string
    {
        $translation = $department->translations->firstWhere('locale', $locale)
            ?? $department->translations->firstWhere('locale', 'ar')
            ?? $department->translations->first();

        return (string) ($translation->name ?? $department->slug);
    }

    private function faculty(string $facultySlug): Faculty
    {
        $faculty = Faculty::query()
            ->with('translations:id,faculty_id,locale,name')
            ->where('public_slug', $facultySlug)
            ->orWhere('slug', $facultySlug)
            ->first();

        if (! $faculty instanceof Faculty) {
            throw new InvalidArgumentException('student_lists.errors.unknown_faculty');
        }

        return $faculty;
    }

    private function facultyName(Faculty $faculty, string $locale): string
    {
        $translation = $faculty->translations->firstWhere('locale', $locale) ?? $faculty->translations->first();

        return $translation instanceof FacultyTranslation ? (string) $translation->name : $this->publicSlug($faculty);
    }

    private function publicSlug(Faculty $faculty): string
    {
        return (string) ($faculty->public_slug ?: $faculty->slug);
    }

    private function targetKey(string $facultySlug, string $kind): string
    {
        return 'facilities.'.$facultySlug.'.'.($kind === self::KIND_ALUMNI ? 'alumni' : 'valedictorians');
    }

    private function displayName(string $originalName): string
    {
        $name = trim(basename(str_replace('\\', '/', $originalName)));

        return mb_substr($name !== '' ? $name : 'decision.docx', 0, 255);
    }

    private function flushPublicCaches(): void
    {
        if (! $this->cacheService->flushTags(['public-pages', 'public-shell', 'facilities', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }
    }
}
