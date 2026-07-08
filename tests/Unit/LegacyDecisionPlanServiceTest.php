<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyDecisionPlanServiceInterface;
use App\Models\Shared\MigrationRejection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyDecisionPlanServiceTest extends TestCase
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

        config()->set('old_database.connection_name', 'legacy_decision_plan_testing');
        config()->set('old_database.connection', $connection);
        config()->set('database.connections.legacy_decision_plan_testing', $connection);
        DB::purge('legacy_decision_plan_testing');
    }

    public function test_decision_plan_applies_automatic_policies_and_picks_canonical_duplicate(): void
    {
        Storage::fake('local');
        $this->createLegacyCategoriesTable();
        $this->createRejections();

        $result = app(LegacyDecisionPlanServiceInterface::class)->export('news');

        $this->assertSame('news', $result->module);
        $this->assertSame(6, $result->decisionCount);
        $this->assertSame(0, $result->manualReviewCount);
        $this->assertSame(1, $result->actionCounts['auto_keep_canonical_duplicate']);
        $this->assertSame(1, $result->actionCounts['auto_skip_duplicate']);
        $this->assertSame(1, $result->actionCounts['auto_accept_sanitized_html']);
        $this->assertSame(1, $result->actionCounts['auto_strip_inline_base64_image']);
        $this->assertSame(1, $result->actionCounts['auto_redirect_candidate']);
        $this->assertSame(1, $result->actionCounts['auto_skip_invalid_contact_until_verified']);

        Storage::disk('local')->assertExists($result->path);
        $payload = json_decode(Storage::disk('local')->get($result->path), true);
        $decisions = collect($payload['decisions']);

        $this->assertSame(2, $decisions->firstWhere('action', 'auto_keep_canonical_duplicate')['source_id']);
        $this->assertSame(2, $decisions->firstWhere('action', 'auto_skip_duplicate')['canonical_source_id']);
        $this->assertSame('/en', $decisions->firstWhere('action', 'auto_redirect_candidate')['target_url']);
        $this->assertFalse($decisions->firstWhere('action', 'auto_skip_invalid_contact_until_verified')['public_import_allowed']);
    }

    private function createLegacyCategoriesTable(): void
    {
        Schema::connection('legacy_decision_plan_testing')->create('jx_categories', function ($schema): void {
            $schema->increments('id');
            $schema->integer('is_visible')->nullable();
            $schema->integer('is_accepted')->nullable();
            $schema->integer('is_archive')->nullable();
            $schema->string('ar_name')->nullable();
            $schema->string('en_name')->nullable();
            $schema->text('ar_data')->nullable();
            $schema->text('en_data')->nullable();
            $schema->string('updated_date')->nullable();
        });

        DB::connection('legacy_decision_plan_testing')->table('jx_categories')->insert([
            [
                'id' => 1,
                'is_visible' => 0,
                'is_accepted' => 0,
                'is_archive' => 1,
                'ar_name' => 'Old duplicate',
                'en_name' => 'Duplicate',
                'ar_data' => 'short',
                'en_data' => 'short',
                'updated_date' => '2020-01-01',
            ],
            [
                'id' => 2,
                'is_visible' => 1,
                'is_accepted' => 1,
                'is_archive' => 0,
                'ar_name' => 'Better duplicate',
                'en_name' => 'Duplicate',
                'ar_data' => str_repeat('content ', 20),
                'en_data' => str_repeat('content ', 20),
                'updated_date' => '2024-01-01',
            ],
        ]);
    }

    private function createRejections(): void
    {
        foreach ([1, 2] as $sourceId) {
            MigrationRejection::query()->create([
                'module' => 'news',
                'source_table' => 'jx_categories',
                'source_id' => $sourceId,
                'reason_code' => 'duplicate_legacy_content',
                'reason_message' => 'duplicate',
                'raw_summary' => [
                    'rule' => 'duplicate',
                    'columns' => ['service_type', 'en_name'],
                    'duplicate_key' => '1|duplicate',
                ],
            ]);
        }

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 3,
            'reason_code' => 'unsafe_html',
            'reason_message' => 'unsafe html',
            'raw_summary' => [
                'field' => 'ar_data',
                'issue_codes' => ['unsafe_html'],
                'original_preview' => '<script>alert(1)</script><p>Keep</p>',
                'cleaned_preview' => '<p>Keep</p>',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 4,
            'reason_code' => 'base64_inline_image',
            'reason_message' => 'inline image',
            'raw_summary' => [
                'field' => 'ar_data',
                'issue_codes' => ['base64_inline_image'],
                'cleaned_preview' => '<p></p>',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_categories',
            'source_id' => 5,
            'reason_code' => 'legacy_internal_link',
            'reason_message' => 'legacy link',
            'raw_summary' => [
                'field' => 'ar_data',
                'legacy_path' => '/index.php?lang=1',
            ],
        ]);

        MigrationRejection::query()->create([
            'module' => 'news',
            'source_table' => 'jx_admins',
            'source_id' => 6,
            'reason_code' => 'invalid_email',
            'reason_message' => 'invalid email',
            'raw_summary' => [
                'field' => 'email',
                'original_preview' => 'person@example.edu Phone',
            ],
        ]);
    }
}
