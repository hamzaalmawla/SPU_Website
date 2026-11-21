<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Contracts\Auth\AdminAccessServiceInterface;
use App\Contracts\Cms\CmsTargetRegistryInterface;
use App\Contracts\Dashboard\AdminDashboardServiceInterface;
use App\DTOs\Cms\CmsTargetDTO;
use App\DTOs\Dashboard\DashboardRowDTO;
use App\Enums\PublicationStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AdminDashboardService implements AdminDashboardServiceInterface
{
    /** @var array<string, string>|null */
    private ?array $facultyNames = null;

    public function __construct(
        private readonly AdminAccessServiceInterface $access,
        private readonly CmsTargetRegistryInterface $targets,
    ) {}

    public function unpublishedDrafts(Authenticatable $user, int $days = 60, int $named = 5): Collection
    {
        $published = DB::table('cms_target_contents')
            ->where('status', PublicationStatus::Published->value)
            ->pluck('updated_at', 'target_key');

        $rows = DB::table('cms_drafts')
            ->leftJoin('users', 'users.id', '=', DB::raw('COALESCE(cms_drafts.updated_by, cms_drafts.created_by)'))
            ->where('cms_drafts.status', PublicationStatus::Draft->value)
            ->where('cms_drafts.updated_at', '>=', now()->subDays(max(1, $days)))
            ->orderByDesc('cms_drafts.updated_at')
            ->get(['cms_drafts.id', 'cms_drafts.target_key', 'cms_drafts.updated_at', 'users.name as editor'])
            ->unique('target_key')
            // A draft older than the published version is what publishing left behind, not pending work.
            ->filter(static function (object $row) use ($published): bool {
                $publishedAt = $published->get($row->target_key);

                return $publishedAt === null || Carbon::parse((string) $row->updated_at)->greaterThan(Carbon::parse((string) $publishedAt));
            })
            ->filter(function (object $row) use ($user): bool {
                $target = $this->targets->find((string) $row->target_key);

                return $target instanceof CmsTargetDTO && $this->access->canManageTarget($user, $target);
            })
            ->values();

        // A record's draft (a profile, an article) is named by its title; only the rows shown need it.
        $titles = $this->draftTitles($rows->take(max(0, $named))->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

        return $rows->map(fn (object $row): ?DashboardRowDTO => $this->targetRow(
            $user,
            (string) $row->target_key,
            $this->who((string) ($row->editor ?? ''), (string) $row->updated_at),
            $titles[(int) $row->id] ?? null,
        ))->filter()->values();
    }

    public function scheduledPublishing(Authenticatable $user, int $limit = 10): Collection
    {
        return DB::table('cms_drafts')
            ->where('status', PublicationStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at')
            ->limit(100)
            ->get(['target_key', 'scheduled_at'])
            ->map(fn (object $row): ?DashboardRowDTO => $this->targetRow(
                $user,
                (string) $row->target_key,
                $this->adminTime((string) $row->scheduled_at)->translatedFormat('j M Y, H:i'),
            ))
            ->filter()
            ->take(max(1, $limit))
            ->values();
    }

    public function missingPages(Authenticatable $user, int $days = 7, int $limit = 5): Collection
    {
        if (! $this->access->can($user, 'settings.update')) {
            return collect();
        }

        return $this->missingPagesQuery($days)
            ->orderByDesc('hit_count')
            ->limit(max(1, $limit))
            ->get(['url', 'hit_count', 'last_seen_at'])
            ->map(fn (object $row): DashboardRowDTO => new DashboardRowDTO(
                label: (string) $row->url,
                value: (int) $row->hit_count,
                hint: $this->adminTime((string) $row->last_seen_at)->diffForHumans(),
            ))
            ->values();
    }

    public function missingPagesCount(Authenticatable $user, int $days = 7): int
    {
        return $this->access->can($user, 'settings.update') ? $this->missingPagesQuery($days)->count() : 0;
    }

    public function failedJobsCount(Authenticatable $user): int
    {
        return $this->access->isSuperAdmin($user) && Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }

    public function lockedAccountsCount(Authenticatable $user): int
    {
        if (! $this->access->isSuperAdmin($user)) {
            return 0;
        }

        // A timed lock ends by itself once locked_until passes.
        return DB::table('users')
            ->where(static fn ($query) => $query->where('is_locked', true)->orWhereNotNull('locked_at'))
            ->where(static fn ($query) => $query->whereNull('locked_until')->orWhere('locked_until', '>', now()))
            ->count();
    }

    public function recentPublishing(Authenticatable $user, int $limit = 8): Collection
    {
        if (! $this->access->can($user, 'audit_log.view')) {
            return collect();
        }

        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
            ->whereIn('audit_logs.action', ['cms.published', 'cms.unpublished'])
            ->orderByDesc('audit_logs.id')
            ->limit(max(1, $limit))
            ->get(['audit_logs.action', 'audit_logs.metadata', 'audit_logs.created_at', 'users.name as actor'])
            ->map(function (object $row): ?DashboardRowDTO {
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : (array) $row->metadata;
                $key = is_array($metadata) && is_string($metadata['target_key'] ?? null) ? $metadata['target_key'] : '';
                $target = $key !== '' ? $this->targets->find($key) : null;

                if (! $target instanceof CmsTargetDTO) {
                    return null;
                }

                return new DashboardRowDTO(
                    label: $this->targetLabel($target),
                    value: $row->action === 'cms.unpublished' ? 0 : 1,
                    hint: $this->who((string) ($row->actor ?? ''), (string) $row->created_at),
                    url: $this->publicUrl($target),
                );
            })
            ->filter()
            ->values();
    }

    /** A page the user may edit, as a dashboard row; null for a page they may not manage. */
    private function targetRow(Authenticatable $user, string $targetKey, string $hint, ?string $title = null): ?DashboardRowDTO
    {
        $target = $this->targets->find($targetKey);

        if (! $target instanceof CmsTargetDTO || ! $this->access->canManageTarget($user, $target)) {
            return null;
        }

        $label = $this->targetLabel($target);

        return new DashboardRowDTO(label: $title !== null ? $label.': '.$title : $label, hint: $hint, url: $this->publicUrl($target));
    }

    /**
     * The title saved in each record draft (`entity.*` targets), in the admin language when it has one.
     *
     * @param  list<int>  $draftIds
     * @return array<int, string>
     */
    private function draftTitles(array $draftIds): array
    {
        if ($draftIds === []) {
            return [];
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $titles = [];

        foreach (DB::table('cms_drafts')->whereIn('id', $draftIds)->where('target_key', 'like', 'entity.%')->get(['id', 'payload_json']) as $draft) {
            $payload = is_string($draft->payload_json) ? json_decode($draft->payload_json, true) : null;

            if (! is_array($payload)) {
                continue;
            }

            foreach ([$payload['translations'][$locale] ?? null, $payload['translations'][$locale === 'en' ? 'ar' : 'en'] ?? null, $payload] as $content) {
                foreach (['title', 'full_name', 'name'] as $field) {
                    $title = is_array($content) && is_string($content[$field] ?? null) ? trim($content[$field]) : '';

                    if ($title !== '') {
                        $titles[(int) $draft->id] = mb_strimwidth($title, 0, 90, '…');

                        continue 3;
                    }
                }
            }
        }

        return $titles;
    }

    /** The page's name, with its faculty for a faculty page ("الخطة الدراسية — كلية الصيدلة"). */
    private function targetLabel(CmsTargetDTO $target): string
    {
        $label = __($target->labelKey);
        $faculty = $target->facultyScopeSlug !== null ? ($this->facultyNames()[$target->facultyScopeSlug] ?? null) : null;

        return $faculty !== null && $faculty !== $label ? $label.' — '.$faculty : $label;
    }

    /** @return array<string, string> */
    private function facultyNames(): array
    {
        if ($this->facultyNames !== null) {
            return $this->facultyNames;
        }

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $names = [];

        foreach (DB::table('faculties')->join('faculty_translations', 'faculty_translations.faculty_id', '=', 'faculties.id')->get(['faculties.public_slug', 'faculty_translations.locale', 'faculty_translations.name']) as $row) {
            $slug = (string) $row->public_slug;

            if ($row->locale === $locale || ! isset($names[$slug])) {
                $names[$slug] = trim((string) $row->name);
            }
        }

        return $this->facultyNames = $names;
    }

    private function publicUrl(CmsTargetDTO $target): ?string
    {
        return $target->publicPath !== null ? '/'.(app()->getLocale() === 'en' ? 'en' : 'ar').'/'.ltrim($target->publicPath, '/') : null;
    }

    private function who(string $name, string $at): string
    {
        $when = $this->adminTime($at)->diffForHumans();

        return $name !== '' ? $name.' · '.$when : $when;
    }

    private function adminTime(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone((string) config('app.admin_timezone', 'Asia/Damascus'))->locale(app()->getLocale());
    }

    private function missingPagesQuery(int $days): Builder
    {
        return DB::table('unresolved_legacy_requests')
            ->where('request_type', 'page')
            ->where('last_seen_at', '>=', now()->subDays(max(1, $days)));
    }
}
