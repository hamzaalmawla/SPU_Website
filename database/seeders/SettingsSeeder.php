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
                'value_text' => '/e-services/it-support',
                'is_public' => true,
            ],
            [
                'key' => 'staff_access_url',
                'group_key' => 'navigation',
                'type' => 'text',
                'locale' => '',
                'value_json' => null,
                'value_text' => '/e-services/staff-email',
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
                    'phone' => null,
                    'email' => 'info@spu.edu.sy',
                    'brandBlock' => [
                        'title' => 'الجامعة الخاصة السورية',
                        'body' => 'مؤسسة تعليم عالٍ خاصة تأسست عام 2005 وتقدم برامج أكاديمية طبية وهندسية وإدارية.',
                        'logoUrl' => '/images/logo-spu.png',
                    ],
                    'mapEmbed' => [
                        'url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13346.741280351659!2d36.26129575!3d33.31448835!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1518f99e3f1e1e1f%3A0xe1e1e1e1e1e1e1e1!2sSyrian%20Private%20University!5e0!3m2!1sen!2ssy!4v1712710000000!5m2!1sen!2ssy',
                    ],
                    'legalLinks' => [],
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
                    'phone' => null,
                    'email' => 'info@spu.edu.sy',
                    'brandBlock' => [
                        'title' => 'Syrian Private University',
                        'body' => 'A private higher-education institution established in 2005, offering medical, engineering, and administrative academic programs.',
                        'logoUrl' => '/images/logo-spu.png',
                    ],
                    'mapEmbed' => [
                        'url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13346.741280351659!2d36.26129575!3d33.31448835!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1518f99e3f1e1e1f%3A0xe1e1e1e1e1e1e1e1!2sSyrian%20Private%20University!5e0!3m2!1sen!2ssy',
                    ],
                    'legalLinks' => [],
                ],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'social_contact',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'ar',
                'value_json' => ['socialLinks' => []],
                'value_text' => null,
                'is_public' => true,
            ],
            [
                'key' => 'social_contact',
                'group_key' => 'footer',
                'type' => 'json',
                'locale' => 'en',
                'value_json' => ['socialLinks' => []],
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
            ...$this->eServicesDetailSettings(),
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
        $supportUrl = $isArabic ? '/ar/e-services/it-support' : '/en/e-services/it-support';

        return [
            'hero' => [
                'eyebrow' => $isArabic ? 'بوابة الحرم الجامعي الرقمية' : 'DIGITAL CAMPUS GATEWAY',
                'title' => $isArabic ? 'الخدمات الإلكترونية الجامعية' : 'University E-Services',
                'summary' => $isArabic
                    ? 'دليل مركزي إلى الخدمات الرقمية المنشورة ومسارات المساعدة المعتمدة في الجامعة.'
                    : 'A central guide to published digital services and approved university assistance paths.',
                'imageHero' => '/images/slider-1.webp',
                'imageLeft' => '/images/dsc-1060.webp',
                'imageRight' => '/images/slider-3.webp',
            ],
            'digitalServices' => [
                'title' => $isArabic ? 'الخدمات الرقمية' : 'Digital Services',
                'services' => [
                    [
                        'id' => '1',
                        'title' => $isArabic ? 'مساعدة وصول الطلاب' : 'Student Access Help',
                        'summary' => $isArabic ? 'اطلع على مسار المساعدة عند تعذر الوصول إلى خدمة رقمية جامعية أو حساب طالب.' : 'Use the published support path when access to a university digital service or student account fails.',
                        'icon' => '/images/icons/users.svg',
                        'url' => $supportUrl,
                        'button' => $isArabic ? 'عرض مسار المساعدة' : 'View Support Path',
                    ],
                    [
                        'id' => '2',
                        'title' => $isArabic ? 'مساعدة التسجيل الرقمي' : 'Registration Access Help',
                        'summary' => $isArabic ? 'استخدم مسار الدعم المنشور عند ظهور مشكلة تقنية أثناء الوصول إلى خدمات التسجيل.' : 'Use the published support path when a technical issue prevents access to registration services.',
                        'icon' => '/images/icons/file.svg',
                        'url' => $supportUrl,
                        'button' => $isArabic ? 'طلب المساعدة' : 'Get Help',
                    ],
                    [
                        'id' => '3',
                        'title' => $isArabic ? 'وصول المكتبة' : 'Library Access',
                        'summary' => $isArabic ? 'استكشف موارد علمية مفتوحة وموثوقة مع إرشادات تساعدك على البحث والاستخدام الآمن.' : 'Explore verified open scholarly resources with guidance for effective and safe research.',
                        'icon' => '/images/icons/book.svg',
                        'url' => $isArabic ? '/ar/e-services/library' : '/en/e-services/library',
                        'button' => $isArabic ? 'عرض الدليل' : 'View Guide',
                    ],
                    [
                        'id' => '4',
                        'title' => $isArabic ? 'دعم القبول والنماذج' : 'Admissions & Forms Guidance',
                        'summary' => $isArabic ? 'تواصل مع الجامعة للاستفسار عن إجراءات القبول والطلبات والنماذج المتاحة.' : 'Contact the university for guidance about admissions procedures, requests, and available forms.',
                        'icon' => '/images/icons/check-circle.svg',
                        'url' => $isArabic ? '/ar/contact#admissions-support' : '/en/contact#admissions-support',
                        'button' => $isArabic ? 'تواصل مع القبول' : 'Contact Admissions',
                    ],
                    [
                        'id' => '5',
                        'title' => $isArabic ? 'دعم تكنولوجيا المعلومات' : 'IT Support',
                        'summary' => $isArabic ? 'اطلع على فئات المساعدة التقنية ومسار التواصل عند مواجهة مشكلة في حساب أو جهاز أو خدمة رقمية جامعية.' : 'Review technical help categories and the contact path for account, device, or university digital service issues.',
                        'icon' => '/images/icons/dept.svg',
                        'url' => $isArabic ? '/ar/e-services/it-support' : '/en/e-services/it-support',
                        'button' => $isArabic ? 'طلب المساعدة' : 'Get Help',
                    ],
                    [
                        'id' => '6',
                        'title' => $isArabic ? 'البريد الإلكتروني للموظفين' : 'Staff Email',
                        'summary' => $isArabic ? 'راجع إرشادات طلب الوصول إلى البريد الجامعي وحماية بيانات الحساب، مع مسار واضح للدعم التقني.' : 'Review general guidance for requesting university email access and protecting account credentials, with a clear IT support path.',
                        'icon' => '/images/icons/file.svg',
                        'url' => $isArabic ? '/ar/e-services/staff-email' : '/en/e-services/staff-email',
                        'button' => $isArabic ? 'عرض الإرشادات' : 'View Guidance',
                    ],
                ],
            ],
            'supportCards' => [
                [
                    'id' => 'guidance',
                    'eyebrow' => $isArabic ? 'الإرشادات' : 'Guidance',
                    'title' => $isArabic ? 'ابدأ من الخدمة المناسبة' : 'Start with the right service',
                    'summary' => $isArabic ? 'استخدم بطاقات الخدمات للوصول إلى الإرشادات أو مسار التواصل المرتبط بحاجتك.' : 'Use the service cards to reach the guidance or contact path that matches your need.',
                ],
                [
                    'id' => 'security',
                    'eyebrow' => $isArabic ? 'الأمان' : 'Security',
                    'title' => $isArabic ? 'احمِ بيانات الدخول' : 'Protect your credentials',
                    'summary' => $isArabic ? 'لا ترسل كلمات المرور أو رموز التحقق عبر نماذج التواصل أو رسائل الدعم.' : 'Never send passwords or verification codes through contact forms or support messages.',
                ],
            ],
            'seo' => [
                'title' => $isArabic ? 'الخدمات الإلكترونية | الجامعة السورية الخاصة' : 'E-Services | Syrian Private University',
                'description' => $isArabic ? 'الوصول إلى الخدمات الرقمية الرسمية للجامعة السورية الخاصة ونقاط دخول البوابة ومسارات دعم الطلاب.' : 'Access official SPU digital services, portal entry points, and protected student support pathways.',
                'image' => '/images/logo-spu.png',
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, group_key: string, type: string, locale: string, value_json: array<string, mixed>, value_text: null, is_public: bool}>
     */
    private function eServicesDetailSettings(): array
    {
        $settings = [];

        foreach (['library', 'staff-email', 'it-support'] as $slug) {
            foreach (['ar', 'en'] as $locale) {
                $settings[] = [
                    'key' => 'content',
                    'group_key' => 'e_services_'.str_replace('-', '_', $slug).'_page',
                    'type' => 'json',
                    'locale' => $locale,
                    'value_json' => $this->eServicesDetailContent($slug, $locale),
                    'value_text' => null,
                    'is_public' => true,
                ];
            }
        }

        return $settings;
    }

    /** @return array<string, mixed> */
    private function eServicesDetailContent(string $slug, string $locale): array
    {
        $isArabic = $locale === 'ar';
        $paths = [
            'landing' => '/'.$locale.'/e-services',
            'library' => '/'.$locale.'/e-services/library',
            'staff-email' => '/'.$locale.'/e-services/staff-email',
            'it-support' => '/'.$locale.'/e-services/it-support',
        ];

        if ($slug === 'library') {
            return [
                'hero' => [
                    'eyebrow' => $isArabic ? 'الخدمات الإلكترونية' : 'E-Services',
                    'title' => $isArabic ? 'المكتبة الإلكترونية' : 'E-Library',
                    'summary' => $isArabic ? 'دليل إلى موارد علمية مفتوحة وموثوقة يمكن الوصول إليها مباشرة عبر مواقع الجهات الناشرة.' : 'A guide to verified open scholarly resources available directly from their publishing organizations.',
                    'image' => '/images/slider-3.webp',
                ],
                'intro' => [
                    'title' => $isArabic ? 'موارد مفتوحة للبحث والتعلم' : 'Open resources for research and learning',
                    'body' => $isArabic ? 'تجمع هذه الصفحة روابط عامة تم التحقق منها لمساعدة الطلبة والباحثين على اكتشاف الكتب والمجلات والمجموعات الرقمية مفتوحة الوصول. يخضع محتوى كل مورد وشروط استخدامه للجهة التي تديره.' : 'This page brings together verified public links that help students and researchers discover open-access books, journals, and digital collections. Each provider controls its own content and terms of use.',
                ],
                'sections' => [
                    ['id' => 'discover', 'title' => $isArabic ? 'ابدأ بكلمات بحث دقيقة' : 'Start with focused search terms', 'body' => $isArabic ? 'استخدم موضوع البحث والكلمات المفتاحية واسم المؤلف أو السنة لتضييق النتائج، ثم راجع الملخص قبل فتح المادة الكاملة.' : 'Use your topic, keywords, author, or year to narrow results, then review the abstract or description before opening the full item.'],
                    ['id' => 'evaluate', 'title' => $isArabic ? 'قيّم المصدر' : 'Evaluate the source', 'body' => $isArabic ? 'تحقق من المؤلف والناشر وتاريخ النشر والمنهجية، وقارن المعلومات مع مصادر علمية أخرى عند إعداد الواجبات والأبحاث.' : 'Check the author, publisher, publication date, and methodology, and compare information with other scholarly sources.'],
                    ['id' => 'cite', 'title' => $isArabic ? 'وثق ما تستخدمه' : 'Cite what you use', 'body' => $isArabic ? 'سجل بيانات المرجع والرابط وتاريخ الوصول واتبع أسلوب التوثيق المطلوب في مقررك أو بحثك.' : 'Record the citation details, URL, and access date, and follow the citation style required for your course or research.'],
                ],
                'resources' => [
                    'title' => $isArabic ? 'موارد مفتوحة موثوقة' : 'Verified open resources',
                    'links' => [
                        ['id' => 'doab', 'title' => $isArabic ? 'دليل الكتب مفتوحة الوصول (DOAB)' : 'Directory of Open Access Books (DOAB)', 'url' => 'https://www.doabooks.org'],
                        ['id' => 'doaj', 'title' => $isArabic ? 'دليل المجلات مفتوحة الوصول (DOAJ)' : 'Directory of Open Access Journals (DOAJ)', 'url' => 'https://doaj.org'],
                        ['id' => 'internet-archive', 'title' => $isArabic ? 'أرشيف الإنترنت' : 'Internet Archive', 'url' => 'https://archive.org'],
                        ['id' => 'world-digital-library', 'title' => $isArabic ? 'مجموعة المكتبة الرقمية العالمية في مكتبة الكونغرس' : 'World Digital Library Collection at the Library of Congress', 'url' => 'https://www.loc.gov/collections/world-digital-library/about-this-collection/'],
                    ],
                ],
                'cta' => [
                    'title' => $isArabic ? 'هل تواجه مشكلة تقنية؟' : 'Having a technical issue?',
                    'body' => $isArabic ? 'راجع صفحة دعم تكنولوجيا المعلومات لمعرفة فئات المساعدة ومسار التواصل المناسب.' : 'Visit IT Support for help categories and the appropriate contact path.',
                    'label' => $isArabic ? 'الانتقال إلى الدعم التقني' : 'Go to IT Support',
                    'url' => $paths['it-support'],
                ],
                'relatedLinks' => [
                    ['id' => 'staff-email', 'title' => $isArabic ? 'البريد الإلكتروني للموظفين' : 'Staff Email', 'url' => $paths['staff-email']],
                    ['id' => 'it-support', 'title' => $isArabic ? 'دعم تكنولوجيا المعلومات' : 'IT Support', 'url' => $paths['it-support']],
                    ['id' => 'e-services', 'title' => $isArabic ? 'جميع الخدمات الإلكترونية' : 'All E-Services', 'url' => $paths['landing']],
                ],
                'seo' => [
                    'title' => $isArabic ? 'المكتبة الإلكترونية | الجامعة السورية الخاصة' : 'E-Library | Syrian Private University',
                    'description' => $isArabic ? 'استكشف روابط موثوقة لموارد الكتب والمجلات والمجموعات الرقمية مفتوحة الوصول.' : 'Explore verified links to open-access books, journals, and digital collections.',
                    'image' => '/images/slider-3.webp',
                ],
            ];
        }

        if ($slug === 'staff-email') {
            return [
                'hero' => [
                    'eyebrow' => $isArabic ? 'الخدمات الإلكترونية' : 'E-Services',
                    'title' => $isArabic ? 'البريد الإلكتروني للموظفين' : 'Staff Email',
                    'summary' => $isArabic ? 'إرشادات عامة لطلب الوصول إلى البريد الجامعي واستخدام بيانات الحساب بطريقة آمنة.' : 'General guidance for requesting university email access and using account credentials safely.',
                    'image' => '/images/slider-3.webp',
                ],
                'intro' => [
                    'title' => $isArabic ? 'طلب الوصول وحماية الحساب' : 'Request access and protect your account',
                    'body' => $isArabic ? 'اتبع الإجراء الإداري المعتمد في وحدتك عند الحاجة إلى حساب أو استعادة الوصول. لا ترسل كلمة المرور أو رموز التحقق ضمن رسالة أو نموذج دعم.' : 'Follow the approved administrative process in your unit when you need an account or restored access. Never send a password or verification code in a message or support form.',
                ],
                'sections' => [
                    ['id' => 'request', 'title' => $isArabic ? 'اطلب الوصول عبر القناة المعتمدة' : 'Use the approved request channel', 'body' => $isArabic ? 'ابدأ مع الجهة الإدارية المسؤولة في كليتك أو مديريتك. إذا كانت المشكلة تقنية، استخدم مسار دعم تكنولوجيا المعلومات الموضح في الموقع.' : 'Start with the responsible administrative unit in your faculty or directorate. For a technical issue, use the IT Support path provided on this website.'],
                    ['id' => 'credentials', 'title' => $isArabic ? 'حافظ على سرية بيانات الدخول' : 'Keep credentials private', 'body' => $isArabic ? 'استخدم كلمة مرور فريدة، ولا تشاركها مع أي شخص، وتحقق من عنوان الصفحة قبل إدخال بيانات حسابك.' : 'Use a unique password, never share it with anyone, and verify the page address before entering account credentials.'],
                    ['id' => 'suspicious', 'title' => $isArabic ? 'تعامل بحذر مع الرسائل المشبوهة' : 'Treat suspicious messages carefully', 'body' => $isArabic ? 'لا تفتح المرفقات أو الروابط غير المتوقعة. عند الشك، توقف عن التفاعل مع الرسالة واطلب المساعدة التقنية.' : 'Do not open unexpected attachments or links. If in doubt, stop interacting with the message and request technical assistance.'],
                ],
                'resources' => ['title' => '', 'links' => []],
                'cta' => [
                    'title' => $isArabic ? 'هل تحتاج إلى مساعدة تقنية؟' : 'Need technical assistance?',
                    'body' => $isArabic ? 'توضح صفحة دعم تكنولوجيا المعلومات فئات المساعدة ومسار إرسال استفسارك.' : 'The IT Support page explains help categories and how to send your inquiry.',
                    'label' => $isArabic ? 'فتح دعم تكنولوجيا المعلومات' : 'Open IT Support',
                    'url' => $paths['it-support'],
                ],
                'relatedLinks' => [
                    ['id' => 'library', 'title' => $isArabic ? 'المكتبة الإلكترونية' : 'E-Library', 'url' => $paths['library']],
                    ['id' => 'it-support', 'title' => $isArabic ? 'دعم تكنولوجيا المعلومات' : 'IT Support', 'url' => $paths['it-support']],
                    ['id' => 'e-services', 'title' => $isArabic ? 'جميع الخدمات الإلكترونية' : 'All E-Services', 'url' => $paths['landing']],
                ],
                'seo' => [
                    'title' => $isArabic ? 'إرشادات بريد الموظفين | الجامعة السورية الخاصة' : 'Staff Email Guidance | Syrian Private University',
                    'description' => $isArabic ? 'إرشادات عامة لطلب الوصول إلى البريد الجامعي وحماية بيانات الحساب وطلب الدعم التقني.' : 'General guidance for requesting university email access, protecting credentials, and seeking technical help.',
                    'image' => '/images/slider-3.webp',
                ],
            ];
        }

        return [
            'hero' => [
                'eyebrow' => $isArabic ? 'الخدمات الإلكترونية' : 'E-Services',
                'title' => $isArabic ? 'دعم تكنولوجيا المعلومات' : 'IT Support',
                'summary' => $isArabic ? 'تعرّف إلى مجالات المساعدة التقنية ومسار التواصل عند تعطل خدمة جامعية رقمية.' : 'Learn about technical help categories and the contact path for a university digital service issue.',
                'image' => '/images/slider-3.webp',
            ],
            'intro' => [
                'title' => $isArabic ? 'حدّد نوع المشكلة قبل التواصل' : 'Identify the issue before contacting support',
                'body' => $isArabic ? 'سجّل الخدمة أو الجهاز المتأثر، وما الذي كنت تحاول تنفيذه، ونص رسالة الخطأ إن ظهرت. لا ترفق كلمات المرور أو أي بيانات دخول سرية.' : 'Note the affected service or device, what you were trying to do, and the exact error message if one appeared. Do not include passwords or other secret credentials.',
            ],
            'sections' => [
                ['id' => 'accounts', 'title' => $isArabic ? 'الحسابات والوصول' : 'Accounts and access', 'body' => $isArabic ? 'مساعدة عامة عند تعذر تسجيل الدخول أو الوصول إلى خدمة جامعية رقمية، مع التحقق من هوية صاحب الطلب عبر الإجراء المعتمد.' : 'General assistance when sign-in or access to a university digital service fails, subject to the approved identity verification process.'],
                ['id' => 'connectivity', 'title' => $isArabic ? 'الشبكة والاتصال' : 'Network and connectivity', 'body' => $isArabic ? 'الإبلاغ عن مشكلة اتصال ضمن بيئة الجامعة مع تحديد المكان والجهاز والوقت الذي ظهرت فيه المشكلة.' : 'Report a connectivity issue in the university environment, including the location, device, and time it occurred.'],
                ['id' => 'devices', 'title' => $isArabic ? 'الأجهزة والبرمجيات' : 'Devices and software', 'body' => $isArabic ? 'طلب إرشاد بشأن جهاز أو برنامج مستخدم في العمل أو الدراسة، مع وصف السلوك المتوقع وما حدث فعليا.' : 'Request guidance for a device or software used for work or study, describing the expected behavior and what actually happened.'],
                ['id' => 'systems', 'title' => $isArabic ? 'الأنظمة والخدمات الرقمية' : 'Systems and digital services', 'body' => $isArabic ? 'مساعدة في تشخيص مشكلة ضمن بوابة أو نظام جامعي من خلال معلومات واضحة وخطوات يمكن إعادة تنفيذها.' : 'Help diagnose an issue in a university portal or system using clear details and reproducible steps.'],
            ],
            'resources' => ['title' => '', 'links' => []],
            'cta' => [
                'title' => $isArabic ? 'أرسل استفسار دعم تقني' : 'Send an IT support inquiry',
                'body' => $isArabic ? 'استخدم نموذج التواصل العام؛ سيظهر موضوع طلب المساعدة التقنية تلقائيا لتوضيح وجهة الرسالة.' : 'Use the general contact form; the IT support subject will be prefilled to clarify the purpose of your message.',
                'label' => $isArabic ? 'الانتقال إلى نموذج التواصل' : 'Go to contact form',
                'url' => '/'.$locale.'/contact?topic=it-support#contact-form',
            ],
            'relatedLinks' => [
                ['id' => 'library', 'title' => $isArabic ? 'المكتبة الإلكترونية' : 'E-Library', 'url' => $paths['library']],
                ['id' => 'staff-email', 'title' => $isArabic ? 'البريد الإلكتروني للموظفين' : 'Staff Email', 'url' => $paths['staff-email']],
                ['id' => 'e-services', 'title' => $isArabic ? 'جميع الخدمات الإلكترونية' : 'All E-Services', 'url' => $paths['landing']],
            ],
            'seo' => [
                'title' => $isArabic ? 'دعم تكنولوجيا المعلومات | الجامعة السورية الخاصة' : 'IT Support | Syrian Private University',
                'description' => $isArabic ? 'تعرّف إلى فئات المساعدة التقنية واستخدم نموذج التواصل لطلب الدعم في الخدمات الرقمية الجامعية.' : 'Review technical help categories and use the contact form for university digital service support.',
                'image' => '/images/slider-3.webp',
            ],
        ];
    }
}
