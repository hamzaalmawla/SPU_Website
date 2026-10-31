<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
use App\Contracts\Seo\StructuredDataServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Navigation\LanguageSwitchLinkDTO;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AchievementController extends Controller
{
    public function __construct(
        private readonly AchievementServiceInterface $achievementService,
        private readonly NavigationServiceInterface $navigationService,
        private readonly SettingsServiceInterface $settingsService,
        private readonly SeoMetadataServiceInterface $seoMetadataService,
        private readonly StructuredDataServiceInterface $structuredDataService,
    ) {}

    public function __invoke(Request $request, string $locale): View
    {
        $categories = $request->query('categories', []);
        $archive = $this->achievementService->archive(
            $locale,
            is_array($categories) ? $categories : [],
            max(1, $request->integer('page', 1)),
        );

        if ($request->ajax()) {
            return view('public.achievements.partials.results', compact('archive', 'locale'));
        }

        $path = '/'.$locale.'/achievements';
        $title = __('public.achievements.title');
        $description = __('public.achievements.description');

        return view('public.achievements.index', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'archive' => $archive,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => [
                new LanguageSwitchLinkDTO('ar', 'AR', '/ar/achievements', $locale === 'ar'),
                new LanguageSwitchLinkDTO('en', 'EN', '/en/achievements', $locale === 'en'),
            ],
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => $path,
                'locale_paths' => ['ar' => '/ar/achievements', 'en' => '/en/achievements'],
                'title' => $title,
                'meta_description' => $description,
                'og_title' => $title,
                'og_description' => $description,
            ]),
            'isPreview' => false,
        ]);
    }

    public function show(Request $request, string $locale, string $achievement): View
    {
        $detail = $this->achievementService->find((int) $achievement, $locale);
        abort_if($detail === null, 404);

        $path = '/achievements/'.$detail->id;
        $description = $detail->summary ?? __('public.achievements.description');

        return view('public.achievements.show', [
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'achievement' => $detail,
            'navigation' => $this->navigationService->getFullNavigationPayload($locale, $request->path()),
            'settings' => $this->settingsService->getPublicSettings($locale),
            'languageSwitch' => [
                new LanguageSwitchLinkDTO('ar', 'AR', '/ar'.$path, $locale === 'ar'),
                new LanguageSwitchLinkDTO('en', 'EN', '/en'.$path, $locale === 'en'),
            ],
            'seo' => $this->seoMetadataService->buildFallback($locale, [
                'path' => '/'.$locale.$path,
                'locale_paths' => ['ar' => '/ar'.$path, 'en' => '/en'.$path],
                'title' => $detail->title,
                'meta_description' => $description,
                'og_title' => $detail->title,
                'og_description' => $description,
                'og_image' => $detail->image,
            ]),
            'structuredData' => $this->structuredDataService->breadcrumbs($locale, [
                ['name' => __('public.achievements.title'), 'url' => '/'.$locale.'/achievements'],
                ['name' => $detail->title, 'url' => '/'.$locale.$path],
            ])->data,
            'isPreview' => false,
        ]);
    }
}
