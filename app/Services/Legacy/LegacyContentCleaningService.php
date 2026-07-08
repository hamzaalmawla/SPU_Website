<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use App\DTOs\Legacy\LegacyCleaningDecisionDTO;
use App\Support\LegacyImport\DateNormalizer;
use App\Support\LegacyImport\EmailValidator;
use App\Support\LegacyImport\HtmlSanitizer;
use App\Support\LegacyImport\LocaleFilter;
use App\Support\LegacyImport\TextCleaner;
use App\Support\UrlSanitizer;
use DateTimeInterface;

final class LegacyContentCleaningService implements LegacyContentCleaningServiceInterface
{
    private const DECISION_CLEANED = 'cleaned';

    private const DECISION_QUARANTINE = 'quarantine';

    private const DECISION_REJECTED = 'rejected';

    public function __construct(
        private readonly TextCleaner $textCleaner,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly EmailValidator $emailValidator,
        private readonly DateNormalizer $dateNormalizer,
        private readonly LocaleFilter $localeFilter,
    ) {}

    public function cleanText(?string $value, string $field = 'text', bool $required = false): LegacyCleaningDecisionDTO
    {
        $cleaned = $this->textCleaner->clean($value);
        $issues = [];
        $messages = [];

        if ($value !== null && $cleaned !== $value) {
            $issues[] = 'text_normalized';
            $messages[] = 'Whitespace or invisible characters were normalized.';
        }

        return $this->resultForPossiblyMissingValue($field, $value, $cleaned, $required, $issues, $messages);
    }

    public function cleanHtml(?string $html, string $field = 'body', bool $required = false): LegacyCleaningDecisionDTO
    {
        $cleaned = $this->htmlSanitizer->sanitize($html);
        $issues = [];
        $messages = [];
        $quarantine = false;

        if ($this->containsWordHtml($html)) {
            $issues[] = 'word_html_cleaned';
            $messages[] = 'Word/Office HTML markup was removed or normalized.';
        }

        if ($this->containsInlineStyleOrClass($html)) {
            $issues[] = 'inline_formatting_cleaned';
            $messages[] = 'Inline style or class attributes were removed.';
        }

        if ($this->containsScriptOrUnsafeUrl($html)) {
            $issues[] = 'unsafe_html';
            $messages[] = 'Unsafe script or URL content was detected.';
            $quarantine = true;
        }

        if ($this->containsBase64Image($html)) {
            $issues[] = 'base64_inline_image';
            $messages[] = 'Inline base64 image needs extraction or editorial review.';
            $quarantine = true;
        }

        if ($this->containsSpamLink($html)) {
            $issues[] = 'spam_link';
            $messages[] = 'Potential spam link was detected.';
            $quarantine = true;
        }

        if ($html !== null && $cleaned !== $html && ! in_array('unsafe_html', $issues, true)) {
            $issues[] = 'html_sanitized';
            $messages[] = 'HTML was sanitized safely.';
        }

        $missingResult = $this->resultForPossiblyMissingValue($field, $html, $cleaned, $required, $issues, $messages);

        if ($quarantine && $missingResult->decision !== self::DECISION_QUARANTINE) {
            return $this->decision($field, self::DECISION_QUARANTINE, $html, $cleaned, false, $issues, $messages);
        }

        return $missingResult;
    }

    public function cleanEmail(?string $email, string $field = 'email', bool $required = false): LegacyCleaningDecisionDTO
    {
        $original = $email;
        $normalized = $this->emailValidator->normalize($email);
        $issues = [];
        $messages = [];

        if ($email !== null && trim($email) !== '' && $normalized === null) {
            $issues[] = 'invalid_email';
            $messages[] = $required
                ? 'Required operational email is invalid and needs review.'
                : 'Invalid non-critical email was nulled.';

            return $this->decision(
                $field,
                $required ? self::DECISION_QUARANTINE : self::DECISION_CLEANED,
                $original,
                null,
                ! $required,
                $issues,
                $messages,
            );
        }

        if ($original !== null && $normalized !== $original) {
            $issues[] = 'email_normalized';
            $messages[] = 'Email was lowercased and trimmed.';
        }

        return $this->resultForPossiblyMissingValue($field, $original, $normalized, $required, $issues, $messages);
    }

    public function cleanDate(mixed $value, string $field = 'date'): LegacyCleaningDecisionDTO
    {
        $original = $this->stringify($value);
        $normalized = $this->dateNormalizer->normalize($value);

        if ($normalized !== null) {
            return $this->decision($field, self::DECISION_CLEANED, $original, $normalized->toDateString(), true, [], []);
        }

        if ($original === null || trim($original) === '' || in_array(trim($original), (array) config('old_database.fake_dates', []), true)) {
            return $this->decision(
                $field,
                self::DECISION_CLEANED,
                $original,
                null,
                true,
                ['fake_date_nullified'],
                ['Empty or fake legacy date was normalized to null.'],
            );
        }

        return $this->decision(
            $field,
            self::DECISION_QUARANTINE,
            $original,
            null,
            false,
            ['invalid_date'],
            ['Unparseable legacy date needs review.'],
        );
    }

    public function cleanLocale(?string $locale, string $field = 'locale'): LegacyCleaningDecisionDTO
    {
        $normalized = $this->localeFilter->normalize($locale);

        if ($normalized !== null && in_array($normalized, (array) config('old_database.allowed_locales', []), true)) {
            $issues = $locale !== $normalized ? ['locale_normalized'] : [];
            $messages = $locale !== $normalized ? ['Legacy locale label was normalized.'] : [];

            return $this->decision($field, self::DECISION_CLEANED, $locale, $normalized, true, $issues, $messages);
        }

        return $this->decision(
            $field,
            self::DECISION_QUARANTINE,
            $locale,
            null,
            false,
            ['unsupported_locale'],
            ['Unsupported locale must be parked outside public CMS tables.'],
        );
    }

    public function cleanUrl(?string $url, string $field = 'url', bool $required = false, bool $allowRelative = true): LegacyCleaningDecisionDTO
    {
        $cleaned = UrlSanitizer::sanitize($url, ['http', 'https', 'mailto', 'tel'], $allowRelative);
        $issues = [];
        $messages = [];

        if ($this->containsSpamLink($url)) {
            return $this->decision(
                $field,
                self::DECISION_QUARANTINE,
                $url,
                $cleaned,
                false,
                ['spam_link'],
                ['Potential spam URL needs review.'],
            );
        }

        if ($url !== null && trim($url) !== '' && $cleaned === null) {
            $issues[] = 'unsafe_url';
            $messages[] = 'URL failed scheme or control-character validation.';

            return $this->decision($field, self::DECISION_QUARANTINE, $url, null, false, $issues, $messages);
        }

        if ($url !== null && $cleaned !== $url) {
            $issues[] = 'url_normalized';
            $messages[] = 'URL was trimmed or normalized.';
        }

        return $this->resultForPossiblyMissingValue($field, $url, $cleaned, $required, $issues, $messages);
    }

    /**
     * @param array<int, string> $issues
     * @param array<int, string> $messages
     */
    private function resultForPossiblyMissingValue(string $field, ?string $original, ?string $cleaned, bool $required, array $issues, array $messages): LegacyCleaningDecisionDTO
    {
        if ($required && ($cleaned === null || $cleaned === '')) {
            $issues[] = 'missing_required_value';
            $messages[] = 'Required legacy value is missing after cleaning.';

            return $this->decision($field, self::DECISION_QUARANTINE, $original, $cleaned, false, $issues, $messages);
        }

        return $this->decision($field, self::DECISION_CLEANED, $original, $cleaned, true, $issues, $messages);
    }

    /**
     * @param array<int, string> $issueCodes
     * @param array<int, string> $messages
     */
    private function decision(string $field, string $decision, ?string $originalValue, ?string $cleanedValue, bool $canImportPublicly, array $issueCodes, array $messages): LegacyCleaningDecisionDTO
    {
        return new LegacyCleaningDecisionDTO(
            field: $field,
            decision: $decision,
            originalValue: $originalValue,
            cleanedValue: $cleanedValue,
            canImportPublicly: $canImportPublicly,
            issueCodes: array_values(array_unique($issueCodes)),
            messages: array_values(array_unique($messages)),
        );
    }

    private function stringify(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }

    private function containsWordHtml(?string $html): bool
    {
        return is_string($html) && preg_match('/<(?:o:p|w:[^>]+)|class="?Mso|mso-/iu', $html) === 1;
    }

    private function containsInlineStyleOrClass(?string $html): bool
    {
        return is_string($html) && preg_match('/\s(?:style|class)\s*=/iu', $html) === 1;
    }

    private function containsScriptOrUnsafeUrl(?string $html): bool
    {
        if (! is_string($html)) {
            return false;
        }

        if (preg_match('/<\s*script\b|on\w+\s*=|(?:href|src)\s*=\s*["\']?\s*(?:javascript:|vbscript:|data:text\/html)/iu', $html) === 1) {
            return true;
        }

        return $this->htmlSanitizer->hasUnsafeLinks($html);
    }

    private function containsBase64Image(?string $html): bool
    {
        return is_string($html) && preg_match('/<img\b[^>]*src\s*=\s*["\']data:image\//iu', $html) === 1;
    }

    private function containsSpamLink(?string $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        foreach ((array) config('old_database.spam_url_patterns', []) as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
