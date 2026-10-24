<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyClassificationReportServiceInterface;
use App\Contracts\Legacy\LegacyLocationImportServiceInterface;
use App\Contracts\Legacy\LegacyMappingProposalServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixApprovalServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixMenuLinkImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixPageImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixRestoreServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixSettingsImportServiceInterface;
use App\Contracts\Legacy\LegacyPhaseSixSettingsMappingServiceInterface;
use App\Contracts\Legacy\LegacyStagingReviewServiceInterface;
use App\Contracts\Legacy\LegacyStudentProfileImportServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Legacy\LegacyPhaseSixRestoreResultDTO;
use App\Models\Faculty\Faculty;
use InvalidArgumentException;

final class LegacyPhaseSixRestoreService implements LegacyPhaseSixRestoreServiceInterface
{
    private const APPROVAL_TOKEN = 'phase6-restore';

    public function __construct(
        private readonly LegacyClassificationReportServiceInterface $classificationReportService,
        private readonly LegacyMappingProposalServiceInterface $mappingProposalService,
        private readonly LegacyStagingReviewServiceInterface $stagingReviewService,
        private readonly LegacyPhaseSixApprovalServiceInterface $approvalService,
        private readonly LegacyPhaseSixSettingsMappingServiceInterface $settingsMappingService,
        private readonly LegacyLocationImportServiceInterface $locationImportService,
        private readonly LegacyStudentProfileImportServiceInterface $studentProfileImportService,
        private readonly LegacyPhaseSixPageImportServiceInterface $pageImportService,
        private readonly LegacyPhaseSixMenuLinkImportServiceInterface $menuLinkImportService,
        private readonly LegacyPhaseSixSettingsImportServiceInterface $settingsImportService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function restore(bool $write = false, ?string $approval = null, ?string $batch = null): LegacyPhaseSixRestoreResultDTO
    {
        if ($write && $approval !== self::APPROVAL_TOKEN) {
            throw new InvalidArgumentException('Restoring Phase 6 requires --approve='.self::APPROVAL_TOKEN.'.');
        }

        if ($write && Faculty::query()->count() === 0) {
            throw new InvalidArgumentException('Phase 6 restore requires foundation faculties. Run migrate:fresh --seed before restoring.');
        }

        $batch = $batch !== null && trim($batch) !== '' ? trim($batch) : 'phase6-restore-'.now()->format('Ymd_His');
        $lanes = [];
        $warnings = [];
        $settingsInput = null;

        if ($write) {
            [$preparation, $settingsInput] = $this->prepareReviewedImports();
            $lanes = [...$lanes, ...$preparation];
        } else {
            $warnings[] = 'Dry-run does not persist classification, staging, or approvals; Pages, Menu, and Settings reflect only existing review state.';
        }

        $locations = $this->locationImportService->import($write, $write ? 'phase6-locations' : null, $batch.'-locations', false);
        $lanes['locations'] = [
            'scanned' => $locations->scannedCountries + $locations->scannedCities,
            'importable' => $locations->importableCountries + $locations->importableCities,
            'imported' => $locations->importedCountries + $locations->importedCities,
            'skipped' => $locations->skippedRows,
            'enabled' => false,
        ];

        $alumni = $this->studentProfileImportService->import('alumni', $write, $write ? 'phase6-alumni' : null, $batch.'-alumni', true);
        $lanes['alumni'] = $this->profileSummary($alumni->scannedRows, $alumni->importableRows, $alumni->importedRows, $alumni->skippedRows, true);

        $honor = $this->studentProfileImportService->import('honor_students', $write, $write ? 'phase6-honor-students' : null, $batch.'-honor-students', true);
        $lanes['honor_students'] = $this->profileSummary($honor->scannedRows, $honor->importableRows, $honor->importedRows, $honor->skippedRows, true);

        $lanes['faculty_members'] = ['status' => 'blocked_by_audit_reconciliation', ...$this->profileSummary(0, 0, 0, 0, false)];
        $warnings[] = 'Faculty members are blocked by audit reconciliation: jx_councils1 is not proven public; use the separate private jx_councils public staff packet workflow.';

        $lanes['research_publications'] = ['status' => 'blocked_by_audit_reconciliation', ...$this->profileSummary(0, 0, 0, 0, false)];
        $warnings[] = 'Research publications are blocked because audited jx_member_* records belong to /members/ and require reconciliation.';

        $lanes['news'] = ['status' => 'requires_approved_packet', ...$this->profileSummary(0, 0, 0, 0, false)];
        $warnings[] = 'News is excluded from Phase 6 restore and requires a privately reviewed category approval packet.';

        $pages = $this->pageImportService->import($write, $write ? 'phase6-pages' : null, $batch.'-pages');
        $lanes['static_pages'] = $this->profileSummary($pages->scannedRows, $pages->importableRows, $pages->importedRows, $pages->skippedRows, false);

        $menu = $this->menuLinkImportService->import($write, $write ? 'phase6-menu-links' : null, $batch.'-menu-links');
        $lanes['menu_links'] = $this->profileSummary($menu->scannedRows, $menu->importableRows, $menu->importedRows, $menu->skippedRows, false);

        if ($write && $settingsInput === null) {
            throw new InvalidArgumentException('Phase 6 settings mapping preparation did not produce a safe mapping file.');
        }

        if ($write || $settingsInput !== null) {
            $settings = $this->settingsImportService->import($settingsInput, $write, $write ? 'phase6-settings' : null, 'local', $batch.'-settings');
            $lanes['settings'] = $this->profileSummary($settings->scannedRows, $settings->importableRows, $settings->importedRows, $settings->skippedRows, true);
        } else {
            $lanes['settings'] = ['status' => 'requires_write_preparation', 'scanned' => 0, 'importable' => 0, 'imported' => 0, 'skipped' => 0];
        }

        if ($write && ! $this->cacheService->flushTags(['public-pages', 'public-shell', 'facilities', 'news', 'research', 'settings', 'seo', 'sitemap'])) {
            $this->cacheService->flushAll();
        }

        return new LegacyPhaseSixRestoreResultDTO($write, $batch, $lanes, $warnings);
    }

    /** @return array{0: array<string, array<string, int|string|bool>>, 1: string} */
    private function prepareReviewedImports(): array
    {
        $lanes = [];

        foreach (['static_pages', 'links', 'settings'] as $module) {
            $classification = $this->classificationReportService->export(
                module: $module,
                disk: 'local',
                directory: 'legacy-import-exports/phase6-restore/classification',
            );
            $mappingPath = $classification->paths[2] ?? null;

            if (! is_string($mappingPath) || $mappingPath === '') {
                throw new InvalidArgumentException('Classification did not produce a mapping file for '.$module.'.');
            }

            $mapping = $this->mappingProposalService->importFromClassificationCsv($mappingPath, true, 'local');
            $staging = $this->stagingReviewService->build(
                module: $module,
                write: true,
                disk: 'local',
                directory: 'legacy-import-exports/phase6-restore/staging',
            );
            $lanes['prepare_'.$module] = [
                'classified' => $classification->classifiedRowCount,
                'mappings_written' => $mapping->createdRows + $mapping->updatedRows,
                'staged' => $staging->stagedRows,
            ];
        }

        $pagesApproval = $this->approvalService->approvePages(true, 'phase6-pages');
        $menuApproval = $this->approvalService->approveMenuLinks(true, 'phase6-menu-links');
        $lanes['approve_static_pages'] = ['approved' => $pagesApproval->approvedRows, 'blocked' => $pagesApproval->blockedRows];
        $lanes['approve_menu_links'] = ['approved' => $menuApproval->approvedRows, 'blocked' => $menuApproval->blockedRows];

        $settingsMapping = $this->settingsMappingService->export('local', 'legacy-import-exports/phase6-restore/settings');
        $settingsInput = $settingsMapping->paths[1] ?? null;

        if (! is_string($settingsInput) || $settingsInput === '') {
            throw new InvalidArgumentException('Phase 6 settings mapping did not produce a safe mapping CSV.');
        }

        $lanes['prepare_settings_mapping'] = [
            'scanned' => $settingsMapping->scannedRows,
            'safe' => $settingsMapping->safeMappingRows,
            'blocked' => $settingsMapping->backlogRows + $settingsMapping->duplicateConflictRows + $settingsMapping->unsafeValueRows,
        ];

        return [$lanes, $settingsInput];
    }

    /** @return array<string, int|bool> */
    private function profileSummary(int $scanned, int $importable, int $imported, int $skipped, bool $enabled): array
    {
        return compact('scanned', 'importable', 'imported', 'skipped', 'enabled');
    }
}
