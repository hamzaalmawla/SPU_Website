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
            ->assertSee('English');
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
            ->assertSee('إدارة الصفحات والمحتوى ثنائي اللغة من نفس نمط التحرير.');
    }
}
