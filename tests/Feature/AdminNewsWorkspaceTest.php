<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\ManageNews;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\User\User;
use Database\Seeders\NewsCategorySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminNewsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NewsCategorySeeder::class);
    }

    public function test_admin_panel_warns_before_leaving_dirty_forms(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasUnsavedChangesAlerts());
    }

    public function test_arabic_news_editor_uses_the_simplified_staff_layout(): void
    {
        $this->actingAs($this->administrator(), 'web')
            ->get('/admin/news-articles/create')
            ->assertOk()
            ->assertSee('معلومات المقال')
            ->assertSee('المحتوى العربي والإنجليزي')
            ->assertSee('نوع المحتوى')
            ->assertSee('خبر')
            ->assertSee('إعلان')
            ->assertSee('النشر والظهور')
            ->assertSee('يبقى المقال الظاهر للزوار دون تغيير')
            ->assertDontSee('موعد النشر')
            ->assertSee('إعدادات متقدمة')
            ->assertSee('يتم إنشاؤه تلقائياً من العنوان');
    }

    public function test_english_news_list_uses_localized_article_and_editorial_type(): void
    {
        $category = NewsCategory::query()->create([
            'slug' => 'university-news',
            'type' => 'news',
            'is_enabled' => true,
        ]);
        $category->translations()->createMany([
            ['locale' => 'ar', 'name' => 'أخبار الجامعة'],
            ['locale' => 'en', 'name' => 'University news'],
        ]);
        $article = NewsArticle::query()->create([
            'news_category_id' => $category->getKey(),
            'slug' => 'welcome-week',
            'status' => 'draft',
            'is_enabled' => true,
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => 'أسبوع الترحيب'],
            ['locale' => 'en', 'title' => 'Welcome week'],
        ]);

        $this->actingAs($this->administrator(), 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/news-articles')
            ->assertOk()
            ->assertSee('Welcome week')
            ->assertSee('News')
            ->assertSee('Publication state')
            ->assertSee('Draft')
            ->assertSee('News pages and events');
    }

    public function test_news_category_management_is_not_exposed_to_staff(): void
    {
        $this->actingAs($this->administrator(), 'web')
            ->get('/admin/news-categories/create')
            ->assertForbidden();
    }

    public function test_news_edit_screen_exposes_only_the_revision_workflow_actions(): void
    {
        $category = NewsCategory::query()->where('type', 'news')->firstOrFail();
        $article = NewsArticle::query()->create([
            'news_category_id' => $category->getKey(),
            'slug' => 'revision-workflow-actions',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'is_enabled' => true,
        ]);
        $article->translations()->createMany([
            ['locale' => 'ar', 'title' => 'إجراءات المراجعة', 'body' => '<p>محتوى عربي</p>'],
            ['locale' => 'en', 'title' => 'Revision actions', 'body' => '<p>English content</p>'],
        ]);

        $response = $this->actingAs($this->administrator(), 'web')
            ->get('/admin/news-articles/'.$article->getKey().'/edit')
            ->assertOk()
            ->assertSee('حفظ المسودة')
            ->assertSee('حفظ ومعاينة العربية')
            ->assertSee('حفظ ومعاينة الإنجليزية')
            ->assertSee('نشر الآن')
            ->assertSee('جدولة النشر')
            ->assertSee('إلغاء النشر')
            ->assertDontSee('تاريخ النشر');

        $this->assertSame(1, substr_count($response->getContent(), 'حفظ المسودة'));
        $response->assertDontSee('حفظ التغييرات');
    }

    public function test_broad_news_workspace_exposes_only_canonical_page_targets(): void
    {
        $this->actingAs($this->administrator(), 'web');

        $component = Livewire::test(ManageNews::class);
        $method = new \ReflectionMethod(ManageNews::class, 'targetOptions');
        $method->setAccessible(true);
        /** @var array<string, string> $options */
        $options = $method->invoke($component->instance());

        $this->assertSame(['news.index', 'news.articles', 'news.gallery'], array_keys($options));
        $this->assertArrayNotHasKey('news.article', $options);
        $this->assertArrayNotHasKey('news.announcements', $options);
        $this->assertArrayNotHasKey('news.events', $options);
    }

    public function test_stale_news_target_queries_fall_back_to_the_news_landing_editor(): void
    {
        $this->actingAs($this->administrator(), 'web');

        foreach (['news.article', 'news.announcements', 'news.events', 'unknown'] as $target) {
            Livewire::withQueryParams(['target' => $target])
                ->test(ManageNews::class)
                ->assertSet('activeTargetKey', 'news.index')
                ->assertSet('data.target_key', 'news.index')
                ->assertDontSee('Target Schema Pending');
        }
    }

    public function test_active_news_shell_targets_use_arabic_admin_labels(): void
    {
        $user = $this->administrator();

        foreach ([
            'news.index' => 'مقدمة الصفحة',
            'news.articles' => 'صفحة المقالات الإخبارية',
            'news.gallery' => 'معرض الوسائط',
        ] as $target => $label) {
            $this->actingAs($user, 'web')
                ->get('/admin/manage-news?target='.$target)
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee('Target Schema Pending');
        }
    }

    private function administrator(): User
    {
        return User::factory()->create([
            'role_slug' => 'super_admin',
            'is_locked' => false,
        ]);
    }
}
