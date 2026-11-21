<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Where a page's form lives. Editors pick one of the forms built under Forms
 * (internal), or give the address of a form hosted elsewhere, such as a Google
 * or Microsoft form (external); the page's button then opens that address.
 *
 * Stored next to the picked form as "<prefix>Source" and "<prefix>Url", e.g.
 * formSource/formUrl or applicationFormSource/applicationFormUrl.
 */
final class FormLink
{
    public const INTERNAL = 'internal';

    public const EXTERNAL = 'external';

    public const SOURCES = [self::INTERNAL, self::EXTERNAL];

    public static function source(mixed $value): string
    {
        return $value === self::EXTERNAL ? self::EXTERNAL : self::INTERNAL;
    }

    /** A form address that is safe to send visitors to, or null. */
    public static function sanitizeUrl(mixed $url): ?string
    {
        return ExternalSignupUrl::sanitize($url);
    }

    /**
     * The external form address a payload points to, or null when it uses one
     * of our own forms (or the address is missing or unsafe).
     *
     * @param  array<string, mixed>  $data
     */
    public static function externalUrl(array $data, string $prefix = 'form'): ?string
    {
        if (self::source($data[$prefix.'Source'] ?? null) !== self::EXTERNAL) {
            return null;
        }

        return self::sanitizeUrl($data[$prefix.'Url'] ?? null);
    }

    /**
     * The stored pair for a payload: the source, and the address only when the
     * form is external.
     *
     * @return array{source: string, url: string|null}
     */
    public static function normalize(mixed $source, mixed $url): array
    {
        $source = self::source($source);

        return [
            'source' => $source,
            'url' => $source === self::EXTERNAL ? (is_string($url) && trim($url) !== '' ? trim($url) : null) : null,
        ];
    }
}
