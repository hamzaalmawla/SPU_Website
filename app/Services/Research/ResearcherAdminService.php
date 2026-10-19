<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Contracts\Research\ResearcherAdminServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Research\ResearcherDataDTO;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Research\Researcher;
use App\Models\Research\ResearchPublication;
use App\Models\User\User;
use App\Support\PersonName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ResearcherAdminService implements ResearcherAdminServiceInterface
{
    public const LINK_APPROVAL_TOKEN = 'researcher-staff-links-20261019';

    /** @var list<array{id: int, faculty_id: ?int, names: array<int, string>, label: string}>|null Staff, loaded once per request */
    private ?array $staff = null;

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function create(ResearcherDataDTO $data, int $userId): int
    {
        $this->authorize($userId);
        $researcher = new Researcher(['registration_status' => 'registered', 'created_by' => $userId]);
        $this->fill($researcher, $data);
        $researcher->save();

        $this->auditService->log('researcher.created', $userId, Researcher::class, (int) $researcher->getKey());
        $this->flush();

        return (int) $researcher->getKey();
    }

    public function update(int $id, ResearcherDataDTO $data, int $userId): bool
    {
        $this->authorize($userId);
        $researcher = Researcher::query()->findOrFail($id);
        $this->fill($researcher, $data);
        $researcher->save();

        $this->auditService->log('researcher.updated', $userId, Researcher::class, $id);
        $this->flush();

        return true;
    }

    public function delete(int $id, int $userId): bool
    {
        $this->authorize($userId);
        $researcher = Researcher::query()->find($id);

        if (! $researcher instanceof Researcher) {
            return false;
        }

        $researcher->delete();
        $this->auditService->log('researcher.deleted', $userId, Researcher::class, $id);
        $this->flush();

        return true;
    }

    public function staffSuggestions(int $researcherId): array
    {
        $researcher = Researcher::query()->find($researcherId);

        if (! $researcher instanceof Researcher || $researcher->faculty_member_id !== null) {
            return [];
        }

        $name = (string) $researcher->full_name;

        return $this->staff()
            ->map(fn (array $member): array => [...$member, 'rank' => collect($member['names'])->contains(fn (string $staffName): bool => PersonName::same($name, $staffName)) ? 2 : (collect($member['names'])->contains(fn (string $staffName): bool => PersonName::likelySame($name, $staffName)) ? 1 : 0)])
            ->filter(fn (array $member): bool => $member['rank'] > 0)
            ->sortByDesc('rank')
            ->mapWithKeys(fn (array $member): array => [$member['id'] => $member['label']])
            ->all();
    }

    public function researchersWithSuggestions(): array
    {
        return Researcher::query()->whereNull('faculty_member_id')->get(['id', 'full_name'])
            ->filter(fn (Researcher $researcher): bool => $this->staff()->contains(fn (array $member): bool => collect($member['names'])
                ->contains(fn (string $staffName): bool => PersonName::likelySame((string) $researcher->full_name, $staffName))))
            ->map(fn (Researcher $researcher): int => (int) $researcher->getKey())
            ->values()
            ->all();
    }

    public function linkToStaff(int $researcherId, int $facultyMemberId, int $userId): bool
    {
        $this->authorize($userId);
        $researcher = Researcher::query()->findOrFail($researcherId);
        $member = FacultyMember::query()->findOrFail($facultyMemberId);

        $researcher->faculty_member_id = (int) $member->getKey();
        $researcher->faculty_id ??= $member->faculty_id;
        $researcher->save();

        $this->auditService->log('researcher.linked_to_staff', $userId, Researcher::class, $researcherId, ['faculty_member_id' => $facultyMemberId]);
        $this->flush();

        return true;
    }

    public function linkClearMatches(bool $write, ?string $approval): array
    {
        if ($write && $approval !== self::LINK_APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Writing requires --approve='.self::LINK_APPROVAL_TOKEN.'.');
        }

        $lines = [];

        foreach (Researcher::query()->whereNull('faculty_member_id')->get() as $researcher) {
            // Only a name that matches one staff member exactly; looser
            // matches stay suggestions for an editor.
            $matches = $this->staff()->filter(fn (array $member): bool => collect($member['names'])->contains(fn (string $staffName): bool => PersonName::same((string) $researcher->full_name, $staffName)));

            if ($matches->count() !== 1) {
                continue;
            }

            $member = $matches->first();
            $lines[] = 'R'.$researcher->getKey().' '.$researcher->full_name.' -> M'.$member['id'].' '.$member['label'];

            if ($write) {
                $researcher->faculty_member_id = $member['id'];
                $researcher->faculty_id ??= $member['faculty_id'];
                $researcher->save();
            }
        }

        if ($write && $lines !== []) {
            $this->auditService->log('researchers.linked_to_staff', null, Researcher::class, null, ['batch' => self::LINK_APPROVAL_TOKEN, 'links' => count($lines)]);
            $this->flush();
        }

        return $lines;
    }

    public function publications(int $researcherId): array
    {
        $researcher = Researcher::query()->find($researcherId);

        if (! $researcher instanceof Researcher) {
            return [];
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return $researcher->publications()
            ->with('translations')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (ResearchPublication $publication): array => [
                'title' => (string) ($publication->translations->firstWhere('locale', $locale)?->title ?? $publication->translations->first()?->title ?? '#'.$publication->getKey()),
                'year' => (string) ($publication->publication_year ?? ''),
                'editUrl' => route('filament.admin.resources.research-publications.edit', ['record' => $publication->getKey()]),
            ])
            ->values()
            ->all();
    }

    public function facultyOptions(): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return Faculty::query()->with('translations')->whereNotNull('public_slug')->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Faculty $faculty): array => [(int) $faculty->getKey() => (string) ($faculty->translations->firstWhere('locale', $locale)?->name ?? $faculty->public_slug)])
            ->all();
    }

    private function fill(Researcher $researcher, ResearcherDataDTO $data): void
    {
        $name = trim($data->fullName);

        if ($name === '') {
            throw ValidationException::withMessages(['full_name' => ['The researcher\'s name is required.']]);
        }

        foreach (['orcid_url' => $data->orcidUrl, 'scholar_url' => $data->scholarUrl, 'scopus_url' => $data->scopusUrl] as $field => $url) {
            if ($url !== null && trim($url) !== '' && (filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                throw ValidationException::withMessages([$field => ['Enter a full http or https address.']]);
            }
        }

        $researcher->fill([
            'full_name' => $name,
            'email' => $this->nullable($data->email),
            'affiliation' => $this->nullable($data->affiliation),
            'faculty_id' => $data->facultyId,
            'faculty_member_id' => $data->facultyMemberId,
            'orcid_url' => $this->nullable($data->orcidUrl),
            'scholar_url' => $this->nullable($data->scholarUrl),
            'scopus_url' => $this->nullable($data->scopusUrl),
            'expertise' => array_values(array_unique(array_filter(array_map('trim', $data->expertise)))) ?: null,
            'is_enabled' => $data->isEnabled,
            'notes' => $this->nullable($data->notes),
        ]);

        // A linked staff member's faculty is the researcher's unless one was chosen.
        if ($researcher->faculty_id === null && $researcher->faculty_member_id !== null) {
            $researcher->faculty_id = FacultyMember::query()->whereKey($researcher->faculty_member_id)->value('faculty_id');
        }
    }

    /** @return Collection<int, array{id: int, faculty_id: ?int, names: array<int, string>, label: string}> */
    private function staff(): Collection
    {
        return collect($this->staff ??= $this->staffRows());
    }

    /** @return list<array{id: int, faculty_id: ?int, names: array<int, string>, label: string}> */
    private function staffRows(): array
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $rows = [];

        foreach (FacultyMember::query()->with(['translations', 'faculty.translations'])->get() as $member) {
            $name = $member->translations->firstWhere('locale', $locale)?->full_name ?? $member->translations->first()?->full_name ?? $member->slug;
            $faculty = $member->faculty?->translations->firstWhere('locale', $locale)?->name;
            $rows[] = [
                'id' => (int) $member->getKey(),
                'faculty_id' => $member->faculty_id !== null ? (int) $member->faculty_id : null,
                'names' => array_values(array_map('strval', array_filter($member->translations->pluck('full_name')->all()))),
                'label' => trim((string) $name).($faculty ? ' ('.$faculty.')' : ''),
            ];
        }

        return $rows;
    }

    private function authorize(int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || (bool) $user->is_locked || Gate::forUser($user)->denies('create', ResearchPublication::class)) {
            throw new AuthorizationException('This user is not authorized to manage researchers.');
        }
    }

    private function flush(): void
    {
        $this->cacheService->flushTags(['research', 'facilities', 'about', 'public-pages', 'seo', 'sitemap']);
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
