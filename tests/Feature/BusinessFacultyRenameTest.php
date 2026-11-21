<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The 2026-11-08 rename of the business faculty to "كلية الأعمال" / "Business Faculty". */
final class BusinessFacultyRenameTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_08_000001_rename_business_faculty.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_migration_renames_the_faculty_where_it_is_named_and_nowhere_else(): void
    {
        $facultyId = (int) DB::table('faculties')->where('public_slug', 'business-administration')->value('id');
        $userId = (int) User::query()->where('role_slug', 'super_admin')->value('id');

        // The live database as it was before the rename.
        DB::table('faculty_translations')->where('faculty_id', $facultyId)->where('locale', 'ar')->update(['name' => 'كلية إدارة الأعمال', 'catalog_title' => 'كلية إدارة الأعمال']);
        DB::table('faculty_translations')->where('faculty_id', $facultyId)->where('locale', 'en')->update(['name' => 'Business Administration', 'catalog_title' => 'Faculty of Business Administration']);
        DB::table('menu_items')->where('url', '/en/faculties/business-administration')->update(['label' => 'Business Administration']);
        DB::table('menu_items')->where('url', '/ar/faculties/business-administration')->update(['label' => 'كلية إدارة الأعمال']);

        $plan = ['translations' => [
            'ar' => ['title' => 'الخطة الدراسية', 'payload' => ['plan' => [
                'facultyAr' => 'إدارة الأعمال',
                'summaryAr' => 'خطط دراسية ديناميكية لأقسام كلية إدارة الأعمال.',
                'departments' => [['id' => 'management', 'nameAr' => 'إدارة الأعمال', 'nameEn' => 'Business Administration', 'terms' => [['courses' => [
                    ['id' => 'asba-8-17', 'titleAr' => 'إدارة الأعمال الدولية', 'titleEn' => 'International Business Administration'],
                ]]]]],
            ]]],
            'en' => ['title' => 'Business Administration Study Plan | SPU'],
        ]];
        DB::table('cms_target_contents')->updateOrInsert(
            ['target_key' => 'facilities.business-administration.study_plan'],
            ['payload_json' => json_encode($plan), 'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        );

        $publishedVersion = ['translations' => ['ar' => ['title' => 'كلية إدارة الأعمال'], 'en' => ['title' => 'Faculty of Business Administration']]];
        $pendingDraft = ['translations' => ['ar' => ['title' => 'كلية  الأعمال', 'body' => 'أرحب بكم في كلية العلوم الإدارية.'], 'en' => ['title' => 'Business  Faculty']]];
        foreach ([['published', 1, $publishedVersion], ['draft', 2, $pendingDraft]] as [$status, $version, $payload]) {
            DB::table('cms_drafts')->insert([
                'target_key' => 'facilities.business-administration',
                'payload_json' => json_encode($payload),
                'status' => $status,
                'created_by' => $userId,
                'version' => $version,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $person = ['translations' => ['ar' => ['role' => 'عميد كلية إدارة الأعمال', 'bio' => 'كان نائب عميد في كلية إدارة الأعمال من 2011 حتى 2013.']]];
        DB::table('cms_target_contents')->updateOrInsert(
            ['target_key' => 'entity.person.9001'],
            ['payload_json' => json_encode($person), 'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        );

        $this->runMigration();
        $this->runMigration(); // Running it again changes nothing.

        $this->assertSame(
            ['ar' => ['كلية الأعمال', 'كلية الأعمال'], 'en' => ['Business Faculty', 'Business Faculty']],
            DB::table('faculty_translations')->where('faculty_id', $facultyId)->orderBy('locale')->get(['locale', 'name', 'catalog_title'])
                ->mapWithKeys(fn ($row): array => [$row->locale => [$row->name, $row->catalog_title]])->all(),
        );
        $this->assertSame('Business Faculty', DB::table('menu_items')->where('url', '/en/faculties/business-administration')->value('label'));
        $this->assertSame('كلية الأعمال', DB::table('menu_items')->where('url', '/ar/faculties/business-administration')->value('label'));

        $renamedPlan = json_decode((string) DB::table('cms_target_contents')->where('target_key', 'facilities.business-administration.study_plan')->value('payload_json'), true);
        $arPlan = $renamedPlan['translations']['ar']['payload']['plan'];
        $this->assertSame('كلية الأعمال', $arPlan['facultyAr']);
        $this->assertSame('خطط دراسية ديناميكية لأقسام كلية الأعمال.', $arPlan['summaryAr']);
        $this->assertSame('إدارة الأعمال', $arPlan['departments'][0]['nameAr'], 'The program keeps its name.');
        $this->assertSame('International Business Administration', $arPlan['departments'][0]['terms'][0]['courses'][0]['titleEn'], 'The course keeps its name.');
        $this->assertSame('Business Faculty Study Plan | SPU', $renamedPlan['translations']['en']['title']);

        $drafts = DB::table('cms_drafts')->where('target_key', 'facilities.business-administration')->pluck('payload_json', 'status')
            ->map(fn (string $json): array => json_decode($json, true));
        $this->assertSame('كلية إدارة الأعمال', $drafts['published']['translations']['ar']['title'], 'An older published version is history.');
        $this->assertSame(['title' => 'كلية الأعمال', 'body' => 'أرحب بكم في كلية الأعمال.'], $drafts['draft']['translations']['ar'], 'The pending draft carries the new name.');
        $this->assertSame('Business Faculty', $drafts['draft']['translations']['en']['title']);

        $renamedPerson = json_decode((string) DB::table('cms_target_contents')->where('target_key', 'entity.person.9001')->value('payload_json'), true);
        $this->assertSame('عميد كلية الأعمال', $renamedPerson['translations']['ar']['role']);
        $this->assertSame($person['translations']['ar']['bio'], $renamedPerson['translations']['ar']['bio'], 'A biography is left as written.');

        Cache::flush();
        $this->get('/ar/faculties/business-administration')->assertOk()->assertSee('كلية الأعمال')->assertDontSee('كلية إدارة الأعمال');
        $this->get('/en/faculties/business-administration')->assertOk()->assertSee('Business Faculty')->assertDontSee('Faculty of Business Administration');
        $this->get('/ar/faculties')->assertOk()->assertSee('كلية الأعمال')->assertDontSee('كلية إدارة الأعمال');
    }

    public function test_a_fresh_site_names_the_faculty_by_its_new_name(): void
    {
        $this->get('/ar')->assertOk()->assertSee('كلية الأعمال')->assertDontSee('كلية إدارة الأعمال');
        $this->get('/en')->assertOk()->assertSee('Business Faculty')->assertDontSee('Faculty of Business Administration');
        $this->get('/en/faculties/business-administration')->assertOk()->assertSee('Business Faculty');
    }

    private function runMigration(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->up();
    }
}
