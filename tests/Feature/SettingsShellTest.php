<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SettingValueDTO;
use App\Models\User\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_apply_cta_student_portal_and_staff_access_resolve_through_services(): void
    {
        $settings = $this->settingsService()->getPublicSettings('en');

        $this->assertSame('Apply now', $settings->applyCta->label);
        $this->assertSame('/en/admissions', $settings->applyCta->url);
        $this->assertSame('/e-services/it-support', $settings->studentPortalUrl);
        $this->assertSame('/e-services/staff-email', $settings->staffAccessUrl);
    }

    public function test_emergency_notice_payload_resolves_when_configured(): void
    {
        $updated = $this->settingsService()->updateGroup(
            new SettingsDTO(
                group: 'public_shell',
                locale: 'en',
                values: [
                    new SettingValueDTO(
                        key: 'emergency_notice',
                        type: 'json',
                        jsonValue: [
                            'is_enabled' => true,
                            'title' => 'Weather advisory',
                            'message' => 'Evening activity timings have changed.',
                            'url' => '/en/contact',
                        ],
                        textValue: null,
                        isPublic: true,
                    ),
                ],
            ),
            $this->author()->id,
        );

        $this->assertTrue($updated);

        $notice = $this->settingsService()->getEmergencyNotice('en');
        $navigation = $this->navigationService()->getFullNavigationPayload('en', 'en');

        $this->assertTrue($notice->isEnabled);
        $this->assertSame('Weather advisory', $notice->title);
        $this->assertSame('Weather advisory', $navigation->emergencyNotice->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.utility_shell_updated']);
    }

    public function test_settings_updates_invalidate_cached_settings_and_navigation_payloads(): void
    {
        $initialGroup = $this->settingsService()->getGroup('navigation', 'en');
        $initialNavigation = $this->navigationService()->getFullNavigationPayload('en', 'en');

        $this->assertSame('Apply now', $initialNavigation->applyCta?->label);
        $this->assertCount(1, array_filter($initialGroup->values, static fn (SettingValueDTO $value): bool => $value->key === 'apply_cta'));

        $this->assertTrue($this->settingsService()->updateGroup(
            new SettingsDTO(
                group: 'navigation',
                locale: 'en',
                values: [
                    new SettingValueDTO(
                        key: 'apply_cta',
                        type: 'json',
                        jsonValue: ['label' => 'Start your application', 'url' => '/en/admissions', 'target' => null, 'is_enabled' => true],
                        textValue: null,
                        isPublic: true,
                    ),
                ],
            ),
            $this->author()->id,
        ));

        $updatedGroup = $this->settingsService()->getGroup('navigation', 'en');
        $updatedNavigation = $this->navigationService()->getFullNavigationPayload('en', 'en');

        $applyCta = collect($updatedGroup->values)->firstWhere('key', 'apply_cta');

        $this->assertInstanceOf(SettingValueDTO::class, $applyCta);
        $this->assertSame('Start your application', $applyCta->jsonValue['label'] ?? null);
        $this->assertSame('Start your application', $updatedNavigation->applyCta?->label);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.update']);
    }

    public function test_footer_payload_resolves_legal_social_and_contact_data_for_locale(): void
    {
        $footer = $this->settingsService()->getFooterSettings('en');
        $social = $this->settingsService()->getSocialContactSettings('en');
        $navigation = $this->navigationService()->getFullNavigationPayload('en', 'en/about');

        $this->assertSame('Syrian Private University', $footer->brandTitle);
        $this->assertNotNull($footer->logoUrl);
        $this->assertSame([], $footer->legalLinks);
        $this->assertSame([], $social->socialLinks);
        $this->assertNotEmpty($social->contactLinks);
        $this->assertSame('Syrian Private University', $navigation->footerSettings->brandTitle);
    }

    public function test_footer_setting_update_invalidates_footer_payload_and_writes_audit_rows(): void
    {
        $this->assertTrue($this->settingsService()->updateGroup(
            new SettingsDTO(
                group: 'footer',
                locale: 'en',
                values: [
                    new SettingValueDTO(
                        key: 'footer',
                        type: 'json',
                        jsonValue: [
                            'copyrightText' => 'Updated footer copyright',
                            'address' => 'Damascus, Syria',
                            'phone' => '+963 11 000 0000',
                            'email' => 'info@spu.edu.sy',
                            'brandBlock' => [
                                'title' => 'Updated Footer Brand',
                                'body' => 'Updated footer summary',
                                'logoUrl' => '/images/home/footer-logo.png',
                            ],
                            'mapEmbed' => [
                                'url' => 'https://maps.google.com/?q=Damascus+Syria',
                            ],
                            'legalLinks' => [
                                ['label' => 'Accessibility', 'url' => '/en/about'],
                            ],
                        ],
                        textValue: null,
                        isPublic: true,
                    ),
                ],
            ),
            $this->author()->id,
        ));

        $footer = $this->settingsService()->getFooterSettings('en');
        $navigation = $this->navigationService()->getFullNavigationPayload('en', 'en');

        $this->assertSame('Updated Footer Brand', $footer->brandTitle);
        $this->assertSame('Updated Footer Brand', $navigation->footerSettings->brandTitle);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.footer_updated']);
    }

    private function settingsService(): SettingsServiceInterface
    {
        return app(SettingsServiceInterface::class);
    }

    private function navigationService(): NavigationServiceInterface
    {
        return app(NavigationServiceInterface::class);
    }

    private function author(): User
    {
        return User::query()->where('role_slug', 'super_admin')->firstOrFail();
    }
}
