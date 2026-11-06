<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Legacy\LegacyFacultyProjectImportServiceInterface;
use App\Models\Faculty\Faculty;
use App\Models\Faculty\FacultyStudentProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LegacyImportFacultyProjectsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_projects_with_title_fallback_media_and_parent_visibility(): void
    {
        Http::fake([
            'https://www.spu.edu.sy/downloads/files/project.pdf' => Http::response('%PDF-1.4 test project', 200, ['Content-Type' => 'application/pdf']),
            'https://www.spu.edu.sy/downloads/files/missing.pdf' => Http::response('Not found', 404),
            'https://www.spu.edu.sy/downloads/files/cover.jpg' => Http::response(base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q=='), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Faculty::query()->create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'faculty_scope_slug' => 'pharmacy',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        $dump = tempnam(sys_get_temp_dir(), 'faculty-projects-');
        self::assertIsString($dump);
        file_put_contents($dump, $this->fixtureDump());

        try {
            $this->artisan('legacy-import:faculty-projects', [
                'dump' => $dump,
                '--write' => true,
                '--approve' => 'faculty-projects-20260827',
                '--enable-visible' => true,
                '--verify-media' => true,
            ])->assertSuccessful();

            $visible = FacultyStudentProject::query()->where('legacy_source_id', 7001)->firstOrFail();
            $hidden = FacultyStudentProject::query()->where('legacy_source_id', 7002)->firstOrFail();
            self::assertTrue($visible->is_enabled);
            self::assertFalse($hidden->is_enabled);
            self::assertSame('pharmacy-project-7001', $visible->slug);
            self::assertSame(1, $hidden->sort_order);
            self::assertSame(2, $visible->sort_order);
            self::assertSame('مشروع صيدلاني موثق', $visible->translations()->where('locale', 'en')->value('title'));
            self::assertNull($visible->translations()->where('locale', 'en')->value('summary'));
            self::assertSame(['إعداد الطالبة: سارة أحمد', 'أنجز المشروع بإشراف الدكتورة ليلى.'], $visible->translations()->where('locale', 'ar')->value('body_json'));
            self::assertSame('سارة أحمد', $visible->translations()->where('locale', 'ar')->value('team'));
            self::assertSame('الدكتورة ليلى', $visible->translations()->where('locale', 'ar')->value('supervisor'));
            self::assertCount(1, $visible->documents_json);
            self::assertCount(1, $visible->gallery_json);
            self::assertSame('downloads/files/project.pdf', $visible->documents_json[0]['file']);
            self::assertSame('downloads/files/cover.jpg', $visible->gallery_json[0]);

            $this->artisan('legacy-import:faculty-projects', [
                'dump' => $dump,
                '--write' => true,
                '--approve' => 'faculty-projects-20260827',
                '--enable-visible' => true,
                '--verify-media' => true,
            ])->assertSuccessful();

            self::assertSame(2, FacultyStudentProject::query()->count());
        } finally {
            @unlink($dump);
        }
    }

    private function fixtureDump(): string
    {
        return <<<'SQL'
INSERT INTO `jx_categories` (`id`) VALUES
(7001, 'Under Construction', 'مشروع صيدلاني موثق', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ' Translation to English will be soon', '<p>إعداد الطالبة: سارة أحمد</p><p>أنجز المشروع بإشراف الدكتورة ليلى.</p>', NULL, NULL, NULL, 0, 44, 2, 'cover.jpg', 1, 0, '', 0, '0000-00-00', '0000-00-00', NULL, 0, 0, 0, 0, 0, 0),
(7002, 'Hidden project', 'مشروع مخفي', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 44, 1, NULL, 0, 0, '', 0, '0000-00-00', '0000-00-00', NULL, 0, 0, 0, 0, 0, 0);
INSERT INTO `jx_items` (`id`) VALUES
(9001, 7001, 44, NULL, 'ملف المشروع', NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'project.pdf', NULL, NULL, 0, 0, 0, 0, 0, '2026-01-01 00:00:00', NULL, NULL, 1, NULL, 0, 3);
(9002, 7001, 44, NULL, 'ملف مفقود', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 'missing.pdf', NULL, NULL, 0, 0, 0, 0, 0, '2026-01-01 00:00:00', NULL, NULL, 1, NULL, 0, 3);
SQL;
    }

    /**
     * A second editorial approval gets its own token. Both must work, and
     * anything else must not - the token is the only thing standing between a
     * deploy and 500 rows of published content.
     */
    public function test_each_editorial_approval_token_authorises_a_write_and_nothing_else_does(): void
    {
        $dump = tempnam(sys_get_temp_dir(), 'faculty-projects-');
        self::assertIsString($dump);
        file_put_contents($dump, $this->fixtureDump());

        Faculty::query()->create([
            'slug' => 'pharmacy',
            'public_slug' => 'pharmacy',
            'faculty_scope_slug' => 'pharmacy',
            'sort_order' => 1,
            'is_enabled' => true,
        ]);

        try {
            foreach (['faculty-projects-20260827', 'faculty-projects-20261004-live500'] as $token) {
                FacultyStudentProject::query()->forceDelete();

                $this->artisan('legacy-import:faculty-projects', [
                    'dump' => $dump,
                    '--write' => true,
                    '--approve' => $token,
                ])->assertSuccessful();

                self::assertGreaterThan(
                    0,
                    FacultyStudentProject::query()->count(),
                    "Approval token {$token} must authorise a write",
                );
            }

            // An unrecognised token throws rather than exiting non-zero, so the
            // exception is the assertion. What matters either way is that no
            // row is written.
            foreach (['', 'faculty-projects-20260828', 'yes', 'faculty-projects-20261004-live499'] as $rejected) {
                FacultyStudentProject::query()->forceDelete();

                $threw = false;

                try {
                    $this->artisan('legacy-import:faculty-projects', [
                        'dump' => $dump,
                        '--write' => true,
                        '--approve' => $rejected,
                    ])->run();
                } catch (\InvalidArgumentException) {
                    $threw = true;
                }

                self::assertTrue($threw, "An unrecognised approval token must be refused: {$rejected}");
                self::assertSame(
                    0,
                    FacultyStudentProject::query()->count(),
                    "A write must not happen for an unrecognised approval token: {$rejected}",
                );
            }
        } finally {
            @unlink($dump);
        }
    }

    /**
     * Legacy bodies write the labels without the hamza - اعداد, not إعداد - and
     * put the value on the following line. Matching only the hamza spelling left
     * every team and supervisor empty and promoted the bare label to the
     * description, which is what shipped: cards reading "اعداد" above two blank
     * fields.
     */
    public function test_contributor_labels_are_read_with_or_without_the_hamza(): void
    {
        $service = app(LegacyFacultyProjectImportServiceInterface::class);

        $contributors = new \ReflectionMethod($service, 'contributors');
        $contributors->setAccessible(true);
        $summaryFrom = new \ReflectionMethod($service, 'summaryFrom');
        $summaryFrom->setAccessible(true);

        // Exactly the shape reported from the live site.
        $body = ['اعداد', 'Owais hilal', 'تاريخ', '2025-2026', 'نظام امتحانات ذكي يعتمد على الذكاء الاصطناعي.'];

        $result = $contributors->invoke($service, $body, 'SPU admission chatbot');
        self::assertSame('Owais hilal', $result['team'], 'A label written with a plain alef must still be read');

        self::assertSame(
            'نظام امتحانات ذكي يعتمد على الذكاء الاصطناعي.',
            $summaryFrom->invoke($service, $body),
            'The description must be the first real sentence, not a field label',
        );

        // The hamza spelling must keep working.
        $hamza = ['إعداد: Sara Ahmad', 'إشراف: Dr. Noor', 'وصف المشروع.'];
        $result = $contributors->invoke($service, $hamza, 'x');
        self::assertSame('Sara Ahmad', $result['team']);
        self::assertSame('Dr. Noor', $result['supervisor']);
        self::assertSame('وصف المشروع.', $summaryFrom->invoke($service, $hamza));

        // A project with nothing but labels has no description rather than a bogus one.
        self::assertNull($summaryFrom->invoke($service, ['اعداد', 'تاريخ']));
    }
}
