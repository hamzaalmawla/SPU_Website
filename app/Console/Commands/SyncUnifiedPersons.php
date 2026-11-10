<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Person\PersonAppointment;
use App\Models\Person\PersonAppointmentTranslation;
use App\Models\Person\PersonEducation;
use App\Models\Person\PersonEducationTranslation;
use App\Models\Person\PersonTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncUnifiedPersons extends Command
{
    protected $signature = 'app:sync-unified-persons {--dry-run} {--slug=} {--force}';

    protected $description = 'Sync faculty_members and existing person categories into unified persons + appointments.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $singleSlug = $this->option('slug');
        $force = $this->option('force');

        if (! $force && ! $dryRun) {
            $this->warn('This command mutates person data. Use --dry-run first, or --force to run.');

            return self::FAILURE;
        }

        $query = FacultyMember::query()->with([
            'canonicalPerson',
            'translations',
            'educations.translations',
            'photoMedia',
            'cvMedia',
            'faculty',
            'department',
            'councilMemberships.translations',
            'councilMemberships.council',
            'researchPublications',
        ]);

        if ($singleSlug) {
            $query->where('slug', $singleSlug);
        }

        $members = $query->get();
        $this->info('Faculty members to sync: '.$members->count());

        $stats = ['created' => 0, 'updated' => 0, 'appointments' => 0, 'publications' => 0, 'councils' => 0, 'educations' => 0];

        foreach ($members as $member) {
            $person = $this->resolvePerson($member, ! $dryRun);

            if (! $person instanceof Person) {
                if ($dryRun) {
                    $stats['created']++;
                    $this->line('  → Would create person ('.$member->slug.')');

                    continue;
                }

                $this->error('Could not resolve person for faculty_member slug='.$member->slug);

                continue;
            }

            if (! $dryRun) {
                $this->syncPersonFromFacultyMember($person, $member);
            }

            if ($person->wasRecentlyCreated) {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }

            if (! $dryRun) {
                $stats['appointments'] += $this->syncFacultyAppointment($person, $member);
                $stats['councils'] += $this->syncCouncilMemberships($person, $member);
                $stats['publications'] += $this->syncPublications($person, $member);
                $stats['educations'] += $this->syncEducations($person, $member);
                $member->forceFill(['person_id' => $person->getKey()])->save();
            }

            $this->line('  → '.($person->wasRecentlyCreated ? 'Created' : 'Updated').' person #'.$person->id.' ('.$person->slug.')');
        }

        $persons = Person::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->with('translations')
            ->get();

        $this->info('Existing persons to create leadership appointments: '.$persons->count());

        foreach ($persons as $person) {
            if (! $dryRun) {
                $stats['appointments'] += $this->syncLeadershipAppointment($person);
            }
            $this->line('  → leadership appointment for person #'.$person->id.' ('.$person->slug.') category='.$person->category);
        }

        $this->newLine();
        $this->info('Stats: '.json_encode($stats));

        return self::SUCCESS;
    }

    private function resolvePerson(FacultyMember $member, bool $createIfMissing = true): ?Person
    {
        if ($member->canonicalPerson instanceof Person) {
            return $member->canonicalPerson;
        }

        if ($member->slug) {
            $existing = Person::query()->where('slug', $member->slug)->first();
            if ($existing instanceof Person) {
                return $existing;
            }
        }

        if ($member->email) {
            $existing = Person::query()->where('email', $member->email)->first();
            if ($existing instanceof Person) {
                return $existing;
            }
        }

        foreach ($member->translations as $translation) {
            $name = $translation->full_name ?? '';
            if ($name === '') {
                continue;
            }
            $existing = Person::query()
                ->whereHas('translations', function ($q) use ($name): void {
                    $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
                })
                ->first();
            if ($existing instanceof Person) {
                return $existing;
            }
        }

        if (! $createIfMissing) {
            return null;
        }

        return Person::create([
            'slug' => $member->slug ?? $this->generateSlug($member),
            'email' => $member->email,
            'phone' => $member->phone,
            'office_location' => $member->office_location,
            'social_links' => $member->social_links,
            'sort_order' => $member->sort_order,
            'is_enabled' => $member->is_enabled,
            'publication_status' => $member->publication_status,
            'published_at' => $member->published_at,
            'photo_media_id' => $member->photo_media_id,
            'cv_media_id' => $member->cv_media_id,
            'legacy_photo_path' => $member->legacy_photo_path,
            'legacy_cv_path' => $member->legacy_cv_path,
            'legacy_ar_cv_path' => $member->legacy_ar_cv_path,
        ]);
    }

    private function syncPersonFromFacultyMember(Person $person, FacultyMember $member): void
    {
        $fields = [
            'email' => $person->email ?? $member->email,
            'phone' => $person->phone ?? $member->phone,
            'office_location' => $person->office_location ?? $member->office_location,
            'social_links' => $person->social_links ?? $member->social_links,
            'photo_media_id' => $person->photo_media_id ?? $member->photo_media_id,
            'cv_media_id' => $person->cv_media_id ?? $member->cv_media_id,
            'legacy_photo_path' => $person->legacy_photo_path ?? $member->legacy_photo_path,
            'legacy_cv_path' => $person->legacy_cv_path ?? $member->legacy_cv_path,
            'legacy_ar_cv_path' => $person->legacy_ar_cv_path ?? $member->legacy_ar_cv_path,
        ];

        $person->fill($fields);
        if ($person->isDirty()) {
            $person->save();
        }

        foreach ($member->translations as $ft) {
            $pt = PersonTranslation::query()
                ->firstOrNew(['person_id' => $person->id, 'locale' => $ft->locale]);

            $pt->name = $pt->name ?: ($ft->full_name ?? '');
            $pt->title = $pt->title ?: ($ft->title ?? '');
            $pt->position = $pt->position ?: ($ft->position ?? '');
            $pt->role = $pt->role ?: ($ft->position ?? '');
            $pt->bio = $pt->bio ?: ($ft->bio ?? '');
            $pt->specializations = $pt->specializations ?: ($ft->specializations ?? null);
            $pt->save();
        }
    }

    private function syncFacultyAppointment(Person $person, FacultyMember $member): int
    {
        $existing = PersonAppointment::query()
            ->where('person_id', $person->id)
            ->where('type', 'faculty_member')
            ->where('faculty_id', $member->faculty_id)
            ->first();

        if ($existing instanceof PersonAppointment) {
            return 0;
        }

        $appointment = PersonAppointment::create([
            'person_id' => $person->id,
            'type' => 'faculty_member',
            'faculty_id' => $member->faculty_id,
            'department_id' => $member->department_id,
            'sort_order' => $member->sort_order,
            'is_enabled' => $member->is_enabled,
        ]);

        foreach ($member->translations as $ft) {
            PersonAppointmentTranslation::create([
                'person_appointment_id' => $appointment->id,
                'locale' => $ft->locale,
                'role_override' => $ft->position ?: $ft->title ?: null,
            ]);
        }

        return 1;
    }

    private function syncLeadershipAppointment(Person $person): int
    {
        $category = $person->category;
        if (! is_string($category) || $category === '') {
            return 0;
        }

        $existing = PersonAppointment::query()
            ->where('person_id', $person->id)
            ->where('type', $category)
            ->first();

        if ($existing instanceof PersonAppointment) {
            return 0;
        }

        $facultySlug = $person->faculty_scope_slug;
        $facultyId = null;
        if ($facultySlug) {
            $faculty = Faculty::query()
                ->where(function ($q) use ($facultySlug): void {
                    $q->where('slug', $facultySlug)
                        ->orWhere('public_slug', $facultySlug)
                        ->orWhere('faculty_scope_slug', $facultySlug);
                })
                ->first();
            if ($faculty instanceof Faculty) {
                $facultyId = $faculty->id;
            }
        }

        $appointment = PersonAppointment::create([
            'person_id' => $person->id,
            'type' => $category,
            'faculty_id' => $facultyId,
            'sort_order' => $person->sort_order,
            'is_enabled' => $person->is_enabled,
        ]);

        foreach ($person->translations as $pt) {
            PersonAppointmentTranslation::create([
                'person_appointment_id' => $appointment->id,
                'locale' => $pt->locale,
                'role_override' => $pt->role ?: $pt->position ?: null,
            ]);
        }

        return 1;
    }

    private function syncCouncilMemberships(Person $person, FacultyMember $member): int
    {
        $count = 0;
        foreach ($member->councilMemberships as $cm) {
            if (! $cm->person_id) {
                $cm->person_id = $person->id;
                $cm->save();
            }

            $existing = PersonAppointment::query()
                ->where('person_id', $person->id)
                ->where('type', 'council')
                ->where('council_id', $cm->council_id)
                ->first();

            if (! $existing instanceof PersonAppointment) {
                PersonAppointment::create([
                    'person_id' => $person->id,
                    'type' => 'council',
                    'council_id' => $cm->council_id,
                    'sort_order' => $cm->sort_order,
                    'is_enabled' => $cm->is_enabled,
                ]);
                $count++;
            }
        }

        return $count;
    }

    private function syncPublications(Person $person, FacultyMember $member): int
    {
        $count = 0;
        foreach ($member->researchPublications as $pub) {
            if (! $pub->person_id) {
                $pub->person_id = $person->id;
                $pub->save();
                $count++;
            }
        }

        return $count;
    }

    private function syncEducations(Person $person, FacultyMember $member): int
    {
        $count = 0;
        foreach ($member->educations as $edu) {
            $existing = PersonEducation::query()
                ->where('person_id', $person->id)
                ->where('sort_order', $edu->sort_order)
                ->first();

            if ($existing instanceof PersonEducation) {
                continue;
            }

            $newEdu = PersonEducation::create([
                'person_id' => $person->id,
                'sort_order' => $edu->sort_order,
                'is_enabled' => $edu->is_enabled,
            ]);

            foreach ($edu->translations as $et) {
                PersonEducationTranslation::create([
                    'person_education_id' => $newEdu->id,
                    'locale' => $et->locale,
                    'degree' => $et->degree,
                    'institution' => $et->institution,
                    'field_of_study' => $et->field_of_study,
                    'year_start' => $et->year_start,
                    'year_end' => $et->year_end,
                    'description' => $et->description,
                ]);
            }

            $count++;
        }

        return $count;
    }

    private function generateSlug(FacultyMember $member): string
    {
        $base = '';
        foreach ($member->translations as $t) {
            if ($t->locale === 'en' && $t->full_name) {
                $base = $t->full_name;
                break;
            }
        }
        if ($base === '') {
            $base = $member->translations->first()?->full_name ?? 'faculty-member-'.$member->id;
        }

        $slug = Str::slug($base);
        $original = $slug;
        $counter = 1;
        while (Person::query()->where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter++;
        }

        return $slug;
    }
}
