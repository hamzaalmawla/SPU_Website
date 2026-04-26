<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds starter public-shell settings for local development only.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->settings() as $setting) {
            Setting::query()->updateOrCreate(
                [
                    'group_key' => $setting['group_key'],
                    'key' => $setting['key'],
                    'locale' => $setting['locale'],
                ],
                [
                    'type' => $setting['type'],
                    'value_json' => $setting['value_json'],
                    'value_text' => $setting['value_text'],
                    'is_public' => $setting['is_public'],
                ]
            );
        }
    }

    /**
     * @return array<int, array{key: string, group_key: string, type: string, locale: string, value_json: array<string, mixed>|null, value_text: ?string, is_public: bool}>
     */
    private function settings(): array
    {
        return [
            [
                'key' => 'apply_cta',
                'group_key' => 'navigation',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => ['label' => 'قدّم الآن', 'url' => '/ar/admissions', 'target' => null, 'is_enabled' => true],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'apply_cta',
                'group_key' => 'navigation',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => ['label' => 'Apply now', 'url' => '/en/admissions', 'target' => null, 'is_enabled' => true],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'student_portal_url',
                'group_key' => 'navigation',
                'type' => 'text',
                'locale' => '',
                'value_json' => null,
                'value_text' => 'https://students.spu.edu.sy',
                'is_public' => true,
            ],
            [
                'key' => 'staff_access_url',
                'group_key' => 'navigation',
                'type' => 'text',
                'locale' => '',
                'value_json' => null,
                'value_text' => 'https://staff.spu.edu.sy',
                'is_public' => true,
            ],
            [
                'key' => 'emergency_notice',
                'group_key' => 'public_shell',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => ['is_enabled' => false, 'title' => null, 'message' => null, 'url' => null],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'emergency_notice',
                'group_key' => 'public_shell',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => ['is_enabled' => false, 'title' => null, 'message' => null, 'url' => null],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'footer',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => [
                    'copyrightText' => 'الجامعة الخاصة السورية',
                    'address' => 'دمشق، سوريا',
                    'phone' => '+963 11 000 0000',
                    'email' => 'info@spu.edu.sy',
                    'brandBlock' => [
                        'title' => 'الجامعة الخاصة السورية',
                        'body' => 'واجهة مشتركة قابلة للإدارة للتجارب المحلية وتكاملات القالب العام.',
                        'logoUrl' => '/images/home/footer-logo.png',
                    ],
                    'mapEmbed' => [
                        'url' => 'https://maps.google.com/?q=Damascus+Syria',
                    ],
                    'legalLinks' => [
                        ['label' => 'سياسة الخصوصية', 'url' => '/ar/about'],
                        ['label' => 'شروط الاستخدام', 'url' => '/ar/contact'],
                    ],
                ],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'footer',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => [
                    'copyrightText' => 'Syrian Private University',
                    'address' => 'Damascus, Syria',
                    'phone' => '+963 11 000 0000',
                    'email' => 'info@spu.edu.sy',
                    'brandBlock' => [
                        'title' => 'Syrian Private University',
                        'body' => 'A managed shared shell for local development and public runtime integration.',
                        'logoUrl' => '/images/home/footer-logo.png',
                    ],
                    'mapEmbed' => [
                        'url' => 'https://maps.google.com/?q=Damascus+Syria',
                    ],
                    'legalLinks' => [
                        ['label' => 'Privacy Policy', 'url' => '/en/about'],
                        ['label' => 'Terms of Use', 'url' => '/en/contact'],
                    ],
                ],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'social_contact',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => ['socialLinks' => [['label' => 'Facebook', 'url' => 'https://facebook.com/spu']]],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'social_contact',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => ['socialLinks' => [['label' => 'Facebook', 'url' => 'https://facebook.com/spu']]],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'contact_links',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => ['contactLinks' => [['label' => 'اتصل بنا', 'value' => 'info@spu.edu.sy', 'type' => 'email']]],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'contact_links',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => ['contactLinks' => [['label' => 'Contact us', 'value' => 'info@spu.edu.sy', 'type' => 'email']]],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'default_seo',
                'group_key' => 'seo',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => ['title' => 'الجامعة الخاصة السورية', 'meta_description' => 'المنصة الرسمية للجامعة الخاصة السورية.', 'og_title' => 'الجامعة الخاصة السورية', 'og_description' => 'محتوى رسمي ثنائي اللغة قابل للإدارة.'],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'default_seo',
                'group_key' => 'seo',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => ['title' => 'Syrian Private University', 'meta_description' => 'The official Syrian Private University web foundation.', 'og_title' => 'Syrian Private University', 'og_description' => 'Managed bilingual institutional content.'],
                'value_text' => null,
                'is_public' => true,
            ],
        ];
    }
}
