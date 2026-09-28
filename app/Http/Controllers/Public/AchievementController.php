<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Achievement\AchievementServiceInterface;
use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\SeoMetadataServiceInterface;
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
}
