<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Cms\CmsWorkflowServiceInterface;
use App\Contracts\Page\ContactPageServiceInterface;
use App\Contracts\Settings\SettingsServiceInterface;
use App\DTOs\Contact\ContactPageContentDTO;
use App\DTOs\Contact\ContactPageDTO;
use App\DTOs\Contact\ContactSubmissionDataDTO;
use App\DTOs\Settings\SettingsDTO;
use App\DTOs\Settings\SettingValueDTO;
use App\Models\Contact\ContactMessage;
use Illuminate\Support\Carbon;

final class ContactPageService implements ContactPageServiceInterface
{
    public function __construct(
        private readonly CmsWorkflowServiceInterface $cmsWorkflowService,
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function getPage(string $locale): ContactPageDTO
    {
        return $this->pageFromContent($locale, $this->getContent($locale));
    }

    /** @param array<string, mixed> $content */
    public function buildPreviewPage(string $locale, array $content): ContactPageDTO
    {
        return $this->pageFromContent($locale, $this->contentFromArray($content));
    }

    private function pageFromContent(string $locale, ContactPageContentDTO $content): ContactPageDTO
    {
        return new ContactPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            dateLabel: $this->dateLabel($locale),
            hero: $content->hero,
            info: $content->info,
            socialsTitle: $content->socialsTitle,
            socials: $content->socials,
            form: $content->form,
            location: $content->location,
            seoTitle: $content->seoTitle,
            seoDescription: $content->seoDescription,
            seoImage: $content->seoImage,
        );
    }

    public function updatePage(string $locale, ContactPageContentDTO $content, int $userId): bool
    {
        return $this->settingsService->updateGroup(
            new SettingsDTO('contact_page', $locale, [
                new SettingValueDTO(
                    key: 'content',
                    type: 'json',
                    jsonValue: $this->contentToArray($content),
                    isPublic: true,
                ),
            ]),
            $userId,
        );
    }

    public function submit(ContactSubmissionDataDTO $submission): bool
    {
        ContactMessage::query()->create([
            'locale' => $submission->locale,
            'name' => $submission->name,
            'email' => $submission->email,
            'subject' => $submission->subject,
            'message' => $submission->message,
            'status' => 'new',
            'ip_address' => $submission->ipAddress,
            'user_agent' => $submission->userAgent,
        ]);

        return true;
    }

    private function dateLabel(string $locale): string
    {
        $date = Carbon::now()->locale($locale === 'ar' ? 'ar' : 'en');

        return $date->translatedFormat('l j F Y');
    }

    public function getContent(string $locale): ContactPageContentDTO
    {
        $publishedPayload = $this->cmsWorkflowService->getPublishedPayload('contact');
        $publishedContent = is_array($publishedPayload['translations'][$locale] ?? null)
            ? $publishedPayload['translations'][$locale]
            : null;

        if (is_array($publishedContent)) {
            return $this->contentFromArray($publishedContent);
        }

        $settings = $this->settingsService->getGroup('contact_page', $locale);
        $content = collect($settings->values)->firstWhere('key', 'content')?->jsonValue ?? [];

        return $this->contentFromArray(is_array($content) ? $content : []);
    }

    /** @param array<string, mixed> $content */
    private function contentFromArray(array $content): ContactPageContentDTO
    {
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $info = is_array($content['info'] ?? null) ? $content['info'] : [];
        $form = is_array($content['form'] ?? null) ? $content['form'] : [];
        $location = is_array($content['location'] ?? null) ? $content['location'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];

        return new ContactPageContentDTO(
            hero: [
                'title' => $this->stringValue($hero, 'title'),
                'bgImage' => $this->stringValue($hero, 'bgImage'),
            ],
            info: [
                'title' => $this->stringValue($info, 'title'),
                'callUs' => $this->contactInfoItem($info, 'callUs'),
                'address' => $this->contactInfoItem($info, 'address'),
                'emailUs' => $this->contactInfoItem($info, 'emailUs'),
                'officeHours' => $this->contactInfoItem($info, 'officeHours'),
            ],
            socialsTitle: $this->stringValue($content, 'socialsTitle'),
            socials: $this->socials($content['socials'] ?? []),
            form: [
                'title' => $this->stringValue($form, 'title'),
                'fields' => [
                    'name' => ['label' => $this->fieldLabel($form, 'name')],
                    'email' => ['label' => $this->fieldLabel($form, 'email')],
                    'subject' => ['label' => $this->fieldLabel($form, 'subject')],
                    'message' => ['label' => $this->fieldLabel($form, 'message')],
                ],
                'submit' => $this->stringValue($form, 'submit'),
            ],
            location: [
                'title' => $this->stringValue($location, 'title'),
                'button' => $this->stringValue($location, 'button'),
                'mapUrl' => $this->stringValue($location, 'mapUrl'),
                'embedUrl' => $this->stringValue($location, 'embedUrl'),
            ],
            seoTitle: $this->stringValue($seo, 'title'),
            seoDescription: $this->stringValue($seo, 'description'),
            seoImage: $this->stringValue($seo, 'image'),
        );
    }

    /** @return array<string, mixed> */
    private function contentToArray(ContactPageContentDTO $content): array
    {
        return [
            'hero' => $content->hero,
            'info' => $content->info,
            'socialsTitle' => $content->socialsTitle,
            'socials' => $content->socials,
            'form' => $content->form,
            'location' => $content->location,
            'seo' => [
                'title' => $content->seoTitle,
                'description' => $content->seoDescription,
                'image' => $content->seoImage,
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function contactInfoItem(array $payload, string $key): array
    {
        $item = is_array($payload[$key] ?? null) ? $payload[$key] : [];

        return [
            'label' => $this->stringValue($item, 'label'),
            'value' => $this->stringValue($item, 'value'),
            'icon' => $this->stringValue($item, 'icon'),
        ];
    }

    /** @param array<string, mixed> $form */
    private function fieldLabel(array $form, string $field): string
    {
        $fields = is_array($form['fields'] ?? null) ? $form['fields'] : [];
        $item = is_array($fields[$field] ?? null) ? $fields[$field] : [];

        return $this->stringValue($item, 'label');
    }

    /** @return array<int, array{icon: string, url: string}> */
    private function socials(mixed $socials): array
    {
        if (! is_array($socials)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (! is_array($item)) {
                return null;
            }

            return [
                'icon' => $this->stringValue($item, 'icon'),
                'url' => $this->stringValue($item, 'url'),
            ];
        }, $socials), static fn (mixed $item): bool => is_array($item) && $item['icon'] !== '' && $item['url'] !== ''));
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }
}
