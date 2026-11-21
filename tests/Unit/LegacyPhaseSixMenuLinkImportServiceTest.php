<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyPhaseSixMenuLinkImportServiceInterface;
use App\Models\Legacy\LegacyContentMapping;
use App\Models\Legacy\LegacyReviewItem;
use App\Models\Navigation\MenuItem;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyPhaseSixMenuLinkImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('old_database.connection_name', (string) config('database.default'));
        config()->set('old_database.connection', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('old_database.cleaning_inspection_fields.links', [[
            'table' => 'jx_sites',
            'id_column' => 'id',
            'fields' => [
                ['column' => 'url', 'type' => 'url', 'required' => true],
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ],
        ]]);

        app(OldDatabaseConnection::class)->connection();

        Schema::connection('legacy_mysql')->create('jx_sites', function ($table): void {
            $table->increments('id');
            $table->string('url');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
        });
        app('db')->connection('legacy_mysql')->table('jx_sites')->insert([
            'id' => 1,
            'url' => 'https://example.com/path',
            'ar_name' => '  رابط عربي  ',
            'en_name' => '  English Link  ',
        ]);
        $this->createApprovedReviewItem();
    }

    public function test_dry_run_does_not_create_menu_items(): void
    {
        $result = app(LegacyPhaseSixMenuLinkImportServiceInterface::class)->import(batch: 'test-batch');

        $this->assertFalse($result->written);
        $this->assertSame(1, $result->importableRows);
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_write_requires_approval_token(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LegacyPhaseSixMenuLinkImportServiceInterface::class)->import(write: true, approval: 'wrong');
    }

    public function test_write_imports_disabled_ar_and_en_footer_items_and_is_idempotent(): void
    {
        $service = app(LegacyPhaseSixMenuLinkImportServiceInterface::class);

        $first = $service->import(write: true, approval: 'phase6-menu-links', batch: 'test-batch');
        $second = $service->import(write: true, approval: 'phase6-menu-links', batch: 'test-batch-2');

        $this->assertSame(1, $first->importedRows);
        $this->assertSame(2, $first->createdMenuItems);
        $this->assertSame(2, MenuItem::query()->count());
        $this->assertSame(2, MenuItem::query()->where('is_enabled', false)->count());
        $this->assertSame(2, MigrationLog::query()->where('status', 'success')->count());
        $this->assertSame(0, $second->importedRows);
        $this->assertSame(1, $second->skipReasonCounts['already_imported']);
        $this->assertSame('menu_items', LegacyContentMapping::query()->value('target_table'));
        $this->assertNotNull(LegacyContentMapping::query()->value('target_id'));
    }

    private function createApprovedReviewItem(): void
    {
        LegacyContentMapping::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'site:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'approved',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
        ]);
        LegacyReviewItem::query()->create([
            'module' => 'links',
            'source_table' => 'jx_sites',
            'source_id' => 1,
            'legacy_key' => 'site:1',
            'classification' => 'redirect_to_equivalent',
            'mapping_status' => 'approved',
            'review_status' => 'mapping_already_approved',
            'target_module' => 'continuity',
            'target_type' => 'redirect_candidate',
            'confidence' => 'medium',
            'file_dependency' => 'none',
            'phase3_reasons' => [],
            'cleaning_status' => 'clean',
            'url_status' => 'needs_redirect_review',
            'blocked_reasons' => [],
        ]);
    }
}
