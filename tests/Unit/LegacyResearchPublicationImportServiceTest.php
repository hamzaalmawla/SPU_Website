<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyResearchPublicationImportServiceInterface;
use App\Models\Research\ResearchPublication;
use App\Models\Research\ResearchPublicationTranslation;
use App\Models\Shared\MigrationLog;
use App\Support\LegacyImport\OldDatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class LegacyResearchPublicationImportServiceTest extends TestCase
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

        app(OldDatabaseConnection::class)->connection();
        Schema::connection('legacy_mysql')->create('jx_member_categories', function ($table): void {
            $table->increments('id');
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->text('ar_data')->nullable();
            $table->text('en_data')->nullable();
            $table->unsignedInteger('parent')->nullable();
            $table->unsignedInteger('service_type')->nullable();
            $table->unsignedInteger('member_category_order')->default(0);
            $table->string('photo')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('url')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
        Schema::connection('legacy_mysql')->create('jx_member_items', function ($table): void {
            $table->increments('id');
            $table->unsignedInteger('member_category_id')->nullable();
            $table->unsignedInteger('service_type')->nullable();
            $table->string('ar_name')->nullable();
            $table->string('en_name')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->string('en_file')->nullable();
            $table->string('ar_file')->nullable();
            $table->boolean('is_accepted')->default(true);
        });

        app('db')->connection('legacy_mysql')->table('jx_member_categories')->insert([
            [
                'id' => 10,
                'ar_name' => 'بحث منشور',
                'en_name' => 'Published Research',
                'ar_data' => '<p>ملخص عربي</p>',
                'en_data' => '<p>English abstract</p>',
                'parent' => 5,
                'service_type' => 1,
                'member_category_order' => 2,
                'photo' => 'research.jpg',
                'is_visible' => 1,
                'url' => 'https://example.com/research',
                'start_date' => '2020-05-01',
                'end_date' => null,
            ],
            [
                'id' => 11,
                'ar_name' => 'بحث مخفي',
                'en_name' => 'Hidden Research',
                'ar_data' => null,
                'en_data' => null,
                'parent' => 5,
                'service_type' => 1,
                'member_category_order' => 3,
                'photo' => null,
                'is_visible' => 0,
                'url' => null,
                'start_date' => null,
                'end_date' => null,
            ],
            [
                'id' => 12,
                'ar_name' => 'محاضرة مؤجلة',
                'en_name' => 'Deferred Lecture',
                'ar_data' => null,
                'en_data' => null,
                'parent' => 5,
                'service_type' => 2,
                'member_category_order' => 4,
                'photo' => null,
                'is_visible' => 1,
                'url' => null,
                'start_date' => null,
                'end_date' => null,
            ],
        ]);
        app('db')->connection('legacy_mysql')->table('jx_member_items')->insert([
            'id' => 20,
            'member_category_id' => 10,
            'service_type' => 1,
            'ar_name' => 'ملف البحث',
            'en_name' => 'Research file',
            'photo' => null,
            'is_visible' => 1,
            'en_file' => 'research.pdf',
            'ar_file' => null,
            'is_accepted' => 1,
        ]);
    }

    public function test_dry_run_imports_only_visible_publication_candidates_without_writing(): void
    {
        $result = app(LegacyResearchPublicationImportServiceInterface::class)->import(batch: 'research-test');

        $this->assertFalse($result->written);
        $this->assertSame(3, $result->scannedRows);
        $this->assertSame(1, $result->publishedCandidateRows);
        $this->assertSame(1, $result->importableRows);
        $this->assertSame(1, $result->attachmentReferenceRows);
        $this->assertSame(1, $result->skipReasonCounts['not_published_on_old_site']);
        $this->assertSame(1, $result->skipReasonCounts['deferred_non_publication_row']);
        $this->assertSame(0, ResearchPublication::query()->count());
        $this->assertSame(0, MigrationLog::query()->count());
    }

    public function test_dry_run_limit_caps_importable_rows(): void
    {
        app('db')->connection('legacy_mysql')->table('jx_member_categories')->insert([
            'id' => 13,
            'ar_name' => 'بحث منشور ثان',
            'en_name' => 'Second Published Research',
            'ar_data' => null,
            'en_data' => null,
            'parent' => 5,
            'service_type' => 1,
            'member_category_order' => 5,
            'photo' => null,
            'is_visible' => 1,
            'url' => null,
            'start_date' => null,
            'end_date' => null,
        ]);

        $result = app(LegacyResearchPublicationImportServiceInterface::class)->import(batch: 'research-test', limit: 1);

        $this->assertSame(1, $result->importableRows);
        $this->assertSame(0, ResearchPublication::query()->count());
    }

    public function test_write_is_blocked_before_mutation_regardless_of_approval_token(): void
    {
        try {
            app(LegacyResearchPublicationImportServiceInterface::class)->import(
                write: true,
                approval: 'phase6-research-publications',
                batch: 'research-test',
            );
            $this->fail('Expected the /members/ reconciliation write freeze.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('jx_member_* import is blocked', $exception->getMessage());
        }

        $this->assertSame(0, ResearchPublication::query()->count());
        $this->assertSame(0, ResearchPublicationTranslation::query()->count());
        $this->assertSame(0, MigrationLog::query()->count());
    }
}
