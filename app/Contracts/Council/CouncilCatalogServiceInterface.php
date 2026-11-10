<?php

declare(strict_types=1);

namespace App\Contracts\Council;

use App\DTOs\Cms\CmsTargetDTO;

/**
 * The university's councils as records and CMS targets: a council is a row of
 * university_councils (address, order, shown or not) plus the CMS content of
 * target entity.council.{id} (title, introduction, members and functions in
 * both languages). Admin screens and the CMS workflow use this; public pages
 * use CouncilPageServiceInterface.
 */
interface CouncilCatalogServiceInterface
{
    public const TARGET_PREFIX = 'entity.council.';

    public function targetKey(int $councilId): string;

    /** The council id of an entity.council.{id} target, or null for any other key. */
    public function councilIdFromTarget(string $targetKey): ?int;

    /** The CMS target of an existing council (null for other keys and deleted councils). */
    public function resolveTarget(string $targetKey): ?CmsTargetDTO;

    /** Makes the council's row and returns its id; its content is then saved as a CMS draft. */
    public function createCouncil(string $slug, int $sortOrder, bool $isEnabled): int;

    /** Changes where the council shows in the list and whether it shows at all. */
    public function updateSettings(int $councilId, int $sortOrder, bool $isEnabled): bool;

    /** Removes the council from the site. Its saved content versions stay in the CMS history. */
    public function deleteCouncil(int $councilId): bool;

    public function slugOf(int $councilId): ?string;

    /**
     * The content a new council starts from: its name in both languages and
     * the default headings, with no members or functions yet.
     *
     * @return array<string, mixed>
     */
    public function newCouncilPayload(string $titleAr, string $titleEn): array;

    /**
     * The content the editor opens when there is no draft: the published
     * version, else an empty council with the default headings.
     *
     * @return array<string, mixed>
     */
    public function editorPayload(int $councilId): array;

    /**
     * The council's name for admin lists: from the newest draft, else the
     * published version, else its address.
     */
    public function adminTitle(int $councilId, string $locale): string;

    public function isPublished(int $councilId): bool;

    /**
     * What stops this content from being published, by field.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, list<string>>
     */
    public function validationErrors(array $payload): array;
}
