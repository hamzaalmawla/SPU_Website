<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Settings\Setting;
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
                'value_json' => ['label' => 'سجّل الآن', 'url' => '/ar/admissions', 'target' => null, 'is_enabled' => true],
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
                'key' => 'content',
                'group_key' => 'contact_page',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => $this->contactPageContent('ar'),
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'content',
                'group_key' => 'contact_page',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => $this->contactPageContent('en'),
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'content',
                'group_key' => 'e_services_page',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => $this->eServicesPageContent('ar'),
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'content',
                'group_key' => 'e_services_page',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => $this->eServicesPageContent('en'),
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

    /**
     * @return array<string, mixed>
     */
    private function contactPageContent(string $locale): array
    {
        $isArabic = $locale === 'ar';

        return [
            'hero' => [
                'title' => $isArabic ? 'تواصل معنا' : 'CONTACT US',
                'bgImage' => '/images/slider-3.webp',
            ],
            'info' => [
                'title' => $isArabic ? 'ابق على تواصل' : 'Get In Touch',
                'callUs' => [
                    'label' => $isArabic ? 'اتصل بنا' : 'CALL US',
                    'value' => '+963 11 123 4567',
                    'icon' => '/images/icon-phone-outline.svg',
                ],
                'address' => [
                    'label' => $isArabic ? 'العنوان' : 'ADDRESS',
                    'value' => $isArabic
                        ? 'مقر الجامعة السورية الخاصة، طريق درعا الدولي، دمشق، سوريا'
                        : 'Syrian Private University Campus, Daraa Highway, Damascus, Syria',
                    'icon' => '/images/icon-map-outline.svg',
                ],
                'emailUs' => [
                    'label' => $isArabic ? 'راسلنا' : 'EMAIL US',
                    'value' => 'info@spu.edu.sy',
                    'icon' => '/images/icon-envelope-outline.svg',
                ],
                'officeHours' => [
                    'label' => $isArabic ? 'ساعات العمل' : 'OFFICE HOURS',
                    'value' => $isArabic ? 'الأحد - الخميس 8:00 صباحاً - 4:00 مساءً' : 'Sunday - Thursday 8:00 AM - 4:00 PM',
                    'icon' => '/images/time.svg',
                ],
            ],
            'socialsTitle' => $isArabic ? 'تواصل معنا عبر' : 'CONNECT WITH US',
            'socials' => [
                ['icon' => '/images/icon-facebook-outline.svg', 'url' => 'https://www.facebook.com/SPUpage.sy'],
                ['icon' => '/images/icon-instagram-outline.svg', 'url' => 'https://www.instagram.com/spu_syrian_private_university/'],
                ['icon' => '/images/icon-youtube-outline.svg', 'url' => 'https://youtube.com/@spusyrianprivateuniversity755?si=xW_6Zru4wvjHnm6R'],
            ],
            'form' => [
                'title' => $isArabic ? 'أرسل لنا رسالة' : 'Send us a Message',
                'fields' => [
                    'name' => ['label' => $isArabic ? 'اسمك' : 'Your Name'],
                    'email' => ['label' => $isArabic ? 'بريدك الإلكتروني' : 'Your Email'],
                    'subject' => ['label' => $isArabic ? 'الموضوع' : 'Subject'],
                    'message' => ['label' => $isArabic ? 'رسالتك' : 'Your Message'],
                ],
                'submit' => $isArabic ? 'إرسال الرسالة' : 'Send Message',
            ],
            'location' => [
                'title' => $isArabic ? 'موقع الحرم الجامعي' : 'Campus Location',
                'button' => $isArabic ? 'فتح خريطة الحرم الجامعي' : 'Open Campus Map',
                'mapUrl' => 'https://www.google.com/maps?q=Syrian+Private+University',
                'embedUrl' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13346.741280351659!2d36.26129575!3d33.31448835!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1518f99e3f1e1e1f%3A0xe1e1e1e1e1e1e1e1!2sSyrian%20Private%20University!5e0!3m2!1sen!2ssy!4v1712710000000!5m2!1sen!2ssy',
            ],
            'seo' => [
                'title' => $isArabic ? 'تواصل معنا | الجامعة السورية الخاصة' : 'Contact | Syrian Private University',
                'description' => $isArabic
                    ? 'تواصل مع الجامعة السورية الخاصة عبر قنوات القبول وشؤون الطلاب ومكاتب الجامعة وزيارات الحرم الجامعي.'
                    : 'Reach SPU through official contact channels for admissions, student affairs, university offices, and campus visits.',
                'image' => '/images/logo-spu.png',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eServicesPageContent(string $locale): array
    {
        $isArabic = $locale === 'ar';
        $portalUrl = 'http://my.spu.edu.sy/ar/login';

        return [
            'hero' => [
                'eyebrow' => $isArabic ? 'بوابة الحرم الجامعي الرقمية' : 'DIGITAL CAMPUS GATEWAY',
                'title' => $isArabic ? 'الخدمات الإلكترونية الجامعية' : 'University E-Services',
                'summary' => $isArabic
                    ? 'الوصول إلى أدواتك الأكاديمية الأساسية وإدارة التسجيلات والاتصال بأنظمة دعم الجامعة من خلال منصتنا الرقمية الآمنة والمبسطة.'
                    : 'Access your essential academic tools, manage registrations, and connect with university support systems through our secure and streamlined digital platform.',
                'imageHero' => '/images/slider-1.webp',
                'imageLeft' => '/images/dsc-1060.webp',
                'imageRight' => '/images/slider-3.webp',
            ],
            'digitalServices' => [
                'title' => $isArabic ? 'الخدمات الرقمية' : 'Digital Services',
                'services' => [
                    [
                        'id' => '1',
                        'title' => $isArabic ? 'بوابة الطالب' : 'Student Portal',
                        'summary' => $isArabic ? 'الوصول إلى درجاتك والسجل الأكاديمي والجداول الدراسية وملفك الشخصي الأكاديمي في مكان واحد.' : 'Access your grades, academic transcript, course schedules, and personal academic profile in one place.',
                        'icon' => '/images/icons/users.svg',
                        'url' => $portalUrl,
                        'button' => $isArabic ? 'تفعيل الخدمة' : 'Launch Service',
                    ],
                    [
                        'id' => '2',
                        'title' => $isArabic ? 'التسجيل' : 'Registration',
                        'summary' => $isArabic ? 'التسجيل في الدورات للفصل الدراسي القادم. عرض جدولك الحالي والمقترح والفصل الدراسي.' : 'Enroll in courses for the upcoming semester. View your current and proposed class schedule and the classroom.',
                        'icon' => '/images/icons/file.svg',
                        'url' => $portalUrl,
                        'button' => $isArabic ? 'تفعيل الخدمة' : 'Launch Service',
                    ],
                    [
                        'id' => '3',
                        'title' => $isArabic ? 'وصول المكتبة' : 'Library Access',
                        'summary' => $isArabic ? 'البحث في الفهرس الرقمي وحجز الكتب الفيزيائية والوصول إلى المجلات الأكاديمية واستخدام قواعد البيانات البحثية الأخرى.' : 'Search the digital catalog, reserve physical books, access academic journals, and utilize other digital research databases.',
                        'icon' => '/images/icons/book.svg',
                        'url' => $isArabic ? '/ar/student-life#services' : '/en/student-life#services',
                        'button' => $isArabic ? 'عرض الدليل' : 'View Guide',
                    ],
                    [
                        'id' => '4',
                        'title' => $isArabic ? 'الاستئنافات والنماذج' : 'Appeals & Forms',
                        'summary' => $isArabic ? 'تقديم الالتماسات والاستئنافات الأكاديمية وطلبات التوثيق وإدارة سجلاتك الرسمية بأمان.' : 'Submit official university petitions, academic appeals, documentation requests, and manage your official records securely.',
                        'icon' => '/images/icons/check-circle.svg',
                        'url' => $isArabic ? '/ar/contact#admissions-support' : '/en/contact#admissions-support',
                        'button' => $isArabic ? 'تفعيل الخدمة' : 'Launch Service',
                    ],
                    [
                        'id' => '5',
                        'title' => $isArabic ? 'دعم تكنولوجيا المعلومات' : 'IT Support',
                        'summary' => $isArabic ? 'إنشاء تذاكر الدعم والإبلاغ عن مشاكل الشبكة وإعادة تعيين كلمات المرور والحصول على مساعدة من فريق دعم تكنولوجيا المعلومات بالجامعة.' : 'Create support tickets, report network issues, reset passwords, and get help from the university IT support team.',
                        'icon' => '/images/icons/dept.svg',
                        'url' => $isArabic ? '/ar/contact#it-support' : '/en/contact#it-support',
                        'button' => $isArabic ? 'تفعيل الخدمة' : 'Launch Service',
                    ],
                ],
            ],
            'supportCards' => [
                [
                    'id' => 'appeals',
                    'eyebrow' => $isArabic ? 'النماذج' : 'Forms',
                    'title' => $isArabic ? 'الاعتراضات والنماذج' : 'Appeals & Forms',
                    'summary' => $isArabic ? 'يمكن للطلاب متابعة النماذج الرسمية وطلبات الاعتراض عبر بوابة الخدمات الإلكترونية.' : 'Students can follow official forms and appeal requests through the e-services gateway.',
                ],
                [
                    'id' => 'privacy',
                    'eyebrow' => $isArabic ? 'الحوكمة' : 'Governance',
                    'title' => $isArabic ? 'الخصوصية وإمكانية الوصول' : 'Privacy & Accessibility',
                    'summary' => $isArabic ? 'تظهر روابط الخصوصية وملفات الارتباط وإمكانية الوصول في تذييل الموقع وفق المتطلبات.' : 'Privacy, cookie, and accessibility links are exposed in the global footer according to the requirements.',
                ],
            ],
            'seo' => [
                'title' => $isArabic ? 'الخدمات الإلكترونية | الجامعة السورية الخاصة' : 'E-Services | Syrian Private University',
                'description' => $isArabic ? 'الوصول إلى الخدمات الرقمية الرسمية للجامعة السورية الخاصة ونقاط دخول البوابة ومسارات دعم الطلاب.' : 'Access official SPU digital services, portal entry points, and protected student support pathways.',
                'image' => '/images/logo-spu.png',
            ],
        ];
    }
}
