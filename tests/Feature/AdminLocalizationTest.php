<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\PageResource;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_admin_defaults_to_arabic_rtl(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
        ]);

        $this->actingAs($user, 'web')
            ->get('/admin/manage-settings')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('إدارة الإعدادات')
            ->assertSee('الإنجليزية');
    }

    public function test_filament_admin_can_render_english_ltr(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
        ]);

        $this->actingAs($user, 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/manage-settings')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Manage Settings')
            ->assertSee('العربية');
    }

    public function test_navigation_labels_resolve_from_current_admin_locale(): void
    {
        app()->setLocale('ar');

        $this->assertSame('الصفحات', PageResource::getNavigationLabel());
        $this->assertSame('إدارة الإعدادات', (new ManageSettings)->getTitle());

        app()->setLocale('en');

        $this->assertSame('Pages', PageResource::getNavigationLabel());
        $this->assertSame('Manage Settings', (new ManageSettings)->getTitle());
    }

    public function test_resource_pages_render_shared_cms_workspace_header(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'super_admin',
        ]);

        $this->actingAs($user, 'web')
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('مساحة إدارة المحتوى')
            ->assertSee('لغات المحتوى')
            ->assertDontSee('حالة اللغات')
            ->assertSee('إدارة الصفحات والمحتوى ثنائي اللغة من نفس نمط التحرير.');
    }

    public function test_shared_workspace_css_includes_keyboard_mobile_dark_mode_and_motion_safeguards(): void
    {
        $css = file_get_contents(resource_path('css/filament/admin.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.spu-workspace__link:focus-visible', $css);
        $this->assertStringContainsString('.spu-task-card:focus-visible', $css);
        $this->assertStringContainsString('[dir="rtl"] .spu-workspace__eyebrow', $css);
        $this->assertStringContainsString('.dark .spu-choice-panel', $css);
        $this->assertStringContainsString('.spu-media-preview', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
    }
}
