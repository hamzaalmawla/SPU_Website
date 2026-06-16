<?php

declare(strict_types=1);

namespace App\Services\Homepage;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\DTOs\Homepage\HomepageSectionDataDTO;
use App\DTOs\Shared\ValidationMessageDTO;
use App\DTOs\Shared\ValidationResultDTO;
use App\Support\HomepagePayloadMapper;
use App\Support\UrlSanitizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Handles all validation rules for homepage section payloads.
 *
 * Extracted from HomepageSectionService to keep each class focused on a single responsibility.
 */
final class HomepageSectionValidator
{
    public function validateSectionPayload(string $key, HomepageSectionDataDTO $payload, string $locale): ValidationResultDTO
    {
        $this->assertApprovedKey($key);

        $normalizedPayload = HomepagePayloadMapper::sectionDataToArray($payload);
        $validator = Validator::make($normalizedPayload, $this->rulesForSection($key));
        $this->applyConditionalRules($validator, $key, $normalizedPayload);

        if (! in_array($locale, ['ar', 'en'], true)) {
            $validator->errors()->add('locale', 'The locale must be either ar or en.');
        }

        if (! $validator->fails()) {
            return new ValidationResultDTO(isValid: true);
        }

        $errors = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[] = new ValidationMessageDTO(
                field: $field,
                messages: array_values(array_filter($messages, static fn (mixed $message): bool => is_string($message))),
            );
        }

        return new ValidationResultDTO(isValid: false, errors: $errors);
    }

    public function assertApprovedKey(string $key): void
    {
        if (! in_array($key, HomepageSectionServiceInterface::SECTION_KEYS, true)) {
            throw new \InvalidArgumentException('Unknown homepage section key: '.$key);
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function hasExactApprovedKeySet(array $keys): bool
    {
        sort($keys);
        $approvedKeys = HomepageSectionServiceInterface::SECTION_KEYS;
        sort($approvedKeys);

        return $keys === $approvedKeys;
    }

    // ──────────────────────────────────────────────
    //  Validation rules
    // ──────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rulesForSection(string $key): array
    {
        $rules = match ($key) {
            'hero' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['required', 'string', 'max:500'],
                'backgroundImageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'videoUrl' => ['nullable', 'string', $this->assetPathRule()],
                'badge' => ['nullable', 'string', 'max:120'],
                'content.images' => ['nullable', 'array', 'min:1'],
                'content.images.*' => ['required', 'string', $this->assetPathRule()],
                'content.overlay' => ['nullable', 'array'],
                'content.alignment' => ['nullable', 'array'],
            ],
            'hero_stats', 'bottom_stats' => [
                'title' => ['required', 'string', 'max:255'],
                'stats' => ['required', 'array', 'min:4'],
                'stats.*.value' => ['required', 'string', 'max:100'],
                'stats.*.label' => ['required', 'string', 'max:255'],
                'stats.*.prefix' => ['nullable', 'string', 'max:50'],
                'stats.*.suffix' => ['nullable', 'string', 'max:50'],
                'stats.*.icon' => ['nullable', 'string', 'max:120'],
                'stats.*.helperText' => ['nullable', 'string', 'max:255'],
                'stats.*.url' => ['nullable', 'string', $this->linkRule()],
                'stats.*.sortOrder' => ['nullable', 'integer', 'min:1'],
            ],
            'academic_faculties' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:500'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.summary' => ['nullable', 'string', 'max:500'],
                'items.*.imageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'items.*.icon' => ['nullable', 'string', 'max:120'],
                'items.*.accent' => ['nullable', 'string', 'max:120'],
                'items.*.metric' => ['nullable', 'string', 'max:120'],
                'items.*.action' => ['required', 'array'],
                'items.*.action.label' => ['required', 'string', 'max:255'],
                'items.*.action.url' => ['required', 'string', $this->linkRule()],
            ],
            'achievements_highlights' => [
                'title' => ['required', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:500'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.summary' => ['required', 'string', 'max:500'],
                'items.*.icon' => ['nullable', 'string', 'max:120'],
                'items.*.metric' => ['nullable', 'string', 'max:120'],
                'items.*.dateLabel' => ['nullable', 'string', 'max:120'],
                'items.*.action' => ['required', 'array'],
                'items.*.action.label' => ['required', 'string', 'max:255'],
                'items.*.action.url' => ['required', 'string', $this->linkRule()],
            ],
            'choose_your_path' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.icon' => ['nullable', 'string', $this->assetPathRule()],
                'items.*.links' => ['nullable', 'array'],
                'items.*.action' => ['nullable', 'array'],
                'items.*.action.label' => ['nullable', 'string', 'max:255'],
                'items.*.action.url' => ['nullable', 'string', $this->linkRule()],
            ],
            'university_news' => [
                'title' => ['required', 'string', 'max:255'],
                'articles' => ['required', 'array', 'min:1'],
                'articles.*.imageUrl' => ['required', 'string', $this->assetPathRule()],
                'articles.*.title' => ['required', 'string', 'max:255'],
                'articles.*.excerpt' => ['nullable', 'string', 'max:500'],
                'articles.*.publishedAt' => ['required', 'date'],
                'articles.*.categoryLabel' => ['required', 'string', 'max:120'],
                'articles.*.badgeTag' => ['nullable', 'string', 'max:120'],
                'articles.*.url' => ['required', 'string', $this->linkRule()],
                'content.selectionMode' => ['nullable', Rule::in(['manual', 'fallback'])],
            ],
            'research_studies' => [
                'title' => ['required', 'string', 'max:255'],
                'researchItems' => ['required', 'array', 'min:1'],
                'researchItems.*.imageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'researchItems.*.title' => ['required', 'string', 'max:255'],
                'researchItems.*.summary' => ['nullable', 'string', 'max:500'],
                'researchItems.*.publishedAt' => ['nullable', 'date'],
                'researchItems.*.categoryLabel' => ['required', 'string', 'max:120'],
                'researchItems.*.authors' => ['nullable', 'array'],
                'researchItems.*.authors.*' => ['required', 'string', 'max:120'],
                'researchItems.*.url' => ['required', 'string', $this->linkRule()],
                'content.selectionMode' => ['nullable', Rule::in(['manual', 'fallback'])],
            ],
            'events_activities' => [
                'title' => ['required', 'string', 'max:255'],
                'events' => ['required', 'array', 'min:1'],
                'events.*.imageUrl' => ['nullable', 'string', $this->assetPathRule()],
                'events.*.title' => ['required', 'string', 'max:255'],
                'events.*.startsAt' => ['required', 'date'],
                'events.*.timeLabel' => ['nullable', 'string', 'max:120'],
                'events.*.location' => ['nullable', 'string', 'max:255'],
                'events.*.summary' => ['nullable', 'string', 'max:500'],
                'events.*.url' => ['required', 'string', $this->linkRule()],
                'content.calendarHighlights' => ['nullable', 'array'],
                'content.mobileConfig' => ['nullable', 'array'],
            ],
            'medical_facilities_services' => [
                'title' => ['required', 'string', 'max:255'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.summary' => ['nullable', 'string', 'max:500'],
                'items.*.imageUrl' => ['required', 'string', $this->assetPathRule()],
                'items.*.typeTag' => ['nullable', 'string', 'max:120'],
                'items.*.action' => ['nullable', 'array'],
                'items.*.action.label' => ['nullable', 'string', 'max:255'],
                'items.*.action.url' => ['nullable', 'string', $this->linkRule()],
            ],
            'footer' => [
                'footerColumns' => ['required', 'array', 'min:1'],
                'footerColumns.*.title' => ['required', 'string', 'max:255'],
                'footerColumns.*.links' => ['required', 'array', 'min:1'],
                'footerColumns.*.links.*.label' => ['required', 'string', 'max:255'],
                'footerColumns.*.links.*.url' => ['required', 'string', $this->linkRule()],
                'contactLinks' => ['required', 'array', 'min:1'],
                'contactLinks.*.label' => ['required', 'string', 'max:255'],
                'contactLinks.*.value' => ['required', 'string', 'max:255'],
                'socialLinks' => ['required', 'array', 'min:1'],
                'socialLinks.*.platform' => ['required', 'string', 'max:120'],
                'socialLinks.*.url' => ['required', 'string', $this->linkRule()],
                'content.brandBlock' => ['required', 'array'],
                'content.brandBlock.title' => ['required', 'string', 'max:255'],
                'content.brandBlock.logoUrl' => ['nullable', 'string', $this->assetPathRule()],
                'content.contactBlock' => ['nullable', 'array'],
                'content.contactBlock.title' => ['nullable', 'string', 'max:255'],
                'content.contactBlock.address' => ['nullable', 'string', 'max:500'],
                'content.contactBlock.phone' => ['nullable', 'string', 'max:120'],
                'content.contactBlock.email' => ['nullable', 'string', 'max:255'],
                'content.mapEmbed' => ['nullable', 'array'],
                'content.legalLinks' => ['required', 'array', 'min:1'],
                'content.legalLinks.*.label' => ['required', 'string', 'max:255'],
                'content.legalLinks.*.url' => ['required', 'string', $this->linkRule()],
                'content.copyrightText' => ['required', 'string', 'max:255'],
                'content.emergencyNotice' => ['nullable', 'array'],
            ],
            default => [],
        };

        $this->addActionRules($rules, 'primaryAction', $key === 'hero');
        $this->addActionRules($rules, 'secondaryAction', $key === 'hero');
        $this->addActionRules($rules, 'sectionAction', in_array($key, ['university_news', 'research_studies'], true));

        return $rules;
    }

    /**
     * @param  array<string, array<int, mixed>>  $rules
     */
    private function addActionRules(array &$rules, string $key, bool $required): void
    {
        $rules[$key] = [$required ? 'required' : 'nullable', 'array'];
        $rules[$key.'.label'] = [$required ? 'required' : 'nullable', 'string', 'max:255'];
        $rules[$key.'.url'] = [$required ? 'required' : 'nullable', 'string', $this->linkRule()];
        $rules[$key.'.target'] = ['nullable', 'string', 'max:32'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyConditionalRules(\Illuminate\Validation\Validator $validator, string $key, array $payload): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($key, $payload): void {
            if ($key === 'hero') {
                $images = [];
                $contentImages = $payload['content']['images'] ?? [];

                if (is_array($contentImages)) {
                    $images = array_values(array_filter($contentImages, static fn (mixed $image): bool => is_string($image) && $image !== ''));
                }

                if (! is_string($payload['backgroundImageUrl'] ?? null) && $images === []) {
                    $validator->errors()->add('backgroundImageUrl', 'The hero section must include a background image or at least one carousel image.');
                }
            }

            if (in_array($key, ['academic_faculties', 'medical_facilities_services'], true)) {
                foreach ($this->listOfArrays($payload['items'] ?? []) as $index => $item) {
                    if (! is_string($item['imageUrl'] ?? null) && ! is_string($item['icon'] ?? null)) {
                        $validator->errors()->add('items.'.$index, 'Each item must include an imageUrl or icon.');
                    }
                }
            }

            if ($key === 'footer') {
                $contactBlock = is_array($payload['content']['contactBlock'] ?? null) ? $payload['content']['contactBlock'] : [];

                if ($contactBlock !== [] &&
                    ! is_string($contactBlock['address'] ?? null)
                    && ! is_string($contactBlock['phone'] ?? null)
                    && ! is_string($contactBlock['email'] ?? null)
                ) {
                    $validator->errors()->add('content.contactBlock', 'The contact block must include address, phone, or email.');
                }
            }
        });
    }

    private function linkRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || UrlSanitizer::sanitize($value) === null) {
                $fail('The '.$attribute.' field must be a valid internal or absolute URL.');
            }
        };
    }

    private function assetPathRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || UrlSanitizer::sanitize($value, ['http', 'https'], true) === null) {
                $fail('The '.$attribute.' field must be an internal asset path or absolute URL.');
            }
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listOfArrays(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }
}
