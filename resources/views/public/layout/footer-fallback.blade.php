<div class="container">
    <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
        <div class="flex flex-col items-start lg:col-span-4">
            <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">{{ $locale === 'ar' ? 'الجامعة السورية الخاصة' : 'SYRIAN PRIVATE UNIVERSITY' }}</h2>
            <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                {{ $locale === 'ar' ? 'ملتزمون بتعزيز التميز الأكاديمي والقيادة العالمية من قلب دمشق.' : 'Committed to fostering academic excellence and global leadership from the heart of Damascus.' }}
            </p>

            @if ($navigation->socialContact->socialLinks !== [])
                <div class="flex items-center gap-6 text-[22px]">
                    @foreach ($navigation->socialContact->socialLinks as $link)
                        @continue(! ($link->isEnabled ?? true))
                        @php($platform = strtolower($link->platform ?? ''))
                        @php($icon = match ($platform) {
                            'facebook' => '/images/icon-facebook-outline.svg',
                            'instagram' => '/images/icon-instagram-outline.svg',
                            'telegram', 'telegram-plane' => '/images/icon-telegram-outline.svg',
                            'youtube' => '/images/icon-youtube-outline.svg',
                            'globe', 'website' => '/images/icon-globe-outline.svg',
                            default => '/images/icon-globe-outline.svg',
                        })
                        <a href="{{ $link->url }}" target="_blank" rel="noreferrer" class="text-white/80 transition-all hover:scale-110 hover:text-spu-red" aria-label="{{ $link->platform ?? 'Social' }}">
                            <span class="block h-5 w-5 bg-current" aria-hidden="true" style="-webkit-mask: url('{{ $icon }}') center / contain no-repeat; mask: url('{{ $icon }}') center / contain no-repeat;"></span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="lg:col-span-2">
            <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                <span class="sr-only">{{ __('public.navigation_heading') }}</span>
                <span aria-hidden="true">{{ $locale === 'ar' ? 'استكشف SPU' : 'EXPLORE SPU' }}</span>
            </h3>
            <ul class="flex flex-col gap-4">
                @foreach ($navigation->footer->items as $item)
                    @if ($item->resolvedUrl)
                        <li>
                            <a href="{{ $item->resolvedUrl }}" @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif class="text-[16px] text-white/80 transition-colors hover:text-white">{{ $item->label }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </div>

        <div class="lg:col-span-3">
            <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">{{ $locale === 'ar' ? 'التواصل' : 'CONTACT' }}</h3>
            <div class="flex flex-col gap-6">
                <div class="flex items-start gap-4">
                    <span class="mt-1.5 block h-4 w-4 shrink-0 bg-spu-red" aria-hidden="true" style="-webkit-mask: url('/images/icon-map-outline.svg') center / contain no-repeat; mask: url('/images/icon-map-outline.svg') center / contain no-repeat;"></span>
                    <span class="text-[15px] leading-relaxed text-white/80">{{ $locale === 'ar' ? 'مقر الجامعة الرئيسي، أوتوستراد درعا الدولي، بعد بلدة الكسوة، خيارة دنون، دمشق.' : 'University headquarters, Daraa International Highway, past Al-Kiswa, Khayara Danoun, Damascus.' }}</span>
                </div>
                @foreach ($navigation->socialContact->contactLinks as $link)
                    @php($type = strtolower($link->type ?? ''))
                    @php($icon = match ($type) {
                        'phone' => '/images/icon-phone-outline.svg',
                        'email' => '/images/icon-envelope-outline.svg',
                        'address' => '/images/icon-map-outline.svg',
                        default => '/images/icon-university-outline.svg',
                    })
                    <div class="flex items-start gap-4">
                        <span class="mt-1.5 block h-4 w-4 shrink-0 bg-spu-red" aria-hidden="true" style="-webkit-mask: url('{{ $icon }}') center / contain no-repeat; mask: url('{{ $icon }}') center / contain no-repeat;"></span>
                        <span class="text-[15px] leading-relaxed text-white/80 {{ in_array($type, ['phone', 'email'], true) ? 'ltr' : '' }}">{{ $link->label }}: {{ $link->value }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($navigation->footerSettings->mapEmbedUrl)
            <div class="flex flex-col items-start lg:col-span-3 lg:items-end">
                <h3 class="mb-8 w-full text-[18px] font-bold uppercase tracking-widest text-white/50">{{ __('public.campus_map') }}</h3>
                <div class="group h-[180px] w-full overflow-hidden rounded-[12px] border border-white/10 shadow-2xl">
                    <iframe src="{{ $navigation->footerSettings->mapEmbedUrl }}" class="h-full w-full grayscale-[0.3] opacity-80 transition-all duration-700 group-hover:grayscale-0 group-hover:opacity-100" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        @endif
    </div>

    <hr class="mb-8 border-white/10">

    <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
                <p class="text-[14px] text-white/50" translate="no">{{ $locale === 'ar' ? '© 2026 الجامعة السورية الخاصة. التميز في التعليم.' : '© 2026 Syrian Private University. Excellence in Education.' }}</p>
        @if ($navigation->footerSettings->legalLinks !== [])
            <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                @foreach ($navigation->footerSettings->legalLinks as $link)
                    <a href="{{ $link->url }}" @if ($link->target) target="{{ $link->target }}" @endif @if ($link->target === '_blank') rel="noreferrer" @endif class="text-white/50 transition-colors hover:text-white">{{ $link->label }}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>
