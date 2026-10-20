<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\DTOs\Cms\CmsTargetDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The one place that answers "what may this admin edit?".
 *
 * Super administrators and editors keep full content access. A limited editor
 * (role `faculty_editor`) edits only what a super administrator granted on the
 * user form: any number of faculties and any number of individual CMS pages.
 */
interface AdminAccessServiceInterface
{
    public function isLimitedEditor(Authenticatable $user): bool;

    /**
     * Whether the user may edit content owned by this faculty. Accepts any of
     * a faculty's slugs (public, internal or scope slug). Editors and super
     * administrators may edit every faculty.
     */
    public function canManageFaculty(Authenticatable $user, ?string $facultySlug): bool;

    /**
     * Whether the user may edit this CMS page. Faculty pages follow the
     * faculty grant; every other page needs its own page grant.
     */
    public function canManageTarget(Authenticatable $user, CmsTargetDTO $target): bool;

    /**
     * Whether a limited editor holds at least one page grant in this admin
     * area (for example `about` or `admissions`), so its admin screen opens.
     */
    public function canEnterArea(Authenticatable $user, string $area): bool;

    /**
     * Every slug spelling of the user's granted faculties, for filtering
     * records that may store a faculty's public, internal or scope slug.
     *
     * @return list<string>
     */
    public function facultyScopeValues(Authenticatable $user): array;

    /**
     * The granted faculties as public slugs.
     *
     * @return list<string>
     */
    public function grantedFaculties(Authenticatable $user): array;

    /**
     * The user's only granted faculty, used to stamp new content; null when the
     * user has none or several (then the editor must choose).
     */
    public function singleGrantedFaculty(Authenticatable $user): ?string;

    /**
     * The faculty a limited editor's record is saved under: the requested
     * faculty when it is granted, otherwise their only granted faculty. Null
     * when neither applies (the write must then be refused).
     */
    public function facultyForWrite(Authenticatable $user, ?string $requestedFaculty): ?string;

    public function canonicalFacultySlug(string $facultySlug): string;

    /**
     * Faculties a super administrator can grant, keyed by public slug.
     *
     * @return array<string, string>
     */
    public function facultyOptions(): array;
}
