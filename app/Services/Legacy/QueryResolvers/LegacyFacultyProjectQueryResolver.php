<?php

declare(strict_types=1);

namespace App\Services\Legacy\QueryResolvers;

use App\Contracts\Legacy\LegacyQueryModuleResolverInterface;
use App\DTOs\Legacy\LegacyQueryResolutionDTO;
use App\DTOs\Legacy\NormalizedLegacyUrlDTO;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;

final class LegacyFacultyProjectQueryResolver implements LegacyQueryModuleResolverInterface
{
    /** @var array<int, string> */
    private const SERVICE_SUBSITES = [
        24 => 'med',
        34 => 'dent',
        44 => 'pharm',
        54 => 'info',
        64 => 'petrol',
        74 => 'admin',
    ];

    public function canResolve(NormalizedLegacyUrlDTO $url): bool
    {
        $service = (int) ($url->service ?? 0);

        return $url->requestType === 'legacy_router'
            && $url->dir === 'items'
            && $url->page === 'show'
            && isset(self::SERVICE_SUBSITES[$service])
            && in_array($url->subsite->key, ['root', self::SERVICE_SUBSITES[$service]], true)
            && $this->sourceId($url) !== null;
    }

    public function resolve(NormalizedLegacyUrlDTO $url): ?LegacyQueryResolutionDTO
    {
        if (! $this->canResolve($url)) {
            return null;
        }

        $sourceId = $this->sourceId($url);
        $service = (int) $url->service;
        if ($sourceId === null) {
            return null;
        }

        $project = FacultyStudentProject::query()
            ->enabled()
            ->where('legacy_source_id', $sourceId)
            ->where('legacy_service_type', $service)
            ->with('faculty:id,slug,public_slug')
            ->first();

        if (! $project instanceof FacultyStudentProject || ! $project->faculty instanceof Faculty) {
            return null;
        }

        $facultySlug = (string) ($project->faculty->public_slug ?: $project->faculty->slug);

        return new LegacyQueryResolutionDTO(
            module: 'faculty_projects',
            sourceTable: 'jx_categories',
            sourceId: $sourceId,
            targetUrl: '/'.$url->language->locale.'/faculties/'.$facultySlug.'/projects/'.$project->slug,
            statusCode: 301,
            confidence: 'high',
            notes: 'Resolved legacy faculty project by exact jx_categories source ID and faculty service.',
        );
    }

    private function sourceId(NormalizedLegacyUrlDTO $url): ?int
    {
        $value = $url->params['cat_id'] ?? $url->params['id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
