<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\FacultyMemberActivityReportServiceInterface;
use App\Enums\PublicationStatus;
use App\Models\Faculty\Faculty;
use App\Models\Person\FacultyMember;
use App\Models\Person\Person;
use App\Models\Person\PersonAppointment;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class FacultyMemberActivityReportService implements FacultyMemberActivityReportServiceInterface
{
    public function report(string $facultySlug, string $since): array
    {
        $faculty = Faculty::query()
            ->where('public_slug', $facultySlug)
            ->orWhere('slug', $facultySlug)
            ->first();

        if (! $faculty instanceof Faculty) {
            throw new InvalidArgumentException('Unknown faculty: '.$facultySlug);
        }

        $from = Carbon::parse($since)->startOfDay();
        $facultyId = (int) $faculty->getKey();
        $lines = ['Faculty '.$facultySlug.' (#'.$facultyId.'), activity since '.$from->toDateTimeString().' (server time '.now()->toDateTimeString().')'];

        $members = FacultyMember::withTrashed()->with('translations:id,faculty_member_id,locale,full_name')->where('faculty_id', $facultyId)->get();
        $live = $members->whereNull('deleted_at');
        $public = FacultyMember::query()->public()->where('faculty_id', $facultyId)->count();
        $lines[] = sprintf(
            'faculty_members: %d rows, %d deleted, %d not deleted (%d enabled, %d published, %d public by scope, %d linked to a person)',
            $members->count(),
            $members->whereNotNull('deleted_at')->count(),
            $live->count(),
            $live->where('is_enabled', true)->count(),
            $live->filter(fn (FacultyMember $member): bool => $this->status($member->publication_status) === PublicationStatus::Published->value)->count(),
            $public,
            $live->whereNotNull('person_id')->count(),
        );
        $lines[] = 'not deleted by status: '.$live->groupBy(fn (FacultyMember $member): string => $this->status($member->publication_status).($member->is_enabled ? '' : '/disabled'))->map->count()->toJson();

        $appointments = PersonAppointment::query()->where('type', 'faculty_member')->where('faculty_id', $facultyId)->get();
        $publicPeople = Person::query()->public()->whereIn('id', $appointments->where('is_enabled', true)->pluck('person_id'))->count();
        $lines[] = sprintf('person appointments here: %d (%d enabled; %d of those people public)', $appointments->count(), $appointments->where('is_enabled', true)->count(), $publicPeople);

        $lines[] = '';
        $lines[] = 'Deleted members (deleted_at since '.$from->toDateString().'):';
        foreach ($members->filter(fn (FacultyMember $member): bool => $member->deleted_at !== null && $member->deleted_at->gte($from))->sortBy('deleted_at') as $member) {
            $appointment = $member->person_id !== null ? $appointments->firstWhere('person_id', $member->person_id) : null;
            $lines[] = sprintf(
                '  #%d %s | deleted %s | status %s | person %s%s',
                (int) $member->getKey(),
                $this->name($member),
                $member->deleted_at->toDateTimeString(),
                $this->status($member->publication_status),
                $member->person_id ?? '-',
                $appointment instanceof PersonAppointment ? ' (appointment '.($appointment->is_enabled ? 'enabled' : 'disabled').')' : '',
            );
        }

        $lines[] = '';
        $lines[] = 'Members not deleted (why each is or is not public):';
        foreach ($live->sortBy('sort_order') as $member) {
            $lines[] = sprintf(
                '  #%d %s | enabled %s | status %s | published_at %s | person %s',
                (int) $member->getKey(),
                $this->name($member),
                $member->is_enabled ? 'yes' : 'no',
                $this->status($member->publication_status),
                $member->published_at?->toDateTimeString() ?? '-',
                $member->person_id ?? '-',
            );
        }

        $logs = AuditLog::query()->where('created_at', '>=', $from)->orderBy('id')->get();
        $users = User::withTrashed()->whereIn('id', $logs->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $memberIds = $members->map(fn (FacultyMember $member): int => (int) $member->getKey())->all();

        $lines[] = '';
        $lines[] = 'Audit log since '.$from->toDateString().', all actions by user:';
        foreach ($logs->groupBy(fn (AuditLog $log): string => ($users[$log->user_id] ?? 'system').' (#'.($log->user_id ?? '-').')') as $user => $entries) {
            $lines[] = '  '.$user.': '.$entries->groupBy('action')->map->count()->toJson();
        }

        $lines[] = '';
        $lines[] = 'Audit entries for this faculty\'s members and for people:';
        foreach ($logs as $log) {
            $isMember = $log->entity_type === FacultyMember::class && in_array((int) $log->entity_id, $memberIds, true);
            $isPerson = $log->entity_type === Person::class || str_starts_with((string) $log->action, 'people.');
            $target = is_array($log->metadata) ? (string) ($log->metadata['target_key'] ?? '') : '';
            $isMemberTarget = str_starts_with($target, 'entity.faculty-member.') && in_array((int) substr($target, strlen('entity.faculty-member.')), $memberIds, true);

            if (! $isMember && ! $isPerson && ! $isMemberTarget) {
                continue;
            }

            $member = $isMember ? $members->firstWhere('id', (int) $log->entity_id) : null;
            $lines[] = sprintf(
                '  %s | %s | %s | %s #%s%s',
                $log->created_at?->toDateTimeString() ?? '-',
                $users[$log->user_id] ?? 'system',
                $log->action,
                class_basename((string) $log->entity_type),
                $log->entity_id ?? '-',
                $member instanceof FacultyMember ? ' '.$this->name($member) : ($target !== '' ? ' '.$target : ''),
            );
        }

        return $lines;
    }

    private function name(FacultyMember $member): string
    {
        $ar = $member->translations->firstWhere('locale', 'ar')?->full_name;
        $en = $member->translations->firstWhere('locale', 'en')?->full_name;

        return trim((string) ($en ?: $ar ?: '(no name)')).($ar && $en ? ' / '.$ar : '');
    }

    private function status(mixed $status): string
    {
        return $status instanceof PublicationStatus ? $status->value : (string) $status;
    }
}
