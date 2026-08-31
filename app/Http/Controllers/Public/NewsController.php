<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\News\NewsServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\StructuredDataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NewsController extends Controller
{
    public function __construct(
        private readonly NewsServiceInterface $newsService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly StructuredDataServiceInterface $structuredDataService,
    ) {}

    public function index(Request $request, string $locale): View
    {
        $page = $this->newsService->getIndexPageContent($locale);

        $latest = $this->newsService->getLatestArticleCards($locale, 5, 'news');
        $announcements = $this->newsService->getLatestArticleCards($locale, 3, 'announcement');
        $featured = $this->newsService->getFeaturedArticles($locale, 1)->first() ?? $latest->first() ?? $announcements->first();

        return view('public.news.index', $this->sharedPayload($request, $locale, '/news', [
            'page' => $page,
            'featured' => $featured,
            'lastNews' => $latest,
            'announcements' => $announcements,
            'events' => $this->newsService->listNewsEvents($locale)->take(3)->values(),
            'pageTitle' => (string) ($page['pageTitle'] ?? ''),
            'pageDescription' => (string) ($page['pageDescription'] ?? ''),
            'seo' => $this->seo($locale, '/news', (string) ($page['pageTitle'] ?? ''), (string) ($page['pageDescription'] ?? ''), (string) ($page['heroImage'] ?? '')),
        ], $this->newsTrail($locale)));
    }

    public function articles(Request $request, string $locale): View
    {
        $page = $this->newsService->getArticlesPageContent($locale);

        return view('public.news.articles', $this->sharedPayload($request, $locale, '/news/articles', [
            'articles' => $this->newsService->listPublicArticles($locale, [
                'category' => $request->query('category'),
                'categoryType' => 'news',
                'search' => $request->query('search'),
            ], max(1, (int) $request->query('page', 1)), 9),
            'categories' => $this->newsService->getPublicCategories($locale, 'news'),
            'activeCategory' => is_string($request->query('category')) ? (string) $request->query('category') : null,
            'search' => is_string($request->query('search')) ? (string) $request->query('search') : '',
            'page' => $page,
            'pageTitle' => (string) $page['title'],
            'pageDescription' => (string) $page['summary'],
            'seo' => $this->seo($locale, '/news/articles', (string) $page['seoTitle'], (string) $page['seoDescription'], (string) $page['seoImage']),
        ], $this->newsTrail($locale, $locale === 'ar' ? 'المقالات' : 'Articles', '/news/articles')));
    }

    public function announcements(Request $request, string $locale): View
    {
        $page = $this->newsService->getAnnouncementsPageContent($locale);
        $featured = $this->newsService->getFeaturedArticles($locale, 1, 'announcement')->first();
        $featuredId = $featured?->id;

        return view('public.news.announcements', $this->sharedPayload($request, $locale, '/news/announcements', [
            'page' => $page,
            'featured' => $featured,
            'announcements' => $this->newsService->listPublicArticles($locale, [
                'category' => $request->query('category'),
                'categoryType' => 'announcement',
                'excludeId' => $featuredId,
            ], max(1, (int) $request->query('page', 1)), 4),
            'categories' => $this->newsService->getPublicCategories($locale, 'announcement'),
            'activeCategory' => is_string($request->query('category')) ? (string) $request->query('category') : null,
            'seo' => $this->seo(
                $locale,
                '/news/announcements',
                (string) $page['pageTitle'],
                (string) $page['pageDescription'],
                (string) $page['heroImage'],
            ),
        ], $this->newsTrail($locale, $locale === 'ar' ? 'الإعلانات' : 'Announcements', '/news/announcements')));
    }

    public function events(Request $request, string $locale): View
    {
        $page = $this->newsService->getEventsPageContent($locale);
        $calendar = $this->newsService->getNewsEventCalendar($locale, is_string($request->query('month')) ? $request->query('month') : null);

        return view('public.news.events-calendar', $this->sharedPayload($request, $locale, '/news/events', [
            'page' => $page,
            'month' => $calendar['month'],
            'monthLabel' => $calendar['monthLabel'],
            'previousMonth' => $calendar['previousMonth'],
            'nextMonth' => $calendar['nextMonth'],
            'events' => $calendar['events'],
            'days' => $calendar['days'],
            'seo' => $this->seo($locale, '/news/events', (string) $page['calendarTitle'], (string) $page['summary'], (string) $page['heroImage']),
        ]));
    }

    public function eventsList(Request $request, string $locale): View
    {
        $page = $this->newsService->getEventsPageContent($locale);
        $category = is_string($request->query('category')) && $request->query('category') !== '' ? $request->query('category') : null;

        return view('public.news.events-list', $this->sharedPayload($request, $locale, '/news/events-list', [
            'page' => $page,
            'activeCategory' => $category,
            'upcomingEvents' => $this->newsService->listNewsEvents($locale, false, $category),
            'pastEvents' => $this->newsService->listNewsEvents($locale, true),
            'seo' => $this->seo($locale, '/news/events-list', (string) $page['title'], (string) $page['summary'], (string) $page['heroImage']),
        ]));
    }

    public function eventRegistration(Request $request, string $locale): View
    {
        $page = $this->newsService->getEventsPageContent($locale);
        $eventId = is_string($request->query('event')) ? $request->query('event') : '';
        $event = $this->newsService->findNewsEvent($eventId, $locale, false);
        $event = $event?->isRegisterable === true ? $event : null;
        $switchPath = '/news/events-list/register'.($eventId !== '' ? '?event='.rawurlencode($eventId) : '');

        return view('public.news.event-registration', $this->sharedPayload($request, $locale, $switchPath, [
            'page' => $page,
            'event' => $event,
            'seo' => $this->seo($locale, '/news/events-list/register', (string) $page['registrationTitle'], (string) $page['registrationInfo'], null, 'noindex,follow'),
        ]));
    }

    public function pastEvent(Request $request, string $locale): View
    {
        $page = $this->newsService->getEventsPageContent($locale);
        $eventId = is_string($request->query('event')) ? $request->query('event') : '';
        $event = $this->newsService->findNewsEvent($eventId, $locale, true);
        $switchPath = '/news/events-list/past'.($eventId !== '' ? '?event='.rawurlencode($eventId) : '');

        return view('public.news.past-event', $this->sharedPayload($request, $locale, $switchPath, [
            'page' => $page,
            'event' => $event,
            'seo' => $this->seo($locale, '/news/events-list/past', $event?->title ?? (string) $page['notFoundTitle'], $event?->summary ?? (string) $page['notFoundText'], $event?->imageUrl),
        ]));
    }

    public function eventDetail(Request $request, string $locale, string $event): View
    {
        $page = $this->newsService->getEventsPageContent($locale);
        $newsEvent = $this->newsService->findNewsEvent($event, $locale);
        abort_if($newsEvent === null, 404);
        $path = '/news/events-list/'.rawurlencode($event);

        return view('public.news.event-detail', $this->sharedPayload($request, $locale, $path, [
            'page' => $page,
            'event' => $newsEvent,
            'seo' => $this->seo($locale, $path, $newsEvent->title, $newsEvent->summary, $newsEvent->imageUrl),
        ]));
    }

    public function gallery(Request $request, string $locale): View
    {
        $category = is_string($request->query('category')) && $request->query('category') !== '' ? $request->query('category') : null;
        $listing = $this->newsService->getGalleryListing($locale, $category, max(1, (int) $request->query('page', 1)), 8);
        $page = $listing['page'];

        return view('public.news.gallery', $this->sharedPayload($request, $locale, '/news/gallery', [
            'page' => $page,
            'featured' => $listing['featured'],
            'galleryItems' => $listing['items'],
            'activeCategory' => $category,
            'seo' => $this->seo($locale, '/news/gallery', (string) $page['title'], (string) $page['summary'], (string) $page['heroImage']),
        ], $this->newsTrail($locale, $locale === 'ar' ? 'معرض الصور' : 'Gallery', '/news/gallery')));
    }

    public function show(Request $request, string $locale, string $article): View
    {
        $newsArticle = $this->newsService->getPublicArticle($article, $locale);
        abort_if($newsArticle === null, 404);

        return view('public.news.show', $this->sharedPayload($request, $locale, '/news/'.$article, [
            'article' => $newsArticle,
            'relatedArticles' => $this->newsService->getRelatedArticleCards($article, $locale, 3),
            'adjacentArticles' => $this->newsService->getAdjacentArticleCards($article, $locale),
            'hideFooterSocials' => true,
            'seo' => $this->seo(
                $locale,
                '/news/'.$article,
                $newsArticle->metaTitle ?? $newsArticle->title,
                $newsArticle->metaDescription ?? $newsArticle->excerpt ?? $newsArticle->title,
                $newsArticle->ogImage,
                $newsArticle->robots,
            ),
        ], $this->newsTrail($locale, $newsArticle->title, '/news/'.$article)));
    }

    public function redirectLegacyArticle(Request $request, string $locale): RedirectResponse
    {
        $identifier = $request->query('id');
        abort_unless(is_string($identifier) && trim($identifier) !== '', 404);

        $article = $this->newsService->getPublicArticle(trim($identifier), $locale);
        abort_if($article === null, 404);

        return redirect($article->url, 301);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{name: string, url: string}>  $breadcrumbs  Crumbs after the implicit homepage crumb.
     * @return array<string, mixed>
     */
    private function sharedPayload(Request $request, string $locale, string $path, array $payload, array $breadcrumbs = []): array
    {
        $defaults = [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => $this->languageSwitchLinks($locale, $path),
            'isPreview' => false,
        ];

        if ($breadcrumbs !== []) {
            $defaults['structuredData'] = $this->structuredDataService->breadcrumbs($locale, $breadcrumbs)->data;
        }

        return array_merge($defaults, $payload);
    }

    /**
     * Build a breadcrumb trail rooted at the news section.
     *
     * @return array<int, array{name: string, url: string}>
     */
    private function newsTrail(string $locale, ?string $label = null, ?string $path = null): array
    {
        $trail = [[
            'name' => $locale === 'ar' ? 'الأخبار' : 'News',
            'url' => '/'.$locale.'/news',
        ]];

        if ($label !== null && $label !== '' && $path !== null && $path !== '') {
            $trail[] = ['name' => $label, 'url' => '/'.$locale.$path];
        }

        return $trail;
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
