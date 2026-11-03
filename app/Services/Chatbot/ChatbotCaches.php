<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

use App\Contracts\Shared\CacheServiceInterface;

/**
 * Cache keys shared by the assistant's services, and the one invalidation
 * that reaches outside them.
 *
 * The launcher is part of every public page's HTML, and those pages are
 * cached for an hour. When the widget appears or disappears (switched on or
 * off, key added or removed, first file indexed or last one removed) the
 * page cache is flushed the way a footer settings change flushes it. The
 * file store has no tags, so that falls back to a full flush, which is why it
 * happens only on those changes and not on every edit.
 */
final class ChatbotCaches
{
    public const WIDGET_PREFIX = 'chatbot.widget.v1.';

    public const KNOWLEDGE_VERSION = 'chatbot.knowledge.version';

    public static function widgetKey(string $locale): string
    {
        return self::WIDGET_PREFIX.$locale;
    }

    public static function widgetChanged(CacheServiceInterface $cache, bool $visibilityChanged): void
    {
        foreach (['ar', 'en'] as $locale) {
            $cache->forget(self::widgetKey($locale));
        }

        if ($visibilityChanged && ! $cache->flushTags(['public-pages', 'public-shell'])) {
            $cache->flushAll();
        }
    }
}
