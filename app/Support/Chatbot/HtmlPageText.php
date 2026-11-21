<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reduces a rendered public page to the text of its main content, with
 * Markdown headings and list items, and collects its in-site links.
 *
 * Navigation, forms, buttons, scripts, hidden parts and the assistant's own
 * widget are dropped, so only what a visitor reads on the page remains.
 */
final class HtmlPageText
{
    private const DROP = ['script', 'style', 'noscript', 'template', 'svg', 'form', 'button', 'nav', 'iframe', 'select', 'input', 'textarea', 'dialog'];

    private const BLOCKS = ['p', 'div', 'section', 'article', 'header', 'footer', 'aside', 'main', 'li', 'ul', 'ol', 'table', 'tr', 'dl', 'dt', 'dd', 'blockquote', 'figure', 'figcaption', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /**
     * @return array{title: string, text: string, links: list<string>}
     */
    public static function parse(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $main = $xpath->query('//main[@id="main-content"]')?->item(0) ?? $xpath->query('//main')?->item(0) ?? $xpath->query('//body')?->item(0);
        if (! $main instanceof DOMElement) {
            return ['title' => '', 'text' => '', 'links' => []];
        }

        $links = [];
        foreach ($xpath->query('.//a[@href]', $main) ?: [] as $anchor) {
            if ($anchor instanceof DOMElement) {
                $links[] = $anchor->getAttribute('href');
            }
        }

        $removals = [];
        foreach ($xpath->query('.//*', $main) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $hidden = $node->hasAttribute('hidden') || $node->getAttribute('aria-hidden') === 'true'
                || $node->hasAttribute('data-chatbot') || str_contains(' '.$node->getAttribute('class').' ', ' sr-only ')
                || str_contains($node->getAttribute('class'), 'breadcrumb');
            if ($hidden || in_array(strtolower($node->nodeName), self::DROP, true)) {
                $removals[] = $node;
            }
        }
        foreach ($removals as $node) {
            $node->parentNode?->removeChild($node);
        }

        $h1 = trim((string) ($xpath->query('.//h1', $main)?->item(0)?->textContent ?? ''));
        $title = $h1 !== '' ? $h1 : trim((string) preg_replace('/\s*[|–—-]\s*[^|–—-]*$/u', '', (string) ($xpath->query('//title')?->item(0)?->textContent ?? '')));

        $text = self::text($main);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return [
            'title' => (string) preg_replace('/\s+/u', ' ', $title),
            'text' => DocumentTextExtractor::clean(self::dedupeLines($text)),
            'links' => array_values(array_unique($links)),
        ];
    }

    private static function text(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return (string) preg_replace('/\s+/u', ' ', (string) $node->textContent);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->nodeName);
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= self::text($child);
        }
        $inner = trim($inner);

        if ($inner === '' && $name !== 'br') {
            return '';
        }

        return match (true) {
            in_array($name, ['h1', 'h2'], true) => "\n\n# ".$inner."\n",
            in_array($name, ['h3', 'h4', 'h5', 'h6'], true) => "\n\n## ".$inner."\n",
            $name === 'li' => "\n- ".$inner."\n",
            in_array($name, ['td', 'th'], true) => ' | '.$inner,
            $name === 'br' => "\n",
            in_array($name, self::BLOCKS, true) => "\n".$inner."\n",
            default => ' '.$inner.' ',
        };
    }

    /**
     * Cards and sliders often repeat the same line (a mobile and a desktop
     * copy); keep each non-heading line once.
     */
    private static function dedupeLines(string $text): string
    {
        $seen = [];
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $key = trim($line);
            if ($key !== '' && ! str_starts_with($key, '#') && mb_strlen($key) > 3) {
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
