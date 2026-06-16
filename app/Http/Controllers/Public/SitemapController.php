<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves sitemap.xml and robots.txt for search engine crawlers.
 */
final class SitemapController extends Controller
{
    public function __construct(
        private readonly SitemapServiceInterface $sitemapService,
    ) {}

    /**
     * Return the XML sitemap with correct Content-Type.
     */
    public function sitemap(): Response
    {
        return new Response(
            $this->sitemapService->renderXml(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }

    /**
     * Return robots.txt with environment-aware noindex directives.
     */
    public function robots(Request $request): Response
    {
        $isProduction = $this->detectProduction();

        $sitemapUrl = url('/sitemap.xml');

        if ($isProduction) {
            $content = implode("\n", [
                'User-agent: *',
                'Allow: /',
                '',
                'Sitemap: ' . $sitemapUrl,
            ]);
        } else {
            $content = implode("\n", [
                'User-agent: *',
                'Disallow: /',
                '',
                'Sitemap: ' . $sitemapUrl,
            ]);
        }

        return new Response(
            $content,
            200,
            ['Content-Type' => 'text/plain'],
        );
    }

    /**
     * Detect whether the application is running in production.
     * Defaults to restrictive (non-production) on detection failure.
     */
    private function detectProduction(): bool
    {
        try {
            return app()->environment('production');
        } catch (\Throwable) {
            return false;
        }
    }
}
