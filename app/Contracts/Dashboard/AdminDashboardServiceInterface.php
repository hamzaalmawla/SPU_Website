<?php

declare(strict_types=1);

namespace App\Contracts\Dashboard;

use App\DTOs\Dashboard\DashboardRowDTO;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * The admin dashboard's work lists: what waits on an editor (unpublished drafts,
 * scheduled publishing), what is broken (missing pages visitors hit, failed
 * background jobs, locked accounts) and what was published lately. Each list holds
 * only what the user may manage.
 */
interface AdminDashboardServiceInterface
{
    /**
     * Pages with changes saved in the last `$days` days that are not published, newest edit first:
     * label the page (the first `$named` record drafts add their title), hint who edited it and
     * when, url the public page.
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function unpublishedDrafts(Authenticatable $user, int $days = 60, int $named = 5): Collection;

    /**
     * Pages set to publish later, soonest first: label the page, hint when.
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function scheduledPublishing(Authenticatable $user, int $limit = 10): Collection;

    /**
     * Old or mistyped addresses visitors hit in the last `$days` days that lead nowhere (no redirect),
     * most hit first; empty unless the user manages settings.
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function missingPages(Authenticatable $user, int $days = 7, int $limit = 5): Collection;

    /** Missing addresses hit in the last `$days` days (0 unless the user manages settings). */
    public function missingPagesCount(Authenticatable $user, int $days = 7): int;

    /** Background jobs that failed and were not retried (super admins only, else 0). */
    public function failedJobsCount(Authenticatable $user): int;

    /** Admin accounts locked now (super admins only, else 0). */
    public function lockedAccountsCount(Authenticatable $user): int;

    /**
     * Latest publishing and unpublishing, newest first (audit log readers only): label the page,
     * hint who and when.
     *
     * @return Collection<int, DashboardRowDTO>
     */
    public function recentPublishing(Authenticatable $user, int $limit = 8): Collection;
}
