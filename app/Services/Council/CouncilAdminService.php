<?php

declare(strict_types=1);

namespace App\Services\Council;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Council\CouncilAdminServiceInterface;
use App\Contracts\Council\CouncilCatalogServiceInterface;
use Illuminate\Support\Facades\DB;

final class CouncilAdminService implements CouncilAdminServiceInterface
{
    public function __construct(
        private readonly CouncilCatalogServiceInterface $catalog,
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
    ) {}

    public function createCouncil(string $slug, int $sortOrder, bool $isEnabled, string $titleAr, string $titleEn, int $userId): int
    {
        return DB::transaction(function () use ($slug, $sortOrder, $isEnabled, $titleAr, $titleEn, $userId): int {
            $id = $this->catalog->createCouncil($slug, $sortOrder, $isEnabled);
            $this->cmsWorkflowService->saveDraft(
                $this->catalog->targetKey($id),
                $this->catalog->newCouncilPayload($titleAr, $titleEn),
                $userId,
            );

            return $id;
        });
    }
}
