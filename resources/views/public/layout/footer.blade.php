@php
    $isArabic = $locale === 'ar';
    $mapEmbedUrl = $navigation->footerSettings->mapEmbedUrl ?? null;
    $switchLocale = $isArabic ? 'en' : 'ar';
    $switchUrl = '/' . $switchLocale;
    $resourceLinks = [
        ['label_ar' => 'عن الجامعة', 'label_en' => 'About SPU', 'url' => "/{$locale}/about"],
        ['label_ar' => 'المرافق', 'label_en' => 'Facilities', 'url' => "/{$locale}/facilities"],
        ['label_ar' => 'القبول', 'label_en' => 'Admissions', 'url' => "/{$locale}/admissions"],
        ['label_ar' => 'البحث العلمي', 'label_en' => 'Research', 'url' => "/{$locale}/research"],
        ['label_ar' => 'الحياة الجامعية', 'label_en' => 'Campus Life', 'url' => "/{$locale}/campus-life"],
        ['label_ar' => 'الخدمات الإلكترونية', 'label_en' => 'E-Services', 'url' => "/{$locale}/e-services"],
        ['label_ar' => 'الأخبار', 'label_en' => 'News', 'url' => "/{$locale}/news"],
    ];
    $bottomLinks = [
        ['label_ar' => 'سياسة الخصوصية', 'label_en' => 'Privacy Policy', 'url' => "/{$locale}/privacy-policy"],
        ['label_ar' => 'سياسة ملفات الارتباط', 'label_en' => 'Cookie Policy', 'url' => "/{$locale}/cookie-policy"],
        ['label_ar' => 'إمكانية الوصول', 'label_en' => 'Accessibility', 'url' => "/{$locale}/accessibility"],
        ['label_ar' => 'خريطة الموقع', 'label_en' => 'Sitemap', 'url' => "/{$locale}/sitemap"],
        ['label_ar' => 'بوابة الطالب', 'label_en' => 'Student Portal', 'url' => "/{$locale}/e-services"],
    ];
    $socials = [
        ['label' => 'Website', 'url' => "/{$locale}", 'icon' => '/images/icon-globe-outline.svg'],
        ['label' => 'Telegram', 'url' => 'https://t.me/spu', 'icon' => '/images/icon-telegram-outline.svg'],
        ['label' => 'Facebook', 'url' => 'https://facebook.com/spu', 'icon' => '/images/icon-facebook-outline.svg'],
        ['label' => 'Instagram', 'url' => 'https://instagram.com/spu', 'icon' => '/images/icon-instagram-outline.svg'],
        ['label' => 'YouTube', 'url' => 'https://youtube.com', 'icon' => '/images/icon-youtube-outline.svg'],
    ];
@endphp

<footer id="site-footer" class="overflow-hidden bg-spu-blue pt-16 pb-8 font-hacen text-white">
    <div class="container">
        <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
            <div class="flex flex-col items-start lg:col-span-4">
                <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">
                    {{ $isArabic ? 'الجامعة السورية الخاصة' : 'SYRIAN PRIVATE UNIVERSITY' }}
                </h2>
                <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                    {{ $isArabic ? 'ملتزمون بتعزيز التميز الأكاديمي والقيادة العالمية من قلب دمشق.' : 'Committed to fostering academic excellence and global leadership from the heart of Damascus.' }}
                </p>

                <div class="flex items-center gap-6 text-[22px]">
                    @foreach ($socials as $social)
                        <a href="{{ $social['url'] }}" @if (str_starts_with($social['url'], 'http')) target="_blank" rel="noreferrer" @endif class="text-white/80 transition-all hover:scale-110 hover:text-spu-red" aria-label="{{ $social['label'] }}">
                            <img src="{{ $social['icon'] }}" alt="" class="h-5 w-5 brightness-0 invert transition-opacity" aria-hidden="true">
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-2">
                <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                    {{ $isArabic ? 'استكشف SPU' : 'EXPLORE SPU' }}
                </h3>
                <ul class="flex flex-col gap-4">
                    @foreach ($resourceLinks as $link)
                        <li>
                            <a href="{{ $link['url'] }}" class="text-[16px] text-white/80 transition-colors hover:text-white">
                                {{ $isArabic ? $link['label_ar'] : $link['label_en'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lg:col-span-3">
                <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                    {{ $isArabic ? 'التواصل' : 'CONTACT' }}
                </h3>
                <div class="flex flex-col gap-6">
                    <div class="flex items-start gap-4">
                        <img src="/images/icon-map-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                        <span class="text-[15px] leading-relaxed text-white/80">
                            {{ $isArabic ? 'مقر الجامعة الرئيسي، أوتوستراد درعا الدولي، بعد بلدة الكسوة، خيارة دنون، دمشق.' : 'University headquarters, Daraa International Highway, past Al-Kiswa, Khayara Danoun, Damascus.' }}
                        </span>
                    </div>
                    <div class="flex items-start gap-4">
                        <img src="/images/icon-phone-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                        <span class="ltr text-[15px] leading-relaxed text-white/80">+963 11 9860</span>
                    </div>
                    <div class="flex items-start gap-4">
                        <img src="/images/icon-envelope-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                        <span class="ltr text-[15px] leading-relaxed text-white/80">info@spu.edu.sy</span>
                    </div>
                </div>
            </div>

            @if ($mapEmbedUrl)
                <div class="flex flex-col items-start lg:col-span-3 lg:items-end">
                    <h3 class="mb-8 w-full text-left text-[18px] font-bold uppercase tracking-widest text-white/50 {{ $isArabic ? 'lg:text-right' : 'lg:text-left' }}">
                        {{ $isArabic ? 'الموقع' : 'LOCATION' }}
                    </h3>
                    <div class="group h-[180px] w-full overflow-hidden rounded-[12px] border border-white/10 shadow-2xl">
                        <iframe src="{{ $mapEmbedUrl }}" class="h-full w-full grayscale-[0.3] opacity-80 transition-all duration-700 group-hover:grayscale-0 group-hover:opacity-100" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            @endif
        </div>

        <hr class="mb-8 border-white/10">

        <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
            <p class="text-[14px] text-white/50" translate="no">
                {{ $isArabic ? '© 2026 الجامعة السورية الخاصة. التميز في التعليم.' : '© 2026 Syrian Private University. Excellence in Education.' }}
            </p>

            <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                @foreach ($bottomLinks as $link)
                    <a href="{{ $link['url'] }}" class="text-white/50 transition-colors hover:text-white">
                        {{ $isArabic ? $link['label_ar'] : $link['label_en'] }}
                    </a>
                @endforeach
                <a href="{{ $switchUrl }}" class="text-white/50 transition-colors hover:text-white">
                    {{ $isArabic ? 'English' : 'العربية' }}
                </a>
            </div>
        </div>
    </div>
</footer>
