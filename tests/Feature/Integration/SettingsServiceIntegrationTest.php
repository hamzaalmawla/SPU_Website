<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SettingValueDTO;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  Write-then-read round-trip
    // ──────────────────────────────────────────────────────────────

    public function test_write_then_read_returns_written_text_value(): void
    {
        // Write a new student portal URL value.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('navigation', null, [
                    new SettingValueDTO(
                        key: 'student_portal_url',
                        type: 'text',
                        textValue: 'https://portal-updated.spu.edu.sy',
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        // Verify via the dedicated accessor that the new value is returned.
        $this->assertSame(
            'https://portal-updated.spu.edu.sy',
            $this->settingsService()->getStudentPortalUrl(),
        );

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.update']);
    }

    public function test_write_then_read_returns_written_json_value(): void
    {
        $jsonPayload = [
            'label' => 'Apply Now',
            'url' => '/en/admissions',
            'is_enabled' => true,
        ];

        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('navigation', 'en', [
                    new SettingValueDTO(
                        key: 'apply_cta',
                        type: 'json',
                        jsonValue: $jsonPayload,
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        $group = $this->settingsService()->getGroup('navigation', 'en');
        $ctaSetting = collect($group->values)->firstWhere('key', 'apply_cta');

        $this->assertInstanceOf(SettingValueDTO::class, $ctaSetting);
        $this->assertSame('Apply Now', $ctaSetting->jsonValue['label'] ?? null);
        $this->assertSame('/en/admissions', $ctaSetting->jsonValue['url'] ?? null);
    }

    // ──────────────────────────────────────────────────────────────
    //  Bilingual settings stored and retrieved independently
    // ──────────────────────────────────────────────────────────────

    public function test_bilingual_settings_stored_and_retrieved_independently_per_locale(): void
    {
        // Write Arabic emergency notice.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('public_shell', 'ar', [
                    new SettingValueDTO(
                        key: 'emergency_notice',
                        type: 'json',
                        jsonValue: [
                            'is_enabled' => true,
                            'title' => 'إشعار طوارئ',
                            'message' => 'رسالة طوارئ تجريبية',
                        ],
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        // Write English emergency notice.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('public_shell', 'en', [
                    new SettingValueDTO(
                        key: 'emergency_notice',
                        type: 'json',
                        jsonValue: [
                            'is_enabled' => true,
                            'title' => 'Emergency Notice',
                            'message' => 'Test emergency message',
                        ],
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        // Retrieve Arabic and verify it has Arabic content.
        $arabicNotice = $this->settingsService()->getEmergencyNotice('ar');

        $this->assertSame('ar', $arabicNotice->locale);
        $this->assertTrue($arabicNotice->isEnabled);
        $this->assertSame('إشعار طوارئ', $arabicNotice->title);
        $this->assertSame('رسالة طوارئ تجريبية', $arabicNotice->message);

        // Retrieve English and verify it has English content.
        $englishNotice = $this->settingsService()->getEmergencyNotice('en');

        $this->assertSame('en', $englishNotice->locale);
        $this->assertTrue($englishNotice->isEnabled);
        $this->assertSame('Emergency Notice', $englishNotice->title);
        $this->assertSame('Test emergency message', $englishNotice->message);
    }

    public function test_bilingual_footer_settings_are_locale_independent(): void
    {
        // Write Arabic footer.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('footer', 'ar', [
                    new SettingValueDTO(
                        key: 'footer',
                        type: 'json',
                        jsonValue: [
                            'copyrightText' => '© الجامعة السورية الخاصة',
                            'address' => 'دمشق، سوريا',
                            'phone' => '+963-11-1234567',
                        ],
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        // Write English footer.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('footer', 'en', [
                    new SettingValueDTO(
                        key: 'footer',
                        type: 'json',
                        jsonValue: [
                            'copyrightText' => '© Syrian Private University',
                            'address' => 'Damascus, Syria',
                            'phone' => '+963-11-1234567',
                        ],
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        // Verify Arabic footer.
        $arabicFooter = $this->settingsService()->getFooterSettings('ar');

        $this->assertSame('ar', $arabicFooter->locale);
        $this->assertSame('© الجامعة السورية الخاصة', $arabicFooter->copyrightText);
        $this->assertSame('دمشق، سوريا', $arabicFooter->address);

        // Verify English footer.
        $englishFooter = $this->settingsService()->getFooterSettings('en');

        $this->assertSame('en', $englishFooter->locale);
        $this->assertSame('© Syrian Private University', $englishFooter->copyrightText);
        $this->assertSame('Damascus, Syria', $englishFooter->address);

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.footer_updated']);
    }

    public function test_apply_cta_bilingual_retrieval(): void
    {
        // Write Arabic CTA.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('navigation', 'ar', [
                    new SettingValueDTO(
                        key: 'apply_cta',
                        type: 'json',
                        jsonValue: [
                            'label' => 'قدّم الآن',
                            'url' => '/ar/admissions',
                            'is_enabled' => true,
                        ],
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        // Write English CTA.
        $this->assertTrue(
            $this->settingsService()->updateGroup(
                new SettingsDTO('navigation', 'en', [
                    new SettingValueDTO(
                        key: 'apply_cta',
                        type: 'json',
                        jsonValue: [
                            'label' => 'Apply Now',
                            'url' => '/en/admissions',
                            'is_enabled' => true,
                        ],
                        isPublic: true,
                    ),
                ]),
                $this->author()->id,
            ),
        );

        $arabicCta = $this->settingsService()->getApplyCtaTarget('ar');

        $this->assertSame('ar', $arabicCta->locale);
        $this->assertSame('قدّم الآن', $arabicCta->label);
        $this->assertSame('/ar/admissions', $arabicCta->url);

        $englishCta = $this->settingsService()->getApplyCtaTarget('en');

        $this->assertSame('en', $englishCta->locale);
        $this->assertSame('Apply Now', $englishCta->label);
        $this->assertSame('/en/admissions', $englishCta->url);
    }

    public function test_unsupported_locale_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->settingsService()->getEmergencyNotice('fr');
    }

    public function test_unsupported_group_key_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->settingsService()->getGroup('nonexistent_group', 'en');
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function settingsService(): SettingsServiceInterface
    {
        return app(SettingsServiceInterface::class);
    }

    private function author(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }
}
