<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Pages\ListPages;
use App\Models\Page\Page;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_titles_follow_the_admin_locale_and_fall_back_to_arabic(): void
    {
        $page = $this->createPage('bilingual-page', [
            'ar' => 'عنوان عربي',
            'en' => 'English title',
        ]);
        $fallbackPage = $this->createPage('arabic-only-page', [
            'ar' => 'عنوان بديل',
        ]);

        app()->setLocale('ar');
        $this->assertSame('عنوان عربي', PageResource::getRecordTitle($page));

        app()->setLocale('en');
        $this->assertSame('English title', PageResource::getRecordTitle($page));
        $this->assertSame('عنوان بديل', PageResource::getRecordTitle($fallbackPage));
    }

    public function test_title_sorting_uses_the_active_admin_locale(): void
    {
        $this->actingAs($this->createAdministrator(), 'web');

        $first = $this->createPage('first-page', [
            'ar' => 'Zulu',
            'en' => 'Alpha',
        ]);
        $second = $this->createPage('second-page', [
            'ar' => 'Alpha',
            'en' => 'Zulu',
        ]);

        app()->setLocale('ar');
        $this->assertSame([$second->id, $first->id], $this->sortedPageIds());

        app()->setLocale('en');
        $this->assertSame([$first->id, $second->id], $this->sortedPageIds());
    }

    public function test_title_searching_uses_the_active_admin_locale_and_english_fallback(): void
    {
        $this->actingAs($this->createAdministrator(), 'web');

        $bilingualPage = $this->createPage('bilingual-page', [
            'ar' => 'arabic-primary-token',
            'en' => 'english-primary-token',
        ]);
        $arabicFallbackPage = $this->createPage('arabic-fallback-page', [
            'ar' => 'arabic-fallback-token',
        ]);
        $blankEnglishPage = $this->createPage('blank-english-page', [
            'ar' => 'arabic-blank-title-token',
            'en' => '   ',
        ]);

        app()->setLocale('en');
        $this->assertSame([$bilingualPage->id], $this->searchedPageIds('english-primary-token'));
        $this->assertSame([], $this->searchedPageIds('arabic-primary-token'));
        $this->assertSame([$arabicFallbackPage->id], $this->searchedPageIds('arabic-fallback-token'));
        $this->assertSame([$blankEnglishPage->id], $this->searchedPageIds('arabic-blank-title-token'));

        app()->setLocale('ar');
        $this->assertSame([$bilingualPage->id], $this->searchedPageIds('arabic-primary-token'));
        $this->assertSame([], $this->searchedPageIds('english-primary-token'));
    }

    public function test_page_list_uses_english_titles_and_marks_arabic_fallbacks(): void
    {
        $user = $this->createAdministrator();
        $this->createPage('bilingual-page', [
            'ar' => 'عنوان القائمة',
            'en' => 'English list title',
        ]);
        $this->createPage('arabic-only-page', [
            'ar' => 'عنوان بديل',
        ]);

        $this->actingAs($user, 'web')
            ->withSession(['admin_locale' => 'en'])
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('Manage Pages')
            ->assertSee('English list title')
            ->assertSee('Arabic fallback')
            ->assertSee('Search pages')
            ->assertSee('Publication status');
    }

    public function test_page_list_localizes_empty_and_search_empty_states(): void
    {
        $this->actingAs($this->createAdministrator(), 'web');
        app()->setLocale('en');

        Livewire::test(ListPages::class)
            ->assertSee('No pages yet');

        $this->createPage('existing-page', [
            'ar' => 'عنوان موجود',
            'en' => 'Existing page',
        ]);

        Livewire::test(ListPages::class)
            ->set('tableSearch', 'does-not-exist')
            ->assertSee('No pages match your search');
    }

    public function test_page_edit_screen_uses_arabic_interface_labels_for_english_content(): void
    {
        $user = $this->createAdministrator();
        $page = $this->createPage('localized-page', [
            'ar' => 'عنوان الصفحة',
            'en' => 'Page title',
        ]);

        $this->actingAs($user, 'web')
            ->get("/admin/pages/{$page->id}/edit")
            ->assertOk()
            ->assertSee('تحرير عنوان الصفحة')
            ->assertSee('حالة النشر: مسودة')
            ->assertSee('إعدادات الصفحة')
            ->assertSee('إعدادات متقدمة')
            ->assertSee('العنوان الإنجليزي')
            ->assertSee('حفظ المسودة');
    }

    /** @param array<string, string> $translations */
    private function createPage(string $slug, array $translations): Page
    {
        $page = Page::factory()->create(['slug' => $slug]);

        foreach ($translations as $locale => $title) {
            $page->translations()->create([
                'locale' => $locale,
                'title' => $title,
            ]);
        }

        return $page->fresh(['translations']);
    }

    /** @return list<int> */
    private function sortedPageIds(): array
    {
        $method = new \ReflectionMethod(PageResource::class, 'sortByLocalizedTitle');
        $method->setAccessible(true);

        return $method
            ->invoke(null, PageResource::getEloquentQuery(), 'asc')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function searchedPageIds(string $search): array
    {
        $method = new \ReflectionMethod(PageResource::class, 'searchByLocalizedTitle');
        $method->setAccessible(true);

        return $method
            ->invoke(null, PageResource::getEloquentQuery(), $search)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function createAdministrator(): User
    {
        return User::factory()->create([
            'role_slug' => 'super_admin',
            'is_locked' => false,
        ]);
    }
}
