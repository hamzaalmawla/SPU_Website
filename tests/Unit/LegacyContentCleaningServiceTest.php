<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Legacy\LegacyContentCleaningServiceInterface;
use Tests\TestCase;

final class LegacyContentCleaningServiceTest extends TestCase
{
    private LegacyContentCleaningServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('old_database.allowed_locales', ['ar', 'en']);
        config()->set('old_database.fake_dates', ['0000-00-00', '0000-00-00 00:00:00', '1900-01-01']);
        config()->set('old_database.spam_url_patterns', ['/casino/i', '/\bviagra\b/i']);

        $this->service = app(LegacyContentCleaningServiceInterface::class);
    }

    public function test_text_is_auto_fixed_without_quarantine(): void
    {
        $decision = $this->service->cleanText("  Hello\u{200B}   world  ", 'title', true);

        $this->assertSame('cleaned', $decision->decision);
        $this->assertTrue($decision->canImportPublicly);
        $this->assertSame('Hello world', $decision->cleanedValue);
        $this->assertContains('text_normalized', $decision->issueCodes);
    }

    public function test_word_html_is_cleaned_not_rejected(): void
    {
        $decision = $this->service->cleanHtml('<p class="MsoNormal" style="color:red">Text</p><o:p>&nbsp;</o:p>', 'body', true);

        $this->assertSame('cleaned', $decision->decision);
        $this->assertTrue($decision->canImportPublicly);
        $this->assertSame('<p>Text</p><div> </div>', $decision->cleanedValue);
        $this->assertContains('word_html_cleaned', $decision->issueCodes);
        $this->assertContains('inline_formatting_cleaned', $decision->issueCodes);
    }

    public function test_unsafe_html_is_quarantined_with_cleaned_copy(): void
    {
        $decision = $this->service->cleanHtml('<p>Keep this <a href="javascript:alert(1)">link</a></p>', 'body', true);

        $this->assertSame('quarantine', $decision->decision);
        $this->assertFalse($decision->canImportPublicly);
        $this->assertSame('<p>Keep this <a>link</a></p>', $decision->cleanedValue);
        $this->assertContains('unsafe_html', $decision->issueCodes);
    }

    public function test_base64_images_are_quarantined_for_extraction(): void
    {
        $decision = $this->service->cleanHtml('<p>Photo</p><img src="data:image/png;base64,AAAA" alt="x">', 'body');

        $this->assertSame('quarantine', $decision->decision);
        $this->assertFalse($decision->canImportPublicly);
        $this->assertContains('base64_inline_image', $decision->issueCodes);
    }

    public function test_fake_dates_are_fixed_to_null(): void
    {
        $decision = $this->service->cleanDate('0000-00-00', 'published_at');

        $this->assertSame('cleaned', $decision->decision);
        $this->assertTrue($decision->canImportPublicly);
        $this->assertNull($decision->cleanedValue);
        $this->assertContains('fake_date_nullified', $decision->issueCodes);
    }

    public function test_invalid_required_email_is_quarantined_but_optional_email_is_nulled(): void
    {
        $required = $this->service->cleanEmail('not-an-email', 'contact_email', true);
        $optional = $this->service->cleanEmail('not-an-email', 'bio_email', false);

        $this->assertSame('quarantine', $required->decision);
        $this->assertFalse($required->canImportPublicly);
        $this->assertContains('invalid_email', $required->issueCodes);

        $this->assertSame('cleaned', $optional->decision);
        $this->assertTrue($optional->canImportPublicly);
        $this->assertNull($optional->cleanedValue);
    }

    public function test_unsupported_locale_is_quarantined_not_imported_publicly(): void
    {
        $decision = $this->service->cleanLocale('fr', 'locale');

        $this->assertSame('quarantine', $decision->decision);
        $this->assertFalse($decision->canImportPublicly);
        $this->assertNull($decision->cleanedValue);
        $this->assertContains('unsupported_locale', $decision->issueCodes);
    }

    public function test_spam_url_is_quarantined(): void
    {
        $decision = $this->service->cleanUrl('https://example.com/casino-offer', 'url');

        $this->assertSame('quarantine', $decision->decision);
        $this->assertFalse($decision->canImportPublicly);
        $this->assertContains('spam_link', $decision->issueCodes);
    }
}
