<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\PeopleDuplicateReportServiceInterface;
use App\Contracts\Content\PersonIdentityServiceInterface;
use App\DTOs\Content\PeopleDuplicateReportDTO;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Research\Researcher;
use App\Support\PersonName;

/**
 * Lists records that look like one person, so duplicates can be reviewed
 * before anything is merged. It only reads.
 */
final class PeopleDuplicateReportService implements PeopleDuplicateReportServiceInterface
{
    public function __construct(
        private readonly PersonIdentityServiceInterface $identityService,
    ) {}

    public function report(): PeopleDuplicateReportDTO
    {
        $researchers = Researcher::query()->withCount('publications')->get()
            ->map(fn (Researcher $researcher): array => [
                'line' => 'R'.$researcher->getKey().' '.trim($researcher->full_name).' - '.$researcher->publications_count.' papers'
                    .($researcher->faculty_member_id !== null ? ', linked to M'.$researcher->faculty_member_id : ''),
                'names' => [(string) $researcher->full_name],
                'linked_member' => $researcher->faculty_member_id,
            ])->values()->all();

        $members = FacultyMember::query()->withTrashed()->with(['translations', 'faculty'])->withCount('researchPublications')->get()
            ->map(fn (FacultyMember $member): array => [
                'line' => 'M'.$member->getKey().' '.$this->names($member->translations->pluck('full_name')->all())
                    .' - '.($member->faculty?->public_slug ?? 'no faculty').', '.$member->publication_status
                    .($member->is_enabled ? '' : ', hidden').($member->trashed() ? ', DELETED' : '')
                    .($member->person_id !== null ? ', linked to P'.$member->person_id : '')
                    .', '.$member->research_publications_count.' publications, /'.$member->slug,
                'names' => $member->translations->pluck('full_name')->filter()->map(fn (mixed $name): string => (string) $name)->values()->all(),
                'id' => (int) $member->getKey(),
                'person_id' => $member->person_id,
                'trashed' => $member->trashed(),
            ])->values()->all();

        $peopleRows = Person::query()->with('translations')->get();
        $profiles = $this->identityService->profileMemberIds(array_map('intval', $peopleRows->modelKeys()));
        $people = $peopleRows
            ->map(fn (Person $person): array => [
                'line' => 'P'.$person->getKey().' '.$this->names($person->translations->pluck('name')->all())
                    .' - '.($person->category ?? 'no category').', '.$person->publication_status.', /'.$person->slug,
                'names' => $person->translations->pluck('name')->filter()->map(fn (mixed $name): string => (string) $name)->values()->all(),
                'id' => (int) $person->getKey(),
                'member_id' => $profiles[(int) $person->getKey()] ?? null,
            ])->values()->all();

        $liveMembers = array_values(array_filter($members, fn (array $member): bool => ! $member['trashed']));

        return new PeopleDuplicateReportDTO(
            sections: [
                'Researchers that look like the same person' => $this->groups($researchers),
                'Faculty members that look like the same person' => $this->groups($liveMembers),
                'People (leadership and directory profiles) that look like the same person' => $this->groups($people),
                // A leadership row with no profile yet: people:unify or an editor ties it.
                'Leadership rows with no profile that match a faculty member' => $this->pairs(
                    array_values(array_filter($people, fn (array $person): bool => $person['member_id'] === null)),
                    $liveMembers,
                ),
                'Leadership rows and the profile they show' => collect($people)
                    ->filter(fn (array $person): bool => $person['member_id'] !== null)
                    ->map(fn (array $person): array => [
                        $person['line'],
                        collect($members)->firstWhere('id', (int) $person['member_id'])['line'] ?? 'M'.$person['member_id'].' (missing)',
                    ])
                    ->values()
                    ->all(),
                'Researchers who match a faculty member but are not linked' => $this->pairs(
                    array_values(array_filter($researchers, fn (array $researcher): bool => $researcher['linked_member'] === null)),
                    $liveMembers,
                ),
            ],
            totals: [
                'researchers' => count($researchers),
                'faculty members' => count($liveMembers),
                'deleted faculty members' => count($members) - count($liveMembers),
                'people' => count($people),
            ],
        );
    }

    /**
     * @param  list<array{line: string, names: list<string>}>  $rows
     * @return list<list<string>>
     */
    private function groups(array $rows): array
    {
        $groups = [];
        $taken = [];

        foreach ($rows as $index => $row) {
            if (isset($taken[$index])) {
                continue;
            }

            $group = [$row['line']];

            foreach ($rows as $other => $candidate) {
                if ($other > $index && ! isset($taken[$other]) && $this->samePerson($row['names'], $candidate['names'])) {
                    $group[] = $candidate['line'];
                    $taken[$other] = true;
                }
            }

            if (count($group) > 1) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param  list<array{line: string, names: list<string>}>  $left
     * @param  list<array{line: string, names: list<string>}>  $right
     * @return list<list<string>>
     */
    private function pairs(array $left, array $right): array
    {
        $pairs = [];

        foreach ($left as $row) {
            $matches = array_values(array_filter($right, fn (array $candidate): bool => $this->samePerson($row['names'], $candidate['names'])));

            if ($matches !== []) {
                $pairs[] = [$row['line'], ...array_column($matches, 'line')];
            }
        }

        return $pairs;
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    private function samePerson(array $first, array $second): bool
    {
        foreach ($first as $a) {
            foreach ($second as $b) {
                if (PersonName::same($a, $b)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, mixed> $names */
    private function names(array $names): string
    {
        return implode(' / ', array_filter(array_map(fn (mixed $name): string => trim((string) $name), $names)));
    }
}
