<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\News\NewsAdminWorkflowServiceInterface;
use App\Models\News\NewsArticle;
use App\Models\News\NewsCategory;
use App\Models\Shared\AuditLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NewsAdminWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private NewsAdminWorkflowServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NewsAdminWorkflowServiceInterface::class);
    }

    public function test_faculty_editor_cannot_publish_article_on_create(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'pharmacy',
        ]);

        $data = $this->service->prepareArticleDataForCreate([
            'slug' => 'pharmacy-news',
            'status' => 'published',
            'faculty_scope_slug' => 'medicine',
        ], (int) $user->getKey());

        $this->assertSame('draft', $data['status']);
        $this->assertNull($data['published_at']);
        $this->assertSame('pharmacy', $data['faculty_scope_slug']);
        $this->assertSame((int) $user->getKey(), $data['created_by']);
        $this->assertSame((int) $user->getKey(), $data['updated_by']);
    }

    public function test_faculty_editor_cannot_change_status_of_public_article(): void
    {
        $user = User::factory()->create([
            'role_slug' => 'faculty_editor',
            'faculty_scope_slug' => 'pharmacy',
        ]);
        $article = NewsArticle::query()->create([
            'slug' => 'published-pharmacy-news',
            'status' => 'published',
            'published_at' => now()->subDays(3),
            'is_enabled' => true,
            'faculty_scope_slug' => 'pharmacy',
        ]);
        $publishedAt = $article->published_at?->toIso8601String();

        $data = $this->service->prepareArticleDataForUpdate((int) $article->getKey(), [
            'status' => 'draft',
            'published_at' => null,
            'scheduled_at' => now()->addDay(),
            'faculty_scope_slug' => 'medicine',
        ], (int) $user->getKey());

        $this->assertSame('published', $data['status']);
        $this->assertSame($publishedAt, $data['published_at']?->toIso8601String());
        $this->assertNull($data['scheduled_at']);
        $this->assertSame('pharmacy', $data['faculty_scope_slug']);
    }

    public function test_editor_can_publish_article(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $article = NewsArticle::query()->create([
            'slug' => 'draft-news',
            'status' => 'draft',
            'is_enabled' => true,
        ]);

        $data = $this->service->prepareArticleDataForUpdate((int) $article->getKey(), [
            'status' => 'published',
            'published_at' => null,
        ], (int) $user->getKey());

        $this->assertSame('published', $data['status']);
        $this->assertNotNull($data['published_at']);
    }

    public function test_article_write_events_create_audit_entries(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $article = NewsArticle::query()->create([
            'slug' => 'audited-news',
            'status' => 'draft',
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->service->recordArticleCreated((int) $article->getKey(), (int) $user->getKey()));

        $article->forceFill(['status' => 'published', 'published_at' => now()])->save();

        $this->assertTrue($this->service->recordArticleUpdated((int) $article->getKey(), (int) $user->getKey(), [
            'status' => 'draft',
            'published_at' => null,
            'scheduled_at' => null,
            'faculty_scope_slug' => null,
        ]));
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'news.article.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'news.article.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'news.article.status_changed']);
    }

    public function test_category_write_events_create_audit_entries(): void
    {
        $user = User::factory()->create(['role_slug' => 'editor']);
        $category = NewsCategory::query()->create([
            'slug' => 'campus-news',
            'type' => 'news',
            'sort_order' => 10,
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->service->recordCategoryCreated((int) $category->getKey(), (int) $user->getKey()));

        $category->forceFill(['sort_order' => 20])->save();

        $this->assertTrue($this->service->recordCategoryUpdated((int) $category->getKey(), (int) $user->getKey(), [
            'slug' => 'campus-news',
            'type' => 'news',
            'sort_order' => 10,
            'is_enabled' => true,
        ]));
        $this->assertTrue($this->service->deleteCategory((int) $category->getKey(), (int) $user->getKey()));
        $this->assertSoftDeleted('news_categories', ['id' => $category->getKey()]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'news.category.created']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'news.category.updated']);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'news.category.deleted']);
    }
}
