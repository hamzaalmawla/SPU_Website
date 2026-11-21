<?php

declare(strict_types=1);

namespace App\Contracts\Auth;

use App\DTOs\Cms\CmsTargetDTO;

/**
 * Every admin-panel permission, defined in code.
 *
 * A permission is `<component>.<action>`, for example `news_articles.update`.
 * Roles and users store these keys; the catalog decides which exist.
 */
interface PermissionCatalogInterface
{
    /**
     * Components by group, each with the actions it supports.
     *
     * @return array<string, array<string, list<string>>> group => component => actions
     */
    public function groups(): array;

    /** @return list<string> */
    public function all(): array;

    public function exists(string $permission): bool;

    /**
     * Permission picker options: group label => [permission key => label].
     *
     * @return array<string, array<string, string>>
     */
    public function options(): array;

    public function label(string $permission): string;

    /**
     * The component that owns a CMS page (for example `about` for
     * `about.history`, `faculty_medicine` for `facilities.medicine.labs`).
     */
    public function componentForTarget(CmsTargetDTO $target): ?string;

    public function componentForFaculty(string $facultySlug): string;

    /**
     * The seven faculty screen components (`faculty_medicine`, ...).
     *
     * @return list<string>
     */
    public function facultyComponents(): array;
}
