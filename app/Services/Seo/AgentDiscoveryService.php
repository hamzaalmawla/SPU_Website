<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Contracts\Navigation\NavigationServiceInterface;
use App\Contracts\Seo\AgentDiscoveryServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Navigation\MenuItemDTO;
use Throwable;

final class AgentDiscoveryService implements AgentDiscoveryServiceInterface
{
    private const CACHE_SECONDS = 3600;

    public function __construct(
        private readonly NavigationServiceInterface $navigation,
        private readonly SettingsServiceInterface $settings,
        private readonly CacheServiceInterface $cache,
    ) {}

    public function llmsTxt(): string
    {
        $text = $this->cache->remember('agents.llms-txt.v1', fn (): string => $this->buildLlmsTxt(), self::CACHE_SECONDS);

        return is_string($text) ? $text : $this->buildLlmsTxt();
    }

    public function ardManifest(): array
    {
        $base = $this->base();
        $publisher = $this->publisher();

        return [
            'specVersion' => '0.91',
            'publisher' => [
                'name' => (string) __('agents.name', [], 'en'),
                'url' => $base,
            ],
            'entries' => [
                [
                    'identifier' => "urn:air:{$publisher}:website:guide",
                    'displayName' => (string) __('agents.guide_name', [], 'en'),
                    'description' => (string) __('agents.guide_description', [], 'en'),
                    'type' => 'text/markdown',
                    'url' => $base.'/llms.txt',
                    'representativeQueries' => array_values((array) __('agents.guide_queries', [], 'en')),
                ],
                [
                    'identifier' => "urn:air:{$publisher}:website:sitemap",
                    'displayName' => (string) __('agents.sitemap_name', [], 'en'),
                    'description' => (string) __('agents.sitemap_description', [], 'en'),
                    'type' => 'application/xml',
                    'url' => $base.'/sitemap.xml',
                    'representativeQueries' => array_values((array) __('agents.sitemap_queries', [], 'en')),
                ],
            ],
        ];
    }

    private function buildLlmsTxt(): string
    {
        $base = $this->base();
        $lines = [
            '# '.__('agents.name', [], 'en').' — '.__('agents.name', [], 'ar'),
            '',
            '> '.__('agents.summary', [], 'en'),
            '',
            (string) __('agents.summary', [], 'ar'),
            '',
            (string) __('agents.languages', [], 'en'),
        ];

        foreach (['en', 'ar'] as $locale) {
            $items = $this->menu($locale);
            if ($items === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '## '.__('agents.sections_heading', [], $locale);
            $lines[] = '';
            foreach ($items as $item) {
                $lines[] = '- ['.$item['label'].']('.$item['url'].')';
                foreach ($item['children'] as $child) {
                    $lines[] = '  - ['.$child['label'].']('.$child['url'].')';
                }
            }
        }

        $contacts = $this->contacts();
        if ($contacts !== []) {
            $lines[] = '';
            $lines[] = '## '.__('agents.contact_heading', [], 'en');
            $lines[] = '';
            foreach ($contacts as $contact) {
                $lines[] = '- '.$contact;
            }
        }

        $lines[] = '';
        $lines[] = '## '.__('agents.machine_heading', [], 'en');
        $lines[] = '';
        $lines[] = '- ['.__('agents.sitemap_name', [], 'en').']('.$base.'/sitemap.xml)';
        $lines[] = '- ['.__('agents.ard_name', [], 'en').']('.$base.'/.well-known/ard.json)';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * The published header menu: top-level items and one level below them,
     * internal pages only, as absolute links.
     *
     * @return list<array{label: string, url: string, children: list<array{label: string, url: string}>}>
     */
    private function menu(string $locale): array
    {
        try {
            $tree = $this->navigation->getHeaderNavigation($locale);
        } catch (Throwable) {
            return [];
        }

        $items = [];
        foreach ($tree->items as $item) {
            $link = $this->link($item);
            $children = [];
            foreach ($item->children as $child) {
                $childLink = $this->link($child);
                if ($childLink !== null) {
                    $children[] = $childLink;
                }
            }

            if ($link !== null) {
                $items[] = $link + ['children' => $children];
            } elseif ($children !== []) {
                // A menu heading with no page of its own still groups its links.
                $items[] = ['label' => $this->clean($item->label), 'url' => $children[0]['url'], 'children' => array_slice($children, 1)];
            }
        }

        return $items;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    private function link(MenuItemDTO $item): ?array
    {
        $label = $this->clean($item->label);
        $url = trim((string) ($item->resolvedUrl ?? $item->url ?? ''));
        if (! $item->isEnabled || $label === '' || $url === '' || $url === '#') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            $url = $this->base().$url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || strcasecmp($host, (string) parse_url($this->base(), PHP_URL_HOST)) !== 0) {
            return null;
        }

        return ['label' => $label, 'url' => $url];
    }

    /**
     * @return list<string>
     */
    private function contacts(): array
    {
        try {
            $footer = $this->settings->getFooterSettings('en');
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter([
            $footer->phone !== null && $footer->phone !== '' ? __('agents.phone', [], 'en').': '.$footer->phone : null,
            $footer->email !== null && $footer->email !== '' ? __('agents.email', [], 'en').': '.$footer->email : null,
            $footer->address !== null && $footer->address !== '' ? __('agents.address', [], 'en').': '.$this->clean($footer->address) : null,
        ]));
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/[\[\]\r\n]+|\s{2,}/u', ' ', strip_tags($text)));
    }

    private function base(): string
    {
        return rtrim((string) (config('edge.canonical_url') ?: config('app.url')), '/');
    }

    /**
     * The registrable host, for the URN publisher (spu.edu.sy, not v2.spu.edu.sy).
     */
    private function publisher(): string
    {
        $host = strtolower((string) parse_url($this->base(), PHP_URL_HOST));
        $parts = array_values(array_filter(explode('.', $host)));

        return count($parts) > 3 ? implode('.', array_slice($parts, -3)) : ($host !== '' ? $host : 'spu.edu.sy');
    }
}
