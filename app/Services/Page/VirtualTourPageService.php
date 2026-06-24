<?php

declare(strict_types=1);

namespace App\Services\Page;

use App\Contracts\Page\VirtualTourPageServiceInterface;
use App\DTOs\VirtualTour\VirtualTourPageDTO;

final class VirtualTourPageService implements VirtualTourPageServiceInterface
{
    public function getPage(string $locale): VirtualTourPageDTO
    {
        $page = $this->normalizeUrls($this->localized($this->payload(), $locale), $locale);

        return new VirtualTourPageDTO(
            locale: $locale,
            direction: $locale === 'ar' ? 'rtl' : 'ltr',
            page: $page,
            seoTitle: $locale === 'ar' ? 'الجولة الافتراضية | الجامعة السورية الخاصة' : 'Virtual Tour | Syrian Private University',
            seoDescription: $locale === 'ar'
                ? 'استكشف الجامعة السورية الخاصة من خلال جولة مرئية في مبنى الإدارة والمساحات الطلابية والمستشفى والعيادات والمرافق التعليمية.'
                : 'Explore Syrian Private University through a visual campus tour featuring the central administration building, student spaces, hospital, dental clinics, and learning facilities.',
            seoImage: '/images/uni-main-place.JPG',
        );
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'hero' => [
                'eyebrowEn' => 'Virtual Tour',
                'eyebrowAr' => 'جولة افتراضية',
                'titleEn' => 'Explore Our Campus Virtually',
                'titleAr' => 'استكشف حرمنا الجامعي افتراضياً',
                'summaryEn' => 'Take a closer look at our state-of-the-art classrooms, advanced research labs, extensive library, and student spaces all from your screen.',
                'summaryAr' => 'تعرّف عن قرب إلى القاعات الدراسية والمختبرات المتقدمة والمكتبة ومساحات الطلاب من خلال جولة رقمية.',
                'image' => '/images/slider-4.webp',
                'imageAltEn' => 'SPU campus walkway',
                'imageAltAr' => 'ممر داخل حرم الجامعة السورية الخاصة',
                'primaryLabelEn' => 'Start Virtual Tour',
                'primaryLabelAr' => 'ابدأ الجولة الافتراضية',
                'primaryUrl' => '/virtual-tour#tour',
                'secondaryLabelEn' => 'Map & Floor Plan',
                'secondaryLabelAr' => 'الخريطة والمخططات',
                'secondaryUrl' => '/virtual-tour#facilities',
                'primaryIcon' => '/images/icon-map-outline.svg',
                'secondaryIcon' => '/images/icon-sitemap-outline.svg',
            ],
            'tour' => [
                'eyebrowEn' => 'Main Campus',
                'eyebrowAr' => 'الحرم الرئيسي',
                'titleEn' => 'Central Administration Building',
                'titleAr' => 'مبنى الإدارة المركزية',
                'summaryEn' => 'The heart of SPU campus, housing the executive offices and main hall.',
                'summaryAr' => 'قلب الحرم الجامعي، ويضم المكاتب التنفيذية والقاعة الرئيسية.',
                'image' => '/images/uni-main-place.JPG',
                'imageAltEn' => 'Central Administration Building and fountain',
                'imageAltAr' => 'مبنى الإدارة المركزية والنافورة',
                'controlLabelEn' => 'Tour controls',
                'controlLabelAr' => 'أدوات الجولة',
                'floorLabelEn' => 'Floor plan',
                'floorLabelAr' => 'المخطط',
                'fullscreenLabelEn' => 'Fullscreen',
                'fullscreenLabelAr' => 'ملء الشاشة',
                'experienceLabelEn' => '360 degree view',
                'experienceLabelAr' => 'عرض 360 درجة',
                'hotspots' => [
                    ['id' => 'main-hall', 'x' => '49%', 'y' => '37%', 'labelEn' => 'Main Hall', 'labelAr' => 'القاعة الرئيسية'],
                    ['id' => 'fountain', 'x' => '50%', 'y' => '76%', 'labelEn' => 'Campus Fountain', 'labelAr' => 'نافورة الحرم'],
                    ['id' => 'student-entry', 'x' => '31%', 'y' => '55%', 'labelEn' => 'Student Entry', 'labelAr' => 'مدخل الطلاب'],
                ],
            ],
            'highlights' => [
                'eyebrowEn' => 'Campus Highlights',
                'eyebrowAr' => 'أبرز مرافق الحرم',
                'titleEn' => 'Campus Highlights',
                'titleAr' => 'أبرز مرافق الحرم الجامعي',
                'summaryEn' => 'Discover the state-of-the-art facilities that make Syrian Private University a premier center for academic and personal growth.',
                'summaryAr' => 'اكتشف المرافق الحديثة التي تجعل الجامعة السورية الخاصة مركزاً متميزاً للتعلم والنمو الشخصي.',
                'items' => [
                    ['id' => 'hospital', 'titleEn' => 'University Hospital', 'titleAr' => 'المستشفى الجامعي', 'summaryEn' => 'Teaching hospital with clinical training and community healthcare services.', 'summaryAr' => 'مستشفى تعليمي يوفّر التدريب السريري وخدمات الرعاية الصحية.', 'image' => '/images/campus-hospital.webp', 'imageAltEn' => 'University Hospital reception', 'imageAltAr' => 'استقبال المستشفى الجامعي', 'href' => '/campus-life/hospital', 'labelEn' => 'Explore Hospital', 'labelAr' => 'استكشف المستشفى', 'featured' => true],
                    ['id' => 'dental', 'titleEn' => 'Dental Clinics', 'titleAr' => 'عيادات طب الأسنان', 'summaryEn' => 'Modern clinical chairs and supervised patient care for dental students.', 'summaryAr' => 'عيادات حديثة وتدريب سريري بإشراف متخصص.', 'image' => '/images/campus-dental.webp', 'imageAltEn' => 'Dental clinic training space', 'imageAltAr' => 'مساحة تدريب في عيادات الأسنان', 'href' => '/campus-life/dental', 'labelEn' => 'View Clinics', 'labelAr' => 'عرض العيادات'],
                    ['id' => 'activities', 'titleEn' => 'Student Activities', 'titleAr' => 'الأنشطة الطلابية', 'summaryEn' => 'Student clubs, events, and shared campus experiences.', 'summaryAr' => 'نوادٍ طلابية وفعاليات وتجارب جامعية مشتركة.', 'image' => '/images/campus-clubs.webp', 'imageAltEn' => 'Students participating in activities', 'imageAltAr' => 'طلاب يشاركون في الأنشطة', 'href' => '/campus-life/clubs-activities', 'labelEn' => 'Explore Activities', 'labelAr' => 'استكشف الأنشطة'],
                ],
            ],
            'facilities' => [
                'eyebrowEn' => 'Facilities',
                'eyebrowAr' => 'المرافق',
                'titleEn' => 'Discover Our Facilities',
                'titleAr' => 'اكتشف مرافقنا',
                'summaryEn' => 'Navigate through comprehensive campus infrastructure designed to support academic excellence and student well-being.',
                'summaryAr' => 'تنقّل بين مرافق جامعية شاملة صُممت لدعم التميز الأكاديمي ورفاه الطلاب.',
                'detailsLabelEn' => 'View Details',
                'detailsLabelAr' => 'عرض التفاصيل',
                'items' => [
                    ['id' => 'classrooms', 'titleEn' => 'Smart Classrooms', 'titleAr' => 'قاعات ذكية', 'summaryEn' => 'Technology-enabled learning spaces for interactive instruction.', 'summaryAr' => 'مساحات تعليمية مجهزة لدعم التدريس التفاعلي.', 'icon' => '/images/icons/book.svg', 'image' => '/images/slider-2.webp', 'href' => '/facilities'],
                    ['id' => 'laboratories', 'titleEn' => 'Medical Labs', 'titleAr' => 'مختبرات طبية', 'summaryEn' => 'Applied teaching laboratories for health and science programs.', 'summaryAr' => 'مختبرات تعليمية تطبيقية للبرامج الصحية والعلمية.', 'icon' => '/images/icons/lab.svg', 'image' => '/images/dental-clin-lab.jpg', 'href' => '/facilities'],
                    ['id' => 'library', 'titleEn' => 'University Library', 'titleAr' => 'مكتبة الجامعة', 'summaryEn' => 'Study collections, quiet reading areas, and research support.', 'summaryAr' => 'مصادر دراسية ومساحات قراءة هادئة ودعم بحثي.', 'icon' => '/images/icons/globe.svg', 'image' => '/images/slider-3.webp', 'href' => '/e-services'],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function localized(array $payload, string $locale): array
    {
        $suffix = $locale === 'ar' ? 'Ar' : 'En';
        $opposite = $locale === 'ar' ? 'En' : 'Ar';
        $localized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = array_is_list($value)
                    ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->localized($item, $locale) : $item, $value)
                    : $this->localized($value, $locale);
            }

            if (str_ends_with((string) $key, $suffix)) {
                $localized[substr((string) $key, 0, -2)] = $value;
                continue;
            }

            if (str_ends_with((string) $key, $opposite)) {
                continue;
            }

            $localized[$key] = $value;
        }

        return $localized;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizeUrls(array $payload, string $locale): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = array_is_list($value)
                    ? array_map(fn (mixed $item): mixed => is_array($item) ? $this->normalizeUrls($item, $locale) : $item, $value)
                    : $this->normalizeUrls($value, $locale);
                continue;
            }

            if (is_string($value) && in_array($key, ['href', 'primaryUrl', 'secondaryUrl'], true)) {
                $payload[$key] = $this->normalizeUrl($value, $locale);
            }
        }

        return $payload;
    }

    private function normalizeUrl(string $url, string $locale): string
    {
        if ($url === '#' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return $url;
        }

        if (str_starts_with($url, '/images/') || $url === '/sitemap.xml') {
            return $url;
        }

        if ($url === '/') {
            return '/'.$locale;
        }

        return str_starts_with($url, '/'.$locale.'/') || $url === '/'.$locale
            ? $url
            : '/'.$locale.$url;
    }
}
