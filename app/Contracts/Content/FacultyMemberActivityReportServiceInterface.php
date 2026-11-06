<?php

declare(strict_types=1);

namespace App\Contracts\Content;

/**
 * A read-only account of one faculty's members: what is listed publicly, what
 * is deleted or hidden and why, and the admin activity (audit log) since a
 * date. For investigating a members page that lost people; it changes nothing.
 */
interface FacultyMemberActivityReportServiceInterface
{
    /** @return list<string> report lines */
    public function report(string $facultySlug, string $since): array;
}
