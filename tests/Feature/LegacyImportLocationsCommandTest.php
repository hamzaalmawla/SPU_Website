<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Location\City;
use App\Models\Location\Country;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegacyImportLocationsCommandTest extends TestCase
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
        config()->set('old_database.cleaning_inspection_fields.countries', [[
            'table' => 'jx_countries',
            'id_column' => 'id',
            'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ],
        ]]);
        config()->set('old_database.cleaning_inspection_fields.cities', [[
            'table' => 'jx_cities',
            'id_column' => 'id',
            'fields' => [
                ['column' => 'ar_name', 'type' => 'text', 'required' => false],
                ['column' => 'en_name', 'type' => 'text', 'required' => false],
            ],
        ]]);

        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_countries', function ($table): void {
            $table->increments('id');
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->string('fr_name')->nullable();
        });
        Schema::connection('legacy_mysql')->create('jx_cities', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('country_id')->nullable();
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->boolean('is_visible')->default(true);
        });
        app('db')->connection('legacy_mysql')->table('jx_countries')->insert([
            'id' => 1,
            'en_name' => 'Syria',
            'ar_name' => 'سوريا',
            'fr_name' => 'Syrie',
        ]);
        app('db')->connection('legacy_mysql')->table('jx_cities')->insert([
            'id' => 10,
            'country_id' => 1,
            'en_name' => 'Damascus',
            'ar_name' => 'دمشق',
            'is_visible' => 1,
        ]);
    }

    public function test_locations_import_dry_run_does_not_write(): void
    {
        $this->artisan('legacy-import:locations --batch=locations-test')
            ->expectsOutputToContain('Legacy Location Import')
            ->expectsOutputToContain('Written: no')
            ->expectsOutputToContain('Importable countries: 1')
            ->expectsOutputToContain('Importable cities: 1')
            ->assertSuccessful();

        $this->assertSame(0, Country::query()->count());
        $this->assertSame(0, City::query()->count());
    }

    public function test_locations_import_requires_approval_for_write(): void
    {
        $this->artisan('legacy-import:locations --write --batch=locations-test')
            ->assertFailed();
    }

    public function test_locations_import_writes_disabled_reference_rows(): void
    {
        $this->artisan('legacy-import:locations --write --approve=phase6-locations --batch=locations-test')
            ->expectsOutputToContain('Written: yes')
            ->expectsOutputToContain('Imported countries: 1')
            ->expectsOutputToContain('Imported cities: 1')
            ->assertSuccessful();

        $country = Country::query()->with('translations')->firstOrFail();
        $city = City::query()->with('translations')->firstOrFail();

        $this->assertFalse((bool) $country->is_enabled);
        $this->assertFalse((bool) $city->is_enabled);
        $this->assertSame('Syria', $country->translations->firstWhere('locale', 'en')?->name);
        $this->assertSame('سوريا', $country->translations->firstWhere('locale', 'ar')?->name);
        $this->assertSame('Damascus', $city->translations->firstWhere('locale', 'en')?->name);
        $this->assertSame('دمشق', $city->translations->firstWhere('locale', 'ar')?->name);
    }
}
