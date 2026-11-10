<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Contracts\Center\CenterCatalogServiceInterface;
use App\Contracts\Staff\StaffIntakeServiceInterface;
use App\DTOs\Staff\StaffIntakeFormDTO;
use App\DTOs\Staff\StaffIntakeHeldFileDTO;
use App\DTOs\Staff\StaffIntakeLinkDTO;
use App\DTOs\Staff\StaffIntakeSubmissionDTO;
use App\DTOs\Staff\StaffIntakeSubmitResultDTO;
use App\DTOs\Staff\StaffMatchSuggestionDTO;
use App\Mail\StaffIntakeSubmittedNotification;
use App\Models\Faculty\Department;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Staff\StaffIntakeAccessLog;
use App\Models\Staff\StaffIntakeLink;
use App\Models\Staff\StaffIntakeRequest;
use App\Models\User\User;
use App\Support\MediaUrlResolver;
use App\Support\PersonName;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The public side of a staff intake link. Everything a teacher sends becomes
 * a pending request; profiles change only when a reviewer approves it.
 *
 * A teacher picks their profile from suggestions by an encrypted reference
 * (link, profile, time), never by a raw id, so a profile cannot be claimed
 * through a link of another faculty or by guessing numbers.
 *
 * A center's link (center_slug) works the same way for the center's staff:
 * their profiles have no faculty and the center's slug, there is no
 * department, and the position is required. A profile in a faculty or
 * another center is a starting point for a new profile in this center, as a
 * profile in another faculty is for a faculty link.
 */
final class StaffIntakeService implements StaffIntakeServiceInterface
{
    private const SUGGESTION_LIMIT = 5;

    /** How long a form opened from a reference may still be sent. */
    private const REF_LIFETIME_SECONDS = 86400;

    /** Photos and CVs of tries that came back, kept until the teacher sends again (holdUploads). */
    private const HELD_DIRECTORY = 'staff-intake/held';

    public function __construct(
        private readonly CenterCatalogServiceInterface $centers,
    ) {}

    public function resolveLink(string $token): ?StaffIntakeLinkDTO
    {
        $link = $this->activeLink($token);

        return $link instanceof StaffIntakeLink ? StaffIntakeLinkService::toDto($link, $this->centers) : null;
    }

    public function recordOpened(string $token, ?string $ipAddress, ?string $userAgent): bool
    {
        $link = $this->activeLink($token);
        if (! $link instanceof StaffIntakeLink) {
            return false;
        }

        $this->log($link, 'opened', null, null, null, [], $ipAddress, $userAgent);

        return true;
    }

    public function suggestions(string $token, string $typedName, string $locale): Collection
    {
        $link = $this->activeLink($token);
        $typedName = mb_substr(trim($typedName), 0, 100);
        if (! $link instanceof StaffIntakeLink || mb_strlen($typedName) < 3) {
            return collect();
        }

        return $this->members()
            ->map(fn (FacultyMember $member): array => ['member' => $member, 'rank' => $this->rank($typedName, $member)])
            ->filter(fn (array $row): bool => $row['rank'] > 0)
            ->sortBy([
                fn (array $a, array $b): int => $b['rank'] <=> $a['rank'],
                fn (array $a, array $b): int => $this->joinsLinkFaculty($b['member'], $link) <=> $this->joinsLinkFaculty($a['member'], $link),
                fn (array $a, array $b): int => $this->name($a['member'], $locale) <=> $this->name($b['member'], $locale),
            ])
            ->take(self::SUGGESTION_LIMIT)
            ->map(fn (array $row): StaffMatchSuggestionDTO => $this->suggestion($link, $row['member'], $row['rank'], $locale))
            ->values();
    }

    public function form(string $token, ?string $ref, ?string $newName, string $locale, ?string $ipAddress, ?string $userAgent): ?StaffIntakeFormDTO
    {
        $link = $this->activeLink($token);
        if (! $link instanceof StaffIntakeLink) {
            return null;
        }

        $facultyName = $this->unitName($link, $locale);
        if ($ref !== null && $ref !== '') {
            $decoded = $this->decodeRef($link, $ref);
            $member = $decoded !== null && $decoded['m'] !== null ? $this->member($decoded['m']) : null;
            if (! $member instanceof FacultyMember) {
                return null;
            }

            $sameFaculty = $this->joinsLinkFaculty($member, $link);
            $departments = $this->departments((int) $link->faculty_id, $locale, $sameFaculty ? $this->nullableId($member->department_id) : null);
            $fields = StaffIntakeFields::fromMember($member);
            if (! $sameFaculty) {
                // A profile in another faculty: the teacher starts from it, and a
                // new profile is made in this faculty on approval.
                $fields['department_id'] = null;
            }

            $this->log($link, 'claimed', null, (int) $member->getKey(), $this->name($member, $locale), [], $ipAddress, $userAgent);

            return new StaffIntakeFormDTO(
                ref: $this->encodeRef($link, (int) $member->getKey(), null),
                mode: $sameFaculty ? StaffIntakeRequest::TYPE_UPDATE : StaffIntakeRequest::TYPE_CREATE,
                facultyName: $facultyName,
                fields: $fields,
                educations: StaffIntakeFields::educationsFromMember($member),
                departments: $departments,
                // Another faculty's profile passes on only media library files.
                photoUrl: $sameFaculty || $member->photo_media_id !== null ? $this->photoUrl($member) : null,
                cvUrl: $sameFaculty || $member->cv_media_id !== null ? $this->cvUrl($member) : null,
                relatedFacultyName: $sameFaculty ? null : $this->memberUnitName($member, $locale),
                isCenter: $link->isForCenter(),
                requiredFields: StaffIntakeFields::required($link->required_fields, $link->isForCenter()),
            );
        }

        $newName = mb_substr(trim((string) $newName), 0, 255);
        $fields = StaffIntakeFields::blank();
        if ($newName !== '') {
            $fields[preg_match('/\p{Arabic}/u', $newName) === 1 ? 'name_ar' : 'name_en'] = $newName;
        }

        $this->log($link, 'started_new', null, null, $newName !== '' ? $newName : null, [], $ipAddress, $userAgent);

        return new StaffIntakeFormDTO(
            ref: $this->encodeRef($link, null, $newName),
            mode: StaffIntakeRequest::TYPE_CREATE,
            facultyName: $facultyName,
            fields: $fields,
            educations: [],
            departments: $this->departments((int) $link->faculty_id, $locale),
            photoUrl: null,
            cvUrl: null,
            relatedFacultyName: null,
            isCenter: $link->isForCenter(),
            requiredFields: StaffIntakeFields::required($link->required_fields, $link->isForCenter()),
        );
    }

    public function submit(StaffIntakeSubmissionDTO $submission): StaffIntakeSubmitResultDTO
    {
        $link = $this->activeLink($submission->token);
        if (! $link instanceof StaffIntakeLink) {
            throw ValidationException::withMessages(['form' => __('staff_intake.public.errors.link_closed', [], $submission->locale)]);
        }

        $decoded = $this->decodeRef($link, $submission->ref);
        if ($decoded === null) {
            throw ValidationException::withMessages(['form' => __('staff_intake.public.errors.expired_form', [], $submission->locale)]);
        }

        $claimed = $decoded['m'] !== null ? $this->member($decoded['m']) : null;
        if ($decoded['m'] !== null && ! $claimed instanceof FacultyMember) {
            throw ValidationException::withMessages(['form' => __('staff_intake.public.errors.expired_form', [], $submission->locale)]);
        }

        $isUpdate = $claimed instanceof FacultyMember && $this->joinsLinkFaculty($claimed, $link);
        $required = StaffIntakeFields::required($link->required_fields, $link->isForCenter());
        if ($link->isForCenter()) {
            // A center has no departments: whatever was sent is dropped.
            $submission = $this->withFields($submission, ['department_id' => null] + $submission->fields);
        }
        $held = $this->heldUploads($link, $submission);
        $this->requireLinkFields($submission, $required);
        $this->requireFiles($submission, $claimed, $isUpdate, $required, $held);
        $fields = $this->cleanFields(
            $submission->fields,
            (int) $link->faculty_id,
            $submission->locale,
            $isUpdate ? $this->nullableId($claimed->department_id) : null,
        );
        $educations = $this->cleanEducations($submission->educations, $isUpdate ? $claimed : null, $submission->locale);

        $candidates = $isUpdate ? collect() : $this->duplicateCandidates($fields, $claimed);
        if ($candidates->isNotEmpty() && ! $submission->confirmedNotDuplicate) {
            return new StaffIntakeSubmitResultDTO(
                reference: null,
                duplicateCandidates: $candidates
                    ->map(fn (FacultyMember $member): StaffMatchSuggestionDTO => $this->suggestion($link, $member, 3, $submission->locale))
                    ->values()
                    ->all(),
            );
        }

        $uploads = $this->storeUploads($submission, $held);
        $snapshot = $isUpdate ? StaffIntakeFields::fromMember($claimed) : null;
        $snapshotEducations = $isUpdate ? StaffIntakeFields::educationsFromMember($claimed) : [];
        $changed = StaffIntakeFields::changedKeys($snapshot ?? StaffIntakeFields::blank(), $fields, $snapshotEducations, $educations, $uploads);
        $submitterName = (string) ($submission->locale === 'ar' ? ($fields['name_ar'] ?: $fields['name_en']) : ($fields['name_en'] ?: $fields['name_ar']));

        $request = DB::transaction(function () use ($link, $claimed, $isUpdate, $fields, $educations, $uploads, $snapshot, $snapshotEducations, $candidates, $changed, $submitterName, $submission): StaffIntakeRequest {
            $lockedLink = StaffIntakeLink::query()->whereKey($link->getKey())->lockForUpdate()->first();
            if (! $lockedLink instanceof StaffIntakeLink || ! $lockedLink->isActive()) {
                throw ValidationException::withMessages(['form' => __('staff_intake.public.errors.link_closed', [], $submission->locale)]);
            }

            $request = StaffIntakeRequest::query()->create([
                'reference' => $this->newReference(),
                'staff_intake_link_id' => $lockedLink->getKey(),
                'faculty_id' => $lockedLink->faculty_id,
                'center_slug' => $lockedLink->center_slug,
                'type' => $isUpdate ? StaffIntakeRequest::TYPE_UPDATE : StaffIntakeRequest::TYPE_CREATE,
                'faculty_member_id' => $isUpdate ? $claimed->getKey() : null,
                'related_faculty_member_id' => ! $isUpdate && $claimed instanceof FacultyMember ? $claimed->getKey() : null,
                'submitter_name' => mb_substr($submitterName, 0, 255),
                'payload' => ['fields' => $fields, 'educations' => $educations, 'uploads' => $uploads, 'locale' => $submission->locale],
                'original_snapshot' => $snapshot !== null ? ['fields' => $snapshot, 'educations' => $snapshotEducations] : null,
                'duplicate_candidates' => $candidates->map(fn (FacultyMember $member): int => (int) $member->getKey())->values()->all(),
                'possible_duplicate' => $candidates->isNotEmpty(),
                'status' => StaffIntakeRequest::STATUS_PENDING,
            ]);

            // One open request per person: a newer one replaces the older.
            foreach ($this->openRequestsForSamePerson($request, $fields) as $older) {
                $older->forceFill(['status' => StaffIntakeRequest::STATUS_SUPERSEDED])->save();
                $this->log($lockedLink, 'superseded', (int) $older->getKey(), $older->faculty_member_id !== null ? (int) $older->faculty_member_id : null, (string) $older->submitter_name, [], $submission->ipAddress, $submission->userAgent);
            }

            $lockedLink->increment('submissions_count');
            $this->log($lockedLink, 'submitted', (int) $request->getKey(), $isUpdate ? (int) $claimed->getKey() : null, $submitterName, $changed, $submission->ipAddress, $submission->userAgent);

            return $request;
        });

        $this->notifyLinkCreator($link, $request);

        return new StaffIntakeSubmitResultDTO(reference: (string) $request->reference);
    }

    private function activeLink(string $token): ?StaffIntakeLink
    {
        if (preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        $link = StaffIntakeLink::query()->where('token_hash', hash('sha256', $token))->first();

        return $link instanceof StaffIntakeLink && $link->isActive() ? $link : null;
    }

    /** @return EloquentCollection<int, FacultyMember> */
    private function members(): EloquentCollection
    {
        return FacultyMember::query()
            ->with(['translations', 'department.translations', 'faculty.translations', 'photoMedia'])
            ->get();
    }

    private function member(int $id): ?FacultyMember
    {
        $member = FacultyMember::query()
            ->with(['translations', 'educations.translations', 'faculty.translations', 'photoMedia', 'cvMedia'])
            ->find($id);

        return $member instanceof FacultyMember ? $member : null;
    }

    /**
     * The link's own profiles and those with no faculty and no center yet (a
     * leader's profile made from the leadership row): sending one updates it,
     * and approval places it in the link's faculty or center, instead of
     * starting a copy.
     */
    private function joinsLinkFaculty(FacultyMember $member, StaffIntakeLink $link): bool
    {
        $memberCenter = is_string($member->center_slug) && $member->center_slug !== '' ? $member->center_slug : null;

        if ($link->isForCenter()) {
            return $member->faculty_id === null && ($memberCenter === null || $memberCenter === $link->center_slug);
        }

        return ($member->faculty_id === null && $memberCenter === null) || (int) $member->faculty_id === (int) $link->faculty_id;
    }

    private function rank(string $typedName, FacultyMember $member): int
    {
        return (int) $member->translations
            ->map(fn ($translation): int => PersonName::matchRank($typedName, (string) $translation->full_name))
            ->max();
    }

    private function suggestion(StaffIntakeLink $link, FacultyMember $member, int $rank, string $locale): StaffMatchSuggestionDTO
    {
        $translation = $member->translations->firstWhere('locale', $locale) ?? $member->translations->first();
        $department = $member->department?->translations->firstWhere('locale', $locale)?->name
            ?? $member->department?->translations->first()?->name;

        return new StaffMatchSuggestionDTO(
            ref: $this->encodeRef($link, (int) $member->getKey(), null),
            nameAr: (string) ($member->translations->firstWhere('locale', 'ar')?->full_name ?? ''),
            nameEn: (string) ($member->translations->firstWhere('locale', 'en')?->full_name ?? ''),
            title: $translation?->title,
            department: $department,
            faculty: $this->memberUnitName($member, $locale),
            inLinkFaculty: $this->joinsLinkFaculty($member, $link),
            photoUrl: $this->photoUrl($member),
            rank: $rank,
        );
    }

    /**
     * Existing profiles that look like the person entering a new one: the
     * same name in either language, or the same email or phone.
     *
     * @param  array<string, mixed>  $fields
     * @return Collection<int, FacultyMember>
     */
    private function duplicateCandidates(array $fields, ?FacultyMember $startedFrom): Collection
    {
        $names = array_values(array_filter([(string) $fields['name_ar'], (string) $fields['name_en']]));
        $email = is_string($fields['email']) ? mb_strtolower($fields['email']) : null;
        $phone = $this->phoneDigits($fields['phone']);

        return $this->members()
            ->reject(fn (FacultyMember $member): bool => $startedFrom instanceof FacultyMember && (int) $member->getKey() === (int) $startedFrom->getKey())
            ->filter(function (FacultyMember $member) use ($names, $email, $phone): bool {
                if ($email !== null && $email !== '' && mb_strtolower((string) $member->email) === $email) {
                    return true;
                }
                if ($phone !== null && $this->phoneDigits($member->phone) === $phone) {
                    return true;
                }

                foreach ($member->translations as $translation) {
                    foreach ($names as $name) {
                        if (PersonName::same($name, (string) $translation->full_name)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->take(10)
            ->values();
    }

    /**
     * Pending requests for the same person: the same profile for an update,
     * or a new entry in the same faculty with the same name.
     *
     * @param  array<string, mixed>  $fields
     * @return EloquentCollection<int, StaffIntakeRequest>
     */
    private function openRequestsForSamePerson(StaffIntakeRequest $request, array $fields): EloquentCollection
    {
        $query = StaffIntakeRequest::query()
            ->whereKeyNot($request->getKey())
            ->when(
                $request->isForCenter(),
                fn ($requests) => $requests->where('center_slug', $request->center_slug),
                fn ($requests) => $requests->where('faculty_id', $request->faculty_id),
            )
            ->where('status', StaffIntakeRequest::STATUS_PENDING)
            ->lockForUpdate();

        if ($request->type === StaffIntakeRequest::TYPE_UPDATE) {
            return $query->where('faculty_member_id', $request->faculty_member_id)->get();
        }

        $names = array_values(array_filter([(string) $fields['name_ar'], (string) $fields['name_en']]));

        return $query->where('type', StaffIntakeRequest::TYPE_CREATE)->get()
            ->filter(function (StaffIntakeRequest $older) use ($names): bool {
                $olderFields = is_array($older->payload['fields'] ?? null) ? $older->payload['fields'] : [];
                foreach ([(string) ($olderFields['name_ar'] ?? ''), (string) ($olderFields['name_en'] ?? '')] as $olderName) {
                    foreach ($names as $name) {
                        if ($olderName !== '' && PersonName::same($name, $olderName)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function cleanFields(array $input, int $facultyId, string $locale, ?int $currentDepartmentId = null): array
    {
        $fields = StaffIntakeFields::blank();

        foreach (StaffIntakeFields::keys() as $key) {
            $value = $input[$key] ?? null;
            if (str_starts_with($key, 'specializations_')) {
                $fields[$key] = array_slice(StaffIntakeFields::list($value), 0, 30);

                continue;
            }

            if ($key === 'department_id') {
                $fields[$key] = is_numeric($value) ? (int) $value : null;

                continue;
            }

            $value = is_string($value) ? trim(strip_tags($value)) : null;
            $fields[$key] = $value !== null && $value !== '' ? $value : null;
        }

        if ($fields['email'] !== null) {
            $fields['email'] = mb_strtolower($fields['email']);
        }

        // Only the faculty's current departments can be picked; a profile that is
        // still in a department since switched off may keep it.
        if ($fields['department_id'] !== null
            && $fields['department_id'] !== $currentDepartmentId
            && ! Department::query()->whereKey($fields['department_id'])->where('faculty_id', $facultyId)->where('is_enabled', true)->exists()) {
            throw ValidationException::withMessages(['department_id' => __('staff_intake.public.errors.department', [], $locale)]);
        }

        return $fields;
    }

    /**
     * Rows left fully empty are dropped. A row keeps its id only when it is one
     * of the claimed profile's own education entries.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function cleanEducations(array $rows, ?FacultyMember $member, string $locale): array
    {
        $ownIds = $member instanceof FacultyMember ? $member->educations->modelKeys() : [];
        $clean = [];

        foreach (array_slice($rows, 0, 20) as $index => $row) {
            $entry = ['id' => null];
            foreach (StaffIntakeFields::EDUCATION as $key) {
                $value = $row[$key] ?? null;
                if (in_array($key, ['year_start', 'year_end'], true)) {
                    $entry[$key] = is_numeric($value) ? (int) $value : null;
                } else {
                    $value = is_string($value) ? trim(strip_tags($value)) : '';
                    $entry[$key] = $value !== '' ? $value : null;
                }
            }

            if (array_filter($entry, static fn (mixed $value): bool => $value !== null) === []) {
                continue;
            }

            if ($entry['degree_ar'] === null || $entry['degree_en'] === null) {
                throw ValidationException::withMessages(["educations.{$index}.degree_ar" => __('staff_intake.public.errors.degree', [], $locale)]);
            }

            if ($entry['year_start'] !== null && $entry['year_end'] !== null && $entry['year_end'] < $entry['year_start']) {
                throw ValidationException::withMessages(["educations.{$index}.year_end" => __('staff_intake.public.errors.years', [], $locale)]);
            }

            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;
            $entry['id'] = $id !== null && in_array($id, $ownIds, true) ? $id : null;
            $clean[] = $entry;
        }

        return $clean;
    }

    /**
     * The photo and the CV, when the link requires them. A profile being
     * updated keeps the ones it has; a new profile started from one in another
     * faculty reuses its media library files. Anything else must send them.
     *
     * A file kept from an earlier try ($held) counts as sent.
     *
     * @param  list<string>  $required
     * @param  array<string, array{path: string, name: string, mime: string, size: int}>  $held
     */
    private function requireFiles(StaffIntakeSubmissionDTO $submission, ?FacultyMember $claimed, bool $isUpdate, array $required, array $held): void
    {
        $hasPhoto = isset($held['photo']) || ($claimed instanceof FacultyMember && ($isUpdate ? $this->photoUrl($claimed) !== null : $claimed->photo_media_id !== null));
        $hasCv = isset($held['cv']) || ($claimed instanceof FacultyMember && ($isUpdate ? $this->cvUrl($claimed) !== null : $claimed->cv_media_id !== null));

        $errors = [];
        if (in_array('photo', $required, true) && ! $submission->photo instanceof UploadedFile && ! $hasPhoto) {
            $errors['photo'] = __('staff_intake.public.errors.photo_required', [], $submission->locale);
        }
        if (in_array('cv', $required, true) && ! $submission->cv instanceof UploadedFile && ! $hasCv) {
            $errors['cv'] = __('staff_intake.public.errors.cv_required', [], $submission->locale);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * A file sent now wins over one kept from an earlier try; a kept one moves
     * next to the others.
     *
     * @param  array<string, array{path: string, name: string, mime: string, size: int}>  $held
     * @return array<string, array{path: string, name: string, mime: string, size: int}>
     */
    private function storeUploads(StaffIntakeSubmissionDTO $submission, array $held): array
    {
        $uploads = [];
        $disk = Storage::disk('local');
        foreach (['photo' => $submission->photo, 'cv' => $submission->cv] as $kind => $file) {
            if (! $file instanceof UploadedFile) {
                if (isset($held[$kind])) {
                    $path = 'staff-intake/'.now()->format('Y/m').'/'.basename($held[$kind]['path']);
                    if (! $disk->move($held[$kind]['path'], $path)) {
                        throw ValidationException::withMessages([$kind => __('staff_intake.public.errors.upload', [], $submission->locale)]);
                    }
                    $uploads[$kind] = ['path' => $path] + $held[$kind];
                }

                continue;
            }

            $path = $file->store('staff-intake/'.now()->format('Y/m'), 'local');
            if (! is_string($path)) {
                throw ValidationException::withMessages([$kind => __('staff_intake.public.errors.upload', [], $submission->locale)]);
            }

            $uploads[$kind] = ['path' => $path, ...$this->fileMeta($file, $kind)];
        }

        return $uploads;
    }

    /** @return array{name: string, mime: string, size: int} */
    private function fileMeta(UploadedFile $file, string $kind): array
    {
        return [
            'name' => mb_substr((string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '', $file->getClientOriginalName()), 0, 150) ?: $kind,
            'mime' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            'size' => (int) $file->getSize(),
        ];
    }

    public function holdUploads(string $token, ?UploadedFile $photo, ?UploadedFile $cv, array $previous = []): array
    {
        $link = $this->activeLink($token);
        if (! $link instanceof StaffIntakeLink) {
            return [];
        }

        $this->pruneHeldFiles();
        $handles = [];
        foreach (['photo' => $photo, 'cv' => $cv] as $kind => $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $extension = $kind === 'cv' ? 'pdf' : (strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension())) ?: 'jpg');
                $path = $file->storeAs(self::HELD_DIRECTORY.'/'.now()->format('Y-m-d'), Str::random(40).'.'.$extension, 'local');
                if (is_string($path) && $path !== '') {
                    $handles[$kind.'_held'] = Crypt::encryptString((string) json_encode([
                        'l' => (int) $link->getKey(), 'k' => $kind, 'p' => $path, 't' => time(), ...$this->fileMeta($file, $kind),
                    ]));

                    continue;
                }
            }

            $earlier = $previous[$kind.'_held'] ?? null;
            if (is_string($earlier) && $this->decodeHeld($link, $kind, $earlier) !== null) {
                $handles[$kind.'_held'] = $earlier;
            }
        }

        return $handles;
    }

    public function heldFiles(string $token, array $handles): array
    {
        $link = $this->activeLink($token);
        if (! $link instanceof StaffIntakeLink) {
            return [];
        }

        $files = [];
        foreach (['photo', 'cv'] as $kind) {
            $handle = $handles[$kind.'_held'] ?? null;
            $held = is_string($handle) ? $this->decodeHeld($link, $kind, $handle) : null;
            if ($held !== null && is_string($handle)) {
                $files[$kind] = new StaffIntakeHeldFileDTO(kind: $kind, handle: $handle, name: $held['name']);
            }
        }

        return $files;
    }

    /** @return array<string, array{path: string, name: string, mime: string, size: int}> */
    private function heldUploads(StaffIntakeLink $link, StaffIntakeSubmissionDTO $submission): array
    {
        $held = [];
        foreach (['photo' => $submission->heldPhoto, 'cv' => $submission->heldCv] as $kind => $handle) {
            $file = is_string($handle) ? $this->decodeHeld($link, $kind, $handle) : null;
            if ($file !== null) {
                $held[$kind] = $file;
            }
        }

        return $held;
    }

    /** @return array{path: string, name: string, mime: string, size: int}|null */
    private function decodeHeld(StaffIntakeLink $link, string $kind, string $handle): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($handle), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || (int) ($data['l'] ?? 0) !== (int) $link->getKey() || ($data['k'] ?? null) !== $kind
            || ! is_int($data['t'] ?? null) || $data['t'] < time() - self::REF_LIFETIME_SECONDS
            || ! is_string($data['p'] ?? null) || ! str_starts_with($data['p'], self::HELD_DIRECTORY.'/') || str_contains($data['p'], '..')
            || ! Storage::disk('local')->exists($data['p'])) {
            return null;
        }

        return [
            'path' => $data['p'],
            'name' => is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : $kind,
            'mime' => is_string($data['mime'] ?? null) ? $data['mime'] : 'application/octet-stream',
            'size' => is_int($data['size'] ?? null) ? $data['size'] : 0,
        ];
    }

    /** Kept files nobody came back for: their day folders go after two days. */
    private function pruneHeldFiles(): void
    {
        try {
            $disk = Storage::disk('local');
            $cutoff = now()->subDays(2)->format('Y-m-d');
            foreach ($disk->directories(self::HELD_DIRECTORY) as $directory) {
                $day = basename($directory);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && $day < $cutoff) {
                    $disk->deleteDirectory($directory);
                }
            }
        } catch (Throwable) {
            // Tidying up must never stop a teacher's form.
        }
    }

    /**
     * The faculty's current departments (Basic Sciences and the others, in page order). The
     * profile's own department stays in the list even if it has since been switched off.
     *
     * @return array<int, string>
     */
    private function departments(int $facultyId, string $locale, ?int $keepId = null): array
    {
        return Department::query()
            ->where('faculty_id', $facultyId)
            ->where(fn ($query) => $query->where('is_enabled', true)->when($keepId !== null, fn ($query) => $query->orWhere('id', $keepId)))
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Department $department): array => [
                (int) $department->getKey() => (string) ($department->translations->firstWhere('locale', $locale)?->name
                    ?? $department->translations->first()?->name
                    ?? '#'.$department->getKey()),
            ])
            ->all();
    }

    private function nullableId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function encodeRef(StaffIntakeLink $link, ?int $memberId, ?string $name): string
    {
        return Crypt::encryptString((string) json_encode(['l' => (int) $link->getKey(), 'm' => $memberId, 'n' => $name, 't' => time()]));
    }

    /** @return array{m: ?int, n: ?string}|null */
    private function decodeRef(StaffIntakeLink $link, string $ref): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString($ref), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || (int) ($data['l'] ?? 0) !== (int) $link->getKey()
            || ! is_int($data['t'] ?? null) || $data['t'] < time() - self::REF_LIFETIME_SECONDS) {
            return null;
        }

        return [
            'm' => is_int($data['m'] ?? null) ? $data['m'] : null,
            'n' => is_string($data['n'] ?? null) ? $data['n'] : null,
        ];
    }

    private function newReference(): string
    {
        do {
            $reference = 'ST-'.strtoupper(Str::random(8));
        } while (StaffIntakeRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @param list<string> $changedFields */
    private function log(StaffIntakeLink $link, string $event, ?int $requestId, ?int $memberId, ?string $name, array $changedFields, ?string $ipAddress, ?string $userAgent): void
    {
        StaffIntakeAccessLog::query()->create([
            'staff_intake_link_id' => $link->getKey(),
            'staff_intake_request_id' => $requestId,
            'faculty_member_id' => $memberId,
            'event' => $event,
            'submitter_name' => $name !== null ? mb_substr($name, 0, 255) : null,
            'changed_fields' => $changedFields !== [] ? $changedFields : null,
            'ip_address' => $ipAddress !== null ? mb_substr($ipAddress, 0, 45) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);
    }

    private function notifyLinkCreator(StaffIntakeLink $link, StaffIntakeRequest $request): void
    {
        $creator = $link->created_by !== null ? User::query()->find($link->created_by) : null;
        if (! $creator instanceof User || ! is_string($creator->email) || $creator->email === '') {
            return;
        }

        try {
            Mail::to($creator->email)->queue(new StaffIntakeSubmittedNotification(
                reference: (string) $request->reference,
                submitterName: (string) $request->submitter_name,
                facultyName: $this->unitName($link, 'ar'),
                isNewProfile: $request->type === StaffIntakeRequest::TYPE_CREATE,
                possibleDuplicate: (bool) $request->possible_duplicate,
                contentLocale: 'ar',
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function name(FacultyMember $member, string $locale): string
    {
        return (string) ($member->translations->firstWhere('locale', $locale)?->full_name
            ?? $member->translations->first()?->full_name
            ?? $member->slug);
    }

    /** The link's faculty or center, by name. */
    private function unitName(StaffIntakeLink $link, string $locale): string
    {
        if ($link->isForCenter()) {
            return (string) ($this->centers->centerName((string) $link->center_slug, $locale) ?? $link->center_slug);
        }

        $faculty = $link->faculty()->with('translations')->first();

        return $faculty instanceof Faculty ? $this->facultyName($faculty, $locale) : '';
    }

    /** The faculty or center a profile belongs to, by name; null for neither. */
    private function memberUnitName(FacultyMember $member, string $locale): ?string
    {
        if ($member->faculty instanceof Faculty) {
            return $this->facultyName($member->faculty, $locale);
        }

        return is_string($member->center_slug) && $member->center_slug !== ''
            ? ($this->centers->centerName($member->center_slug, $locale) ?? $member->center_slug)
            : null;
    }

    /**
     * The text fields the link requires (picked when it was made; both names
     * are checked by the form request). The photo and CV are requireFiles'.
     *
     * @param  list<string>  $required
     */
    private function requireLinkFields(StaffIntakeSubmissionDTO $submission, array $required): void
    {
        $errors = [];
        foreach (array_diff($required, ['photo', 'cv']) as $key) {
            $value = $submission->fields[$key] ?? null;
            $filled = is_array($value)
                ? StaffIntakeFields::list($value) !== []
                : (is_string($value) || is_int($value)) && trim(strip_tags((string) $value)) !== '';
            if (! $filled) {
                $errors[$key] = __('staff_intake.public.errors.required', ['field' => __('staff_intake.fields.'.$key, [], $submission->locale)], $submission->locale);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $fields */
    private function withFields(StaffIntakeSubmissionDTO $submission, array $fields): StaffIntakeSubmissionDTO
    {
        return new StaffIntakeSubmissionDTO(
            token: $submission->token,
            ref: $submission->ref,
            locale: $submission->locale,
            fields: $fields,
            educations: $submission->educations,
            photo: $submission->photo,
            cv: $submission->cv,
            confirmedNotDuplicate: $submission->confirmedNotDuplicate,
            ipAddress: $submission->ipAddress,
            userAgent: $submission->userAgent,
            heldPhoto: $submission->heldPhoto,
            heldCv: $submission->heldCv,
        );
    }

    private function facultyName(Faculty $faculty, string $locale): string
    {
        return (string) ($faculty->translations->firstWhere('locale', $locale)?->name
            ?? $faculty->translations->first()?->name
            ?? $faculty->slug);
    }

    private function photoUrl(FacultyMember $member): ?string
    {
        if ($member->photoMedia !== null) {
            return MediaUrlResolver::resolveImage($member->photoMedia->webp_path, $member->photoMedia->path, $member->photoMedia->disk);
        }

        return MediaUrlResolver::resolveLegacy($member->legacy_photo_path);
    }

    private function cvUrl(FacultyMember $member): ?string
    {
        if ($member->cvMedia !== null) {
            return MediaUrlResolver::resolve($member->cvMedia->path, $member->cvMedia->disk);
        }

        return MediaUrlResolver::resolveLegacy($member->legacy_cv_path);
    }

    private function phoneDigits(mixed $phone): ?string
    {
        $digits = is_string($phone) ? (string) preg_replace('/\D+/', '', strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])) : '';

        // Compare the last nine digits, so +963 9.. and 09.. are one number.
        return strlen($digits) >= 7 ? substr($digits, -9) : null;
    }
}
