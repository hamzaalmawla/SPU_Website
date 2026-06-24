<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class NewsController extends Controller
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
    ) {}

    public function index(Request $request, string $locale): View
    {
        $title = $locale === 'ar' ? 'الأخبار' : 'News';
        $description = $locale === 'ar'
            ? 'تابع آخر أخبار الجامعة السورية الخاصة وإعلاناتها.'
            : 'Follow the latest Syrian Private University news and announcements.';

        $latest = $this->newsService->getLatestArticleCards($locale, 5, 'news');
        $announcements = $this->newsService->getLatestArticleCards($locale, 3, 'announcement');
        $featured = $this->newsService->getFeaturedArticles($locale, 1)->first() ?? $latest->first() ?? $announcements->first();

        return view('public.news.index', $this->sharedPayload($request, $locale, '/news', [
            'featured' => $featured,
            'lastNews' => $latest,
            'announcements' => $announcements,
            'events' => $this->newsService->getLatestArticleCards($locale, 3),
            'pageTitle' => $title,
            'pageDescription' => $description,
            'seo' => $this->seo($locale, '/news', $title, $description),
        ]));
    }

    public function articles(Request $request, string $locale): View
    {
        $title = $locale === 'ar' ? 'قائمة الأخبار' : 'News Listing';
        $description = $locale === 'ar'
            ? 'تصفح أخبار الجامعة السورية الخاصة حسب التصنيف.'
            : 'Browse Syrian Private University news by category.';

        return view('public.news.articles', $this->sharedPayload($request, $locale, '/news/articles', [
            'articles' => $this->newsService->listPublicArticles($locale, [
                'category' => $request->query('category'),
                'search' => $request->query('search'),
            ], max(1, (int) $request->query('page', 1)), 9),
            'categories' => $this->newsService->getPublicCategories($locale),
            'activeCategory' => is_string($request->query('category')) ? (string) $request->query('category') : null,
            'search' => is_string($request->query('search')) ? (string) $request->query('search') : '',
            'pageTitle' => $title,
            'pageDescription' => $description,
            'seo' => $this->seo($locale, '/news/articles', $title, $description),
        ]));
    }

    public function show(Request $request, string $locale, string $article): View
    {
        $newsArticle = $this->newsService->getPublicArticle($article, $locale);
        abort_if($newsArticle === null, 404);

        return view('public.news.show', $this->sharedPayload($request, $locale, '/news/'.$article, [
            'article' => $newsArticle,
            'relatedArticles' => $this->newsService->getRelatedArticleCards($article, $locale, 3),
            'adjacentArticles' => $this->newsService->getAdjacentArticleCards($article, $locale),
            'seo' => $this->seo(
                $locale,
                '/news/'.$article,
                $newsArticle->metaTitle ?? $newsArticle->title,
                $newsArticle->metaDescription ?? $newsArticle->excerpt ?? $newsArticle->title,
                $newsArticle->ogImage,
                $newsArticle->robots,
            ),
        ]));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function sharedPayload(Request $request, string $locale, string $path, array $payload): array
    {
        return array_merge([
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $path),
            'isPreview' => false,
        ], $payload);
    }

    private function seo(string $locale, string $path, string $title, string $description, ?string $image = null, string $robots = 'index,follow'): mixed
    {
        return $this->seoMetadataService->buildFallback($locale, [
            'path' => '/'.$locale.$path,
            'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
            'title' => $title,
            'meta_description' => $description,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
            'robots' => $robots,
        ]);
    }

    /** @return array<int, LanguageSwitchLinkDTO> */
    private function languageSwitchLinks(string $locale, string $path): array
    {
        return [
            new LanguageSwitchLinkDTO('ar', 'AR', '/ar'.$path, $locale === 'ar'),
            new LanguageSwitchLinkDTO('en', 'EN', '/en'.$path, $locale === 'en'),
        ];
    }
}
