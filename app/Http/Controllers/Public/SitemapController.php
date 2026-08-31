<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Seo\SitemapServiceInterface;
use App\Http\Controllers\Controller;
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
     * Return the sitemap index.
     *
     * In production `public/sitemap.xml` is pre-generated and the web server
     * answers it without entering PHP; this action is the fallback for when
     * that file has not been written yet.
     */
    public function sitemap(): Response
    {
        return $this->xml($this->sitemapService->renderIndexXml());
    }

    /**
     * Return one child sitemap. Fallback for a missing pre-generated file.
     */
    public function section(string $section): Response
    {
        $xml = $this->sitemapService->renderSectionXml($section);

        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    private function xml(string $body): Response
    {
        return new Response($body, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Return robots.txt with environment-aware noindex directives.
     */
    public function robots(): Response
    {
        $isProduction = $this->detectProduction();

        $sitemapUrl = rtrim((string) config('edge.canonical_url'), '/').'/sitemap.xml';

        if ($isProduction) {
            $content = implode("\n", [
                'User-agent: *',
                'Allow: /',
                // Search results are noindex, but that only stops them being
                // listed - a crawler still fetches every one it finds. The
                // route deliberately bypasses the page cache, so each fetch
                // renders the full shell and occupies one of five PHP workers.
                'Disallow: /*/search',
                '',
                'Sitemap: '.$sitemapUrl,
            ]);
        } else {
            $content = implode("\n", [
                'User-agent: *',
                'Disallow: /',
                '',
                'Sitemap: '.$sitemapUrl,
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
