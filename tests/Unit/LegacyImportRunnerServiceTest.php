<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyImportRunnerServiceInterface;
use App\Contracts\Legacy\LegacyCleaningInspectionServiceInterface;
use App\Contracts\Legacy\LegacyImportBatchServiceInterface;
use App\Contracts\Legacy\LegacyIntegrityInspectionServiceInterface;
use App\Contracts\Legacy\LegacyImportInspectionServiceInterface;
use App\Contracts\Legacy\LegacyImportModuleRegistryInterface;
use App\DTOs\Legacy\LegacyImportModuleRunnerDTO;
use App\Services\Legacy\LegacyImportRunnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Tests\TestCase;

final class LegacyImportRunnerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LegacyImportRunnerServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_testing_runner');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_testing_runner', $connection);
        DB::purge('legacy_testing_runner');

        $this->service = app(LegacyImportRunnerServiceInterface::class);
    }

    public function test_unknown_module_records_blocked_run_and_returns_invalid(): void
    {
        $result = $this->service->run('unknown-module', 'unknown run');

        $this->assertSame(ConsoleCommand::INVALID, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('unknown_module', $result->dryRun->status);
        $this->assertSame('Legacy import module is not configured.', $result->message);
        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'unknown-run',
            'module' => 'unknown-module',
            'mode' => 'run',
            'status' => 'blocked',
        ]);
    }

    public function test_disabled_module_records_blocked_run_without_importing(): void
    {
        $result = $this->service->run('homepage', 'disabled homepage run');

        $this->assertSame(ConsoleCommand::FAILURE, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('disabled', $result->dryRun->status);
        $this->assertSame('Legacy import module is disabled in config/old_database.php.', $result->message);
        $this->assertSame('disabled', $result->batch->summary['dry_run_status'] ?? null);
        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'disabled-homepage-run',
            'module' => 'homepage',
            'mode' => 'run',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_enabled_dry_run_records_ready_batch(): void
    {
        config()->set('old_database.modules.homepage.enabled', true);
        $this->createLegacyTable('jx_home_photos', 2);
        $this->createLegacyTable('jx_logos', 1);

        $result = $this->service->run('homepage', 'ready homepage dry run', true);

        $this->assertSame(ConsoleCommand::SUCCESS, $result->exitCode);
        $this->assertSame('dry_run', $result->mode);
        $this->assertSame('dry_run_ready', $result->status);
        $this->assertSame('ready_for_dry_run', $result->dryRun->status);
        $this->assertSame(3, $result->dryRun->estimatedSourceRows);
        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'ready-homepage-dry-run',
            'module' => 'homepage',
            'mode' => 'dry_run',
            'status' => 'dry_run_ready',
            'estimated_source_rows' => 3,
        ]);
    }

    public function test_ready_real_run_without_registered_module_runner_is_blocked(): void
    {
        config()->set('old_database.modules.homepage.enabled', true);
        $this->createLegacyTable('jx_home_photos', 1);
        $this->createLegacyTable('jx_logos', 1);

        $result = $this->service->run('homepage', 'homepage real run');

        $this->assertSame(ConsoleCommand::FAILURE, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('ready_for_dry_run', $result->dryRun->status);
        $this->assertSame('No controlled legacy import runner is registered for this module.', $result->message);
        $this->assertSame(2, $result->batch->summary['estimated_source_rows'] ?? null);
        $this->assertFalse($result->batch->summary['controlled_runner_registered'] ?? true);
        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'homepage-real-run',
            'module' => 'homepage',
            'mode' => 'run',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_ready_links_real_run_is_blocked_until_candidate_runner_is_approved(): void
    {
        config()->set('old_database.modules.links.enabled', true);
        $this->createLegacyTable('jx_docs', 1);
        $this->createLegacyTable('jx_sites', 1);

        $result = $this->service->run('links', 'links real run');

        $this->assertSame(ConsoleCommand::FAILURE, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('ready_for_dry_run', $result->dryRun->status);
        $this->assertSame('Controlled legacy import runner is registered for this module, but real execution is not approved yet.', $result->message);
        $this->assertTrue($result->batch->summary['controlled_runner_registered'] ?? false);
        $this->assertFalse($result->batch->summary['controlled_runner_approved'] ?? true);
        $this->assertDatabaseHas('legacy_import_batches', [
            'batch_name' => 'links-real-run',
            'module' => 'links',
            'mode' => 'run',
            'status' => 'blocked',
        ]);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_approved_runner_is_blocked_when_phase_three_cleaning_has_blocked_fields(): void
    {
        config()->set('old_database.modules.links.enabled', true);
        $this->createLegacyLinksTables('javascript:alert(1)');

        $service = $this->approvedRunnerService();
        $result = $service->run('links', 'approved links unsafe run');

        $this->assertSame(ConsoleCommand::FAILURE, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('Phase 3 cleaning report has blocked fields. Review or record quarantine before real execution.', $result->message);
        $this->assertTrue($result->batch->summary['controlled_runner_approved'] ?? false);
        $this->assertSame('quarantine_required', $result->batch->summary['cleaning_status'] ?? null);
        $this->assertSame(1, $result->batch->summary['cleaning_blocked_fields'] ?? null);
        $this->assertSame(1, $result->batch->summary['cleaning_issue_counts']['unsafe_url'] ?? null);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_approved_runner_with_clean_phase_three_report_reaches_guarded_execution_block(): void
    {
        config()->set('old_database.modules.links.enabled', true);
        $this->createLegacyLinksTables('https://spu.edu.sy');

        $service = $this->approvedRunnerService();
        $result = $service->run('links', 'approved links clean run');

        $this->assertSame(ConsoleCommand::FAILURE, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('Controlled legacy import runner is approved, but real execution is not implemented in this guarded runner yet.', $result->message);
        $this->assertTrue($result->batch->summary['controlled_runner_approved'] ?? false);
        $this->assertSame('cleaning_passed', $result->batch->summary['cleaning_status'] ?? null);
        $this->assertSame(0, $result->batch->summary['cleaning_blocked_fields'] ?? null);
        $this->assertSame('integrity_passed', $result->batch->summary['integrity_status'] ?? null);
        $this->assertSame(0, $result->batch->summary['integrity_blocked_rows'] ?? null);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    public function test_approved_runner_is_blocked_when_phase_three_integrity_has_blockers(): void
    {
        config()->set('old_database.modules.links.enabled', true);
        $this->createLegacyLinksTablesWithDuplicateUrl();

        $service = $this->approvedRunnerService();
        $result = $service->run('links', 'approved links duplicate run');

        $this->assertSame(ConsoleCommand::FAILURE, $result->exitCode);
        $this->assertSame('blocked', $result->status);
        $this->assertSame('Phase 3 integrity report has duplicate or orphan blockers. Review or record quarantine before real execution.', $result->message);
        $this->assertSame('cleaning_passed', $result->batch->summary['cleaning_status'] ?? null);
        $this->assertSame('integrity_blockers_found', $result->batch->summary['integrity_status'] ?? null);
        $this->assertSame(2, $result->batch->summary['integrity_blocked_rows'] ?? null);
        $this->assertSame(2, $result->batch->summary['integrity_issue_counts']['duplicate_legacy_content'] ?? null);
        $this->assertDatabaseCount('migration_logs', 0);
    }

    private function createLegacyTable(string $table, int $rows): void
    {
        Schema::connection('legacy_testing_runner')->create($table, function ($schema): void {
            $schema->increments('id');
            $schema->string('name')->nullable();
        });

        for ($i = 0; $i < $rows; $i++) {
            DB::connection('legacy_testing_runner')->table($table)->insert(['name' => 'row-'.$i]);
        }
    }

    private function createLegacyLinksTables(string $url): void
    {
        Schema::connection('legacy_testing_runner')->create('jx_docs', function ($schema): void {
            $schema->increments('id');
            $schema->string('file')->nullable();
            $schema->string('title')->nullable();
        });

        Schema::connection('legacy_testing_runner')->create('jx_sites', function ($schema): void {
            $schema->increments('id');
            $schema->string('url')->nullable();
            $schema->string('ar_name')->nullable();
            $schema->string('en_name')->nullable();
        });

        DB::connection('legacy_testing_runner')->table('jx_docs')->insert([
            'file' => 'downloads/files/guide.pdf',
            'title' => 'Guide',
        ]);

        DB::connection('legacy_testing_runner')->table('jx_sites')->insert([
            'url' => $url,
            'ar_name' => 'رابط',
            'en_name' => 'Link',
        ]);
    }

    private function createLegacyLinksTablesWithDuplicateUrl(): void
    {
        Schema::connection('legacy_testing_runner')->create('jx_docs', function ($schema): void {
            $schema->increments('id');
            $schema->string('file')->nullable();
            $schema->string('title')->nullable();
        });

        Schema::connection('legacy_testing_runner')->create('jx_sites', function ($schema): void {
            $schema->increments('id');
            $schema->string('url')->nullable();
            $schema->string('ar_name')->nullable();
            $schema->string('en_name')->nullable();
        });

        DB::connection('legacy_testing_runner')->table('jx_docs')->insert([
            'file' => 'downloads/files/guide.pdf',
            'title' => 'Guide',
        ]);

        DB::connection('legacy_testing_runner')->table('jx_sites')->insert([
            ['url' => 'https://spu.edu.sy/same', 'ar_name' => 'رابط 1', 'en_name' => 'Link 1'],
            ['url' => ' https://spu.edu.sy/same ', 'ar_name' => 'رابط 2', 'en_name' => 'Link 2'],
        ]);
    }

    private function approvedRunnerService(): LegacyImportRunnerServiceInterface
    {
        return new LegacyImportRunnerService(
            inspectionService: app(LegacyImportInspectionServiceInterface::class),
            batchService: app(LegacyImportBatchServiceInterface::class),
            moduleRegistry: new class implements LegacyImportModuleRegistryInterface
            {
                public function all(): Collection
                {
                    return collect([$this->definition()]);
                }

                public function find(string $module): ?LegacyImportModuleRunnerDTO
                {
                    return $module === 'links' ? $this->definition() : null;
                }

                public function canExecute(string $module): bool
                {
                    return $module === 'links';
                }

                public function blockedReason(string $module): string
                {
                    return 'Fake approved runner should not be blocked by approval.';
                }

                private function definition(): LegacyImportModuleRunnerDTO
                {
                    return new LegacyImportModuleRunnerDTO(
                        module: 'links',
                        label: 'Approved fake links runner',
                        approvedForRealRun: true,
                        approvalStatus: 'approved_for_test',
                        description: 'Test-only approved runner.',
                    );
                }
            },
            cleaningInspectionService: app(LegacyCleaningInspectionServiceInterface::class),
            integrityInspectionService: app(LegacyIntegrityInspectionServiceInterface::class),
        );
    }
}
