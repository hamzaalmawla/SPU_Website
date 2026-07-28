<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyGeneratedUrlInventoryServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyGeneratedUrlInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        config()->set('old_database.connection_name', 'legacy_generated_url_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_generated_url_testing', $connection);
        DB::purge('legacy_generated_url_testing');
    }

    public function test_generates_router_and_explicit_urls_without_creating_redirects(): void
    {
        Storage::fake('local');
        $this->createLegacyTables();

        $result = app(LegacyGeneratedUrlInventoryServiceInterface::class)->export(table: 'jx_categories');

        $this->assertSame(1, $result->sourceRows);
        $this->assertSame(3, $result->generatedRows);
        $this->assertSame(1, $result->resolvedRows);
        $this->assertSame(2, $result->unresolvedRows);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $csv = Storage::disk('local')->get($result->paths[1]);
        $this->assertStringContainsString('/index.php?page=show&ex=2&dir=items&lang=1&ser=3&cat_id=10', $csv);
        $this->assertStringContainsString('/index.php?page=show&ex=2&dir=items&lang=2&ser=3&cat_id=10', $csv);
        $this->assertStringContainsString('/index.php?lang=1', $csv);
        $this->assertStringContainsString('cat_id=10&dir=items&ex=2&lang=1&page=show&service=3', $csv);
        $this->assertStringContainsString('do not redirect to homepage', $csv);
    }

    public function test_generates_category_urls_with_audited_subsite_prefixes_only(): void
    {
        Storage::fake('local');
        $this->createLegacyTables();
        DB::connection('legacy_generated_url_testing')->table('jx_categories')->insert([
            [
                'id' => 11,
                'ar_name' => 'Business category',
                'en_name' => 'Business category',
                'service_type' => 73,
                'url' => null,
            ],
            [
                'id' => 12,
                'ar_name' => 'Unknown extension',
                'en_name' => 'Unknown extension',
                'service_type' => 131,
                'url' => null,
            ],
        ]);

        $result = app(LegacyGeneratedUrlInventoryServiceInterface::class)->export(table: 'jx_categories');
        $csv = Storage::disk('local')->get($result->paths[1]);

        $this->assertStringContainsString('/admin/index.php?page=show&ex=2&dir=items&lang=1&ser=73&cat_id=11', $csv);
        $this->assertStringNotContainsString('ser=131', $csv);
    }

    public function test_generates_public_council_urls_with_audited_context_and_excludes_councils1(): void
    {
        Storage::fake('local');
        $this->createCouncilTables();

        $public = app(LegacyGeneratedUrlInventoryServiceInterface::class)->export(table: 'jx_councils');
        $archive = app(LegacyGeneratedUrlInventoryServiceInterface::class)->export(table: 'jx_councils1');
        $publicCsv = Storage::disk('local')->get($public->paths[1]);
        $archiveCsv = Storage::disk('local')->get($archive->paths[1]);

        $this->assertStringContainsString('/admin/index.php?page=show&ex=2&dir=councils&lang=1&service=14&cat_id=20', $publicCsv);
        $this->assertStringNotContainsString('/members/index.php', $publicCsv);
        $this->assertStringNotContainsString('council_id=', $publicCsv);
        $this->assertStringNotContainsString('generated_router_url', $archiveCsv);
    }

    private function createLegacyTables(): void
    {
        Schema::connection('legacy_generated_url_testing')->create('jx_categories', function ($schema): void {
            $schema->increments('id');
            $schema->string('ar_name')->nullable();
            $schema->string('en_name')->nullable();
            $schema->integer('service_type')->nullable();
            $schema->string('url')->nullable();
        });

        DB::connection('legacy_generated_url_testing')->table('jx_categories')->insert([
            'id' => 10,
            'ar_name' => 'Arabic title',
            'en_name' => 'English title',
            'service_type' => 3,
            'url' => '/index.php?lang=1',
        ]);
    }

    private function createCouncilTables(): void
    {
        foreach (['jx_councils', 'jx_councils1'] as $table) {
            Schema::connection('legacy_generated_url_testing')->create($table, function ($schema): void {
                $schema->increments('id');
                $schema->string('ar_name')->nullable();
                $schema->string('en_name')->nullable();
                $schema->integer('service_type')->nullable();
                $schema->string('url')->nullable();
            });
        }

        DB::connection('legacy_generated_url_testing')->table('jx_councils')->insert([
            'id' => 20,
            'ar_name' => 'Business member',
            'en_name' => 'Business member',
            'service_type' => 14,
            'url' => null,
        ]);
        DB::connection('legacy_generated_url_testing')->table('jx_councils1')->insert([
            'id' => 30,
            'ar_name' => 'Unproven archive member',
            'en_name' => 'Unproven archive member',
            'service_type' => 14,
            'url' => null,
        ]);
    }
}
