<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

class HtmlSanitizer
{
    public function __construct(
        private readonly TextCleaner $textCleaner,
    ) {}

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $sanitized = preg_replace('/<!--.*?-->/su', '', $html) ?? $html;
        $sanitized = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s?(class|style|lang|width|height|align|valign|dir|xmlns(:\w+)?)="[^"]*"/iu', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/<(\/)?(o:p|span|font)([^>]*)>/iu', '<$1div$3>', $sanitized) ?? $sanitized;
        $sanitized = preg_replace_callback(
            '/<(a|img)\b([^>]*)>/iu',
            fn (array $matches): string => $this->stripUnsafeAttributes($matches[1], $matches[2]),
            $sanitized,
        ) ?? $sanitized;
        $sanitized = strip_tags($sanitized, '<p><br><ul><ol><li><strong><em><b><i><a><h2><h3><h4><blockquote><div>');

        return $this->textCleaner->clean(html_entity_decode($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function hasUnsafeLinks(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        preg_match_all('/(?:href|src)="([^"]+)"/iu', $value, $matches);

        foreach ($matches[1] ?? [] as $url) {
            foreach ((array) config('old_database.unsafe_url_patterns', []) as $pattern) {
                if (preg_match($pattern, trim((string) $url)) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function stripUnsafeAttributes(string $tag, string $attributes): string
    {
        preg_match_all('/(href|src|alt|title)="([^"]*)"/iu', $attributes, $matches, PREG_SET_ORDER);

        $safeAttributes = [];

        foreach ($matches as $match) {
            [$full, $name, $value] = $match;

            if (in_array($name, ['href', 'src'], true) && $this->hasUnsafeLinks($full)) {
                continue;
            }

            $safeAttributes[] = sprintf('%s="%s"', strtolower($name), e($value));
        }

        return '<'.$tag.($safeAttributes !== [] ? ' '.implode(' ', $safeAttributes) : '').'>';
    }
}
