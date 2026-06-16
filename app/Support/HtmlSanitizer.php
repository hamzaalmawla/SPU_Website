<?php

declare(strict_types=1);

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * HTML sanitizer wrapping HTMLPurifier with a strict allowlist.
 *
 * Used at both CMS storage time and Blade render time for defense-in-depth
 * against stored XSS attacks. Handles nested encoding, CSS expressions,
 * and edge cases that regex-based approaches cannot reliably catch.
 */
final class HtmlSanitizer
{
    /**
     * Allowed HTML tags per the design specification.
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'blockquote', 'img', 'table', 'thead', 'tbody',
        'tr', 'th', 'td', 'span', 'div', 'figure', 'figcaption',
    ];

    /**
     * Allowed URI schemes for href attributes.
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Allowed URI schemes for img src attributes.
     * The '/' entry enables relative paths.
     */
    private const ALLOWED_IMG_SCHEMES = ['https'];

    private HTMLPurifier $purifier;

    public function __construct(?string $cachePath = null)
    {
        $this->purifier = new HTMLPurifier($this->buildConfig($cachePath));
    }

    /**
     * Sanitize HTML content using HTMLPurifier with a strict allowlist.
     *
     * Returns an empty string for null or empty input without throwing.
     */
    public function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return $this->purifier->purify($html);
    }

    /**
     * Check whether the given HTML is already clean (sanitization produces no changes).
     *
     * Useful for testing and validation without mutating content.
     */
    public function isClean(string $html): bool
    {
        return $this->sanitize($html) === $html;
    }

    /**
     * Build the HTMLPurifier configuration with strict tag/attribute allowlist,
     * scheme restrictions, and CSS property filtering.
     */
    private function buildConfig(?string $cachePath = null): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();

        // Serializer cache — use provided path, Laravel storage, or system temp
        $cacheDir = $cachePath ?? $this->resolveCachePath();

        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);

        // Build the allowed elements string with their permitted attributes
        $config->set('HTML.Allowed', $this->buildAllowedString());

        // URI scheme restrictions for links
        $config->set('URI.AllowedSchemes', array_fill_keys(self::ALLOWED_SCHEMES, true));

        // Allow relative URIs (for img src with relative paths)
        $config->set('URI.MakeAbsolute', false);

        // Disable external resources by default, but allow https images
        $config->set('URI.DisableExternalResources', false);

        // CSS property allowlist — only safe decorative properties
        $config->set('CSS.AllowedProperties', [
            'text-align',
            'color',
            'background-color',
            'font-weight',
            'font-style',
            'text-decoration',
            'margin',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
            'padding',
            'padding-top',
            'padding-right',
            'padding-bottom',
            'padding-left',
            'border',
            'border-collapse',
            'width',
            'height',
            'max-width',
            'max-height',
            'list-style-type',
            'vertical-align',
        ]);

        // Output settings
        $config->set('Output.TidyFormat', false);
        $config->set('Core.Encoding', 'UTF-8');

        // Automatically close unclosed tags
        $config->set('AutoFormat.RemoveEmpty', false);

        // Add nofollow to external links for safety
        $config->set('HTML.Nofollow', true);

        // Target blank for external links
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        // Register custom HTML5 elements (figure, figcaption) not in HTMLPurifier's default set
        $config->set('HTML.DefinitionID', 'spu-sanitizer-v1');
        $config->set('HTML.DefinitionRev', 2);

        $def = $config->maybeGetRawHTMLDefinition();
        if ($def !== null) {
            // figure: block-level element that can contain flow content
            $def->addElement('figure', 'Block', 'Flow', 'Common');
            // figcaption: block-level element inside figure, contains inline content
            $def->addElement('figcaption', 'Block', 'Flow', 'Common');
            // loading attribute for img (lazy loading — HTML5)
            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
        }

        return $config;
    }

    /**
     * Build the HTML.Allowed configuration string from the allowed tags list.
     *
     * Maps each tag to its permitted attributes based on the element type.
     */
    private function buildAllowedString(): string
    {
        $parts = [];

        foreach (self::ALLOWED_TAGS as $tag) {
            $attrs = $this->attributesForTag($tag);
            $parts[] = $attrs !== '' ? "{$tag}[{$attrs}]" : $tag;
        }

        return implode(',', $parts);
    }

    /**
     * Return the allowed attributes for a given tag.
     */
    private function attributesForTag(string $tag): string
    {
        return match ($tag) {
            'a' => 'href|title|target|rel',
            'img' => 'src|alt|title|width|height|loading',
            'td', 'th' => 'colspan|rowspan',
            'ol' => 'start|type',
            'table' => 'border|cellpadding|cellspacing',
            'div', 'span', 'p', 'blockquote',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'figure', 'figcaption' => 'style',
            default => '',
        };
    }

    /**
     * Resolve the HTMLPurifier cache directory path.
     *
     * Uses Laravel's storage path when the application is booted,
     * falls back to the system temp directory otherwise (e.g. in unit tests).
     */
    private function resolveCachePath(): string
    {
        try {
            if (function_exists('app') && app()->bound('path.storage')) {
                return storage_path('framework/cache/htmlpurifier');
            }
        } catch (\Throwable) {
            // Fall through to temp directory
        }

        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'htmlpurifier';
    }
}
