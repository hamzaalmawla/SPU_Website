<?php

declare(strict_types=1);

namespace App\Services\Career;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Career\GraduationGradeServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Career\GraduationGradeDTO;
use App\Models\Career\Alumni;
use App\Models\Career\GraduationGrade;
use App\Models\Career\GraduationGradeTranslation;
use App\Models\Cms\CmsTargetContent;
use App\Models\Faculty\Faculty;
use App\Models\User\User;
use App\Support\SearchTextNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GraduationGradeService implements GraduationGradeServiceInterface
{
    private const NAME_MAX = 120;

    private const KEY_MAX = 20;

    private const ALUMNI_TARGET = '/^facilities\.([a-z0-9-]+)\.alumni$/';

    /** @var array<string, array{ar: string, en: string, sort: int, id: int, deleted: bool, replacedBy: ?string}>|null every grade, deleted ones too */
    private ?array $grades = null;

    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function all(): Collection
    {
        return collect($this->grades())
            ->reject(static fn (array $grade): bool => $grade['deleted'])
            ->sortBy([['sort', 'asc'], ['id', 'asc']])
            ->map(static fn (array $grade, string $key): GraduationGradeDTO => new GraduationGradeDTO(
                key: $key,
                nameAr: $grade['ar'],
                nameEn: $grade['en'],
                sortOrder: $grade['sort'],
            ))
            ->values();
    }

    public function find(string $key): ?GraduationGradeDTO
    {
        return $this->all()->first(static fn (GraduationGradeDTO $grade): bool => $grade->key === $key);
    }

    public function options(): array
    {
        return $this->all()
            // "امتياز / Excellent": both names, whichever language the admin is in.
            ->mapWithKeys(static fn (GraduationGradeDTO $grade): array => [$grade->key => $grade->nameAr === $grade->nameEn ? $grade->nameAr : $grade->nameAr.' / '.$grade->nameEn])
            ->all();
    }

    public function resolveKey(?string $key): ?string
    {
        $grades = $this->grades();
        $key = is_string($key) ? trim($key) : '';

        // A deleted grade points at the grade its graduates moved to; a short chain at most.
        for ($hop = 0; $hop < 10 && $key !== ''; $hop++) {
            $grade = $grades[$key] ?? null;

            if ($grade === null) {
                return null;
            }

            if (! $grade['deleted']) {
                return $key;
            }

            $key = (string) $grade['replacedBy'];
        }

        return null;
    }

    public function label(?string $key, string $locale): ?string
    {
        $resolved = $this->resolveKey($key);

        if ($resolved === null) {
            return null;
        }

        $grade = $this->grades()[$resolved];

        return $locale === 'ar' ? $grade['ar'] : $grade['en'];
    }

    public function keyForText(?string $text): ?string
    {
        $needle = $this->comparable((string) $text);

        if ($needle === '') {
            return null;
        }

        // Current grades first, so a name reused after a delete reads as the current grade.
        $grades = collect($this->grades())->sortBy(static fn (array $grade): int => $grade['deleted'] ? 1 : 0);

        foreach ($grades as $key => $grade) {
            if ($this->comparable($grade['ar']) === $needle || $this->comparable($grade['en']) === $needle) {
                return $this->resolveKey((string) $key);
            }
        }

        return null;
    }

    public function itemGradeKey(array $item, ?string $recordGrade): ?string
    {
        // An editor's choice wins, including "no grade".
        if (array_key_exists('gradeKey', $item)) {
            return is_string($item['gradeKey']) ? $this->resolveKey($item['gradeKey']) : null;
        }

        if ($recordGrade !== null && trim($recordGrade) !== '') {
            $resolved = $this->resolveKey($recordGrade) ?? $this->keyForText($recordGrade);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Pages saved before grades were a list hold the grade's name.
        return is_string($item['grade'] ?? null) ? $this->keyForText($item['grade']) : null;
    }

    public function recordGrades(array $alumniIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $alumniIds), static fn (int $id): bool => $id > 0)));
        $grades = [];

        foreach (array_chunk($ids, 500) as $chunk) {
            Alumni::query()
                ->whereIn('id', $chunk)
                ->whereNotNull('grade')
                ->where('grade', '<>', '')
                ->get(['id', 'grade'])
                ->each(function (Alumni $alumni) use (&$grades): void {
                    $grades[(int) $alumni->getKey()] = (string) $alumni->grade;
                });
        }

        return $grades;
    }

    public function importNames(): array
    {
        return $this->all()
            ->mapWithKeys(static fn (GraduationGradeDTO $grade): array => [$grade->key => array_values(array_unique([$grade->nameAr, $grade->nameEn]))])
            ->all();
    }

    public function usageCount(string $key): int
    {
        $keys = $this->keysResolvingTo($key);

        if ($keys === []) {
            return 0;
        }

        $records = Alumni::query()->whereIn('grade', $keys)->count();

        // Graduates an editor typed onto an Alumni page, with no record of their own.
        $pageEntries = 0;

        foreach ($this->publishedAlumniItems() as $item) {
            if ((int) ($item['id'] ?? 0) <= 0 && $this->itemGradeKey($item, null) === $key) {
                $pageEntries++;
            }
        }

        return $records + $pageEntries;
    }

    public function canManage(Authenticatable $user, string $action = 'view'): bool
    {
        return in_array($action, ['view', 'create', 'update', 'delete'], true)
            && $this->access->can($user, self::PERMISSION_COMPONENT.'.'.$action);
    }

    public function create(string $nameAr, string $nameEn, int $userId): GraduationGradeDTO
    {
        $this->authorize($userId, 'create');
        [$nameAr, $nameEn] = $this->validatedNames($nameAr, $nameEn, null);
        $key = $this->newKey($nameEn);

        $grade = DB::transaction(function () use ($key, $nameAr, $nameEn): GraduationGrade {
            $grade = GraduationGrade::query()->create([
                'key' => $key,
                'sort_order' => (int) GraduationGrade::query()->max('sort_order') + 1,
            ]);
            $this->writeNames($grade, $nameAr, $nameEn);

            return $grade;
        });

        $this->changed('graduation_grade.created', $userId, $grade, ['key' => $key, 'name_ar' => $nameAr, 'name_en' => $nameEn]);

        return $this->requireGrade($key);
    }

    public function update(string $key, string $nameAr, string $nameEn, int $userId): GraduationGradeDTO
    {
        $this->authorize($userId, 'update');
        $grade = $this->activeRow($key);
        [$nameAr, $nameEn] = $this->validatedNames($nameAr, $nameEn, $key);
        $before = $this->requireGrade($key);

        DB::transaction(fn () => $this->writeNames($grade, $nameAr, $nameEn));

        $this->changed('graduation_grade.updated', $userId, $grade, [
            'key' => $key,
            'before' => ['ar' => $before->nameAr, 'en' => $before->nameEn],
            'after' => ['ar' => $nameAr, 'en' => $nameEn],
        ]);

        return $this->requireGrade($key);
    }

    public function reorder(array $keys, int $userId): void
    {
        $this->authorize($userId, 'update');
        $current = $this->all()->pluck('key')->all();
        $ordered = array_values(array_unique(array_values(array_filter($keys, static fn (mixed $key): bool => is_string($key) && in_array($key, $current, true)))));
        // Grades the list did not name keep their place after the named ones.
        $ordered = [...$ordered, ...array_values(array_diff($current, $ordered))];

        if ($ordered === $current) {
            return;
        }

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $key) {
                GraduationGrade::query()->where('key', $key)->update(['sort_order' => $index + 1]);
            }
        });

        $this->grades = null;
        $this->auditService->log('graduation_grade.reordered', $userId, GraduationGrade::class, null, ['order' => $ordered]);
        $this->flushPublicCaches();
    }

    public function delete(string $key, ?string $moveToKey, int $userId): int
    {
        $this->authorize($userId, 'delete');
        $grade = $this->activeRow($key);
        $moveToKey = is_string($moveToKey) && trim($moveToKey) !== '' ? trim($moveToKey) : null;

        if ($moveToKey !== null && ($moveToKey === $key || ! $this->find($moveToKey) instanceof GraduationGradeDTO)) {
            throw ValidationException::withMessages(['moveTo' => __('student_lists.grades_admin.errors.move_to_unknown')]);
        }

        $before = $this->requireGrade($key);
        $keys = $this->keysResolvingTo($key);

        $moved = DB::transaction(function () use ($grade, $key, $keys, $moveToKey): int {
            $moved = Alumni::withTrashed()->whereIn('grade', $keys)->update(['grade' => $moveToKey]);

            // Grades deleted earlier into this one now point where it goes.
            GraduationGrade::onlyTrashed()->where('replaced_by_key', $key)->update(['replaced_by_key' => $moveToKey]);

            $grade->forceFill(['replaced_by_key' => $moveToKey])->save();
            $grade->delete();

            return $moved;
        });

        $this->changed('graduation_grade.deleted', $userId, $grade, [
            'key' => $key,
            'name_ar' => $before->nameAr,
            'name_en' => $before->nameEn,
            'moved_to' => $moveToKey,
            'graduates' => $moved,
        ]);

        return $moved;
    }

    public function publishTarget(string $targetKey, array $payload): void
    {
        if (preg_match(self::ALUMNI_TARGET, $targetKey, $match) !== 1) {
            return;
        }

        $faculty = Faculty::query()->where('public_slug', $match[1])->first();

        if (! $faculty instanceof Faculty) {
            return;
        }

        $chosen = [];

        foreach (['ar', 'en'] as $locale) {
            $items = $payload['translations'][$locale]['items'] ?? [];

            foreach (is_array($items) ? $items : [] as $item) {
                $id = is_array($item) ? (int) ($item['id'] ?? 0) : 0;

                // Only a grade picked in the editor is saved; older items leave the record as it is.
                if ($id > 0 && ! array_key_exists($id, $chosen) && array_key_exists('gradeKey', $item)) {
                    $chosen[$id] = $this->itemGradeKey($item, null);
                }
            }
        }

        if ($chosen === []) {
            return;
        }

        foreach (array_chunk(array_keys($chosen), 500) as $ids) {
            Alumni::query()
                ->where('faculty_id', $faculty->getKey())
                ->whereIn('id', $ids)
                ->get(['id', 'grade'])
                ->each(function (Alumni $alumni) use ($chosen): void {
                    $grade = $chosen[(int) $alumni->getKey()] ?? null;

                    if ($this->resolveKey($alumni->grade) !== $grade || ($grade === null && $alumni->grade !== null)) {
                        Alumni::query()->whereKey($alumni->getKey())->update(['grade' => $grade]);
                    }
                });
        }
    }

    /** @return array<string, array{ar: string, en: string, sort: int, id: int, deleted: bool, replacedBy: ?string}> */
    private function grades(): array
    {
        if ($this->grades !== null) {
            return $this->grades;
        }

        $grades = [];

        foreach (GraduationGrade::withTrashed()->with('translations')->orderBy('sort_order')->orderBy('id')->get() as $row) {
            $ar = $this->rowName($row, 'ar');
            $en = $this->rowName($row, 'en');
            $grades[(string) $row->key] = [
                'ar' => $ar ?? $en ?? (string) $row->key,
                'en' => $en ?? $ar ?? (string) $row->key,
                'sort' => (int) $row->sort_order,
                'id' => (int) $row->getKey(),
                'deleted' => $row->trashed(),
                'replacedBy' => is_string($row->replaced_by_key) && $row->replaced_by_key !== '' ? $row->replaced_by_key : null,
            ];
        }

        return $this->grades = $grades;
    }

    private function rowName(GraduationGrade $row, string $locale): ?string
    {
        $name = trim((string) $row->translations->firstWhere('locale', $locale)?->name);

        return $name !== '' ? $name : null;
    }

    /** This key and the deleted keys that now read as it. @return list<string> */
    private function keysResolvingTo(string $key): array
    {
        if ($this->resolveKey($key) !== $key) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', array_keys($this->grades())),
            fn (string $candidate): bool => $this->resolveKey($candidate) === $key,
        ));
    }

    /** @return list<array<string, mixed>> the published Alumni pages' items, one language */
    private function publishedAlumniItems(): array
    {
        $items = [];

        $contents = CmsTargetContent::query()
            ->where('target_key', 'like', 'facilities.%.alumni')
            ->where('status', 'published')
            ->get(['target_key', 'payload_json']);

        foreach ($contents as $content) {
            if (preg_match(self::ALUMNI_TARGET, (string) $content->target_key) !== 1 || ! is_array($content->payload_json)) {
                continue;
            }

            $list = $content->payload_json['translations']['ar']['items'] ?? [];

            foreach (is_array($list) ? $list : [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    private function authorize(int $userId, string $action): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || ! $this->canManage($user, $action)) {
            throw new AuthorizationException(__('student_lists.grades_admin.errors.not_allowed'));
        }
    }

    private function activeRow(string $key): GraduationGrade
    {
        $grade = GraduationGrade::query()->where('key', $key)->first();

        if (! $grade instanceof GraduationGrade) {
            throw ValidationException::withMessages(['grade' => __('student_lists.grades_admin.errors.unknown')]);
        }

        return $grade;
    }

    private function requireGrade(string $key): GraduationGradeDTO
    {
        $this->grades = null;

        return $this->find($key) ?? throw ValidationException::withMessages(['grade' => __('student_lists.grades_admin.errors.unknown')]);
    }

    /** @return array{0: string, 1: string} */
    private function validatedNames(string $nameAr, string $nameEn, ?string $exceptKey): array
    {
        $nameAr = trim(preg_replace('/\s+/u', ' ', $nameAr) ?? '');
        $nameEn = trim(preg_replace('/\s+/u', ' ', $nameEn) ?? '');
        $errors = [];

        if ($nameAr === '') {
            $errors['nameAr'] = __('student_lists.grades_admin.errors.name_required');
        }
        if ($nameEn === '') {
            $errors['nameEn'] = __('student_lists.grades_admin.errors.name_required');
        }
        if (mb_strlen($nameAr) > self::NAME_MAX || mb_strlen($nameEn) > self::NAME_MAX) {
            $errors['nameAr'] ??= __('student_lists.grades_admin.errors.name_too_long');
        }

        foreach ($this->all() as $grade) {
            if ($grade->key === $exceptKey) {
                continue;
            }

            if ($nameAr !== '' && $this->comparable($grade->nameAr) === $this->comparable($nameAr)) {
                $errors['nameAr'] = __('student_lists.grades_admin.errors.duplicate', ['name' => $grade->nameAr]);
            }
            if ($nameEn !== '' && $this->comparable($grade->nameEn) === $this->comparable($nameEn)) {
                $errors['nameEn'] = __('student_lists.grades_admin.errors.duplicate', ['name' => $grade->nameEn]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$nameAr, $nameEn];
    }

    private function writeNames(GraduationGrade $grade, string $nameAr, string $nameEn): void
    {
        foreach (['ar' => $nameAr, 'en' => $nameEn] as $locale => $name) {
            GraduationGradeTranslation::query()->updateOrCreate(
                ['graduation_grade_id' => $grade->getKey(), 'locale' => $locale],
                ['name' => $name],
            );
        }

        $grade->touch();
    }

    /** A short readable key from the English name, unique among every grade ever saved. */
    private function newKey(string $nameEn): string
    {
        $base = Str::limit(trim(Str::slug($nameEn, '_'), '_'), self::KEY_MAX - 3, '');
        $base = $base !== '' ? rtrim($base, '_') : 'grade';
        $taken = GraduationGrade::withTrashed()->pluck('key')->map(static fn (mixed $key): string => (string) $key)->all();
        $key = $base;
        $suffix = 2;

        while (in_array($key, $taken, true)) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    private function comparable(string $text): string
    {
        $folded = SearchTextNormalizer::normalize($text);

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded) ?? '');
    }

    /** @param array<string, mixed> $metadata */
    private function changed(string $action, int $userId, GraduationGrade $grade, array $metadata): void
    {
        $this->grades = null;
        $this->auditService->log($action, $userId, GraduationGrade::class, (int) $grade->getKey(), $metadata);
        $this->flushPublicCaches();
    }

    /** Grade names show on every Alumni page and the Alumni directory. */
    private function flushPublicCaches(): void
    {
        DB::afterCommit(function (): void {
            if (! $this->cacheService->flushTags(['public-pages', 'facilities'])) {
                $this->cacheService->flushAll();
            }
        });
    }
}
