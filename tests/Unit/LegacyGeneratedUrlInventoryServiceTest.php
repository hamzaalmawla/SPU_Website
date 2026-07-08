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
        $this->assertSame(0, $result->resolvedRows);
        $this->assertSame(3, $result->unresolvedRows);

        foreach ($result->paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        $csv = Storage::disk('local')->get($result->paths[1]);
        $this->assertStringContainsString('/index.php?page=show&ex=2&dir=items&lang=1&ser=3&cat_id=10', $csv);
        $this->assertStringContainsString('/index.php?page=show&ex=2&dir=items&lang=2&ser=3&cat_id=10', $csv);
        $this->assertStringContainsString('/index.php?lang=1', $csv);
        $this->assertStringContainsString('cat_id=10&dir=items&ex=2&lang=1&page=show&ser=3', $csv);
        $this->assertStringContainsString('do not redirect to homepage', $csv);
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
}
