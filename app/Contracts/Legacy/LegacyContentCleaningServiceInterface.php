<?php

declare(strict_types=1);

namespace App\Contracts\Legacy;

use App\DTOs\Legacy\LegacyCleaningDecisionDTO;

interface LegacyContentCleaningServiceInterface
{
    public function cleanText(?string $value, string $field = 'text', bool $required = false): LegacyCleaningDecisionDTO;

    public function cleanHtml(?string $html, string $field = 'body', bool $required = false): LegacyCleaningDecisionDTO;

    public function cleanEmail(?string $email, string $field = 'email', bool $required = false): LegacyCleaningDecisionDTO;

    public function cleanDate(mixed $value, string $field = 'date'): LegacyCleaningDecisionDTO;

    public function cleanLocale(?string $locale, string $field = 'locale'): LegacyCleaningDecisionDTO;

    public function cleanUrl(?string $url, string $field = 'url', bool $required = false, bool $allowRelative = true): LegacyCleaningDecisionDTO;
}
