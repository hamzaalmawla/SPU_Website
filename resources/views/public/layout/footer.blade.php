@php
    $isArabic = $locale === 'ar';
    $footerSettings = $navigation->footerSettings;
    $footerItems = $navigation->footer->items ?? [];
    $socialLinks = $navigation->socialContact->socialLinks ?? [];
    $contactLinks = $navigation->socialContact->contactLinks ?? [];
    $mapEmbedUrl = $footerSettings->mapEmbedUrl ?? null;
    $switchLocale = $isArabic ? 'en' : 'ar';
    $switchUrl = '/' . $switchLocale;
@endphp

<footer id="site-footer" class="overflow-hidden bg-spu-blue pt-16 pb-8 font-hacen text-white">
    <div class="container">
        <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
            <div class="flex flex-col items-start lg:col-span-4">
                <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">
                    {{ $footerSettings->brandTitle }}
                </h2>

                @if ($footerSettings->brandSummary)
                    <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                        {{ $footerSettings->brandSummary }}
                    </p>
                @endif

                @if ($socialLinks !== [])
                    <div class="flex items-center gap-6 text-[22px]">
                        @foreach ($socialLinks as $link)
                            @continue(! ($link->isEnabled ?? true))
                            @php($platform = strtolower($link->platform ?? ''))
                            @php($icon = match ($platform) {
                                'facebook' => '/images/icon-facebook-outline.svg',
                                'instagram' => '/images/icon-instagram-outline.svg',
                                'telegram', 'telegram-plane' => '/images/icon-telegram-outline.svg',
                                'youtube' => '/images/icon-youtube-outline.svg',
                                default => '/images/icon-globe-outline.svg',
                            })
                            <a href="{{ $link->url }}" target="_blank" rel="noreferrer" class="text-white/80 transition-all hover:scale-110 hover:text-spu-red" aria-label="{{ $link->platform ?? 'Social' }}">
                                <img src="{{ $icon }}" alt="" class="h-5 w-5 brightness-0 invert transition-opacity" aria-hidden="true">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($footerItems !== [])
                <div class="lg:col-span-2">
                    <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                        {{ $isArabic ? 'استكشف SPU' : 'EXPLORE SPU' }}
                    </h3>
                    <ul class="flex flex-col gap-4">
                        @foreach ($footerItems as $item)
                            @if ($item->resolvedUrl)
                                <li>
                                    <a href="{{ $item->resolvedUrl }}" @if ($item->openInNewTab) target="_blank" rel="noreferrer" @endif class="text-[16px] text-white/80 transition-colors hover:text-white">
                                        {{ $item->label }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="lg:col-span-3">
                <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                    {{ $isArabic ? 'التواصل' : 'CONTACT' }}
                </h3>
                <div class="flex flex-col gap-6">
                    @if ($footerSettings->address)
                        <div class="flex items-start gap-4">
                            <img src="/images/icon-map-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="text-[15px] leading-relaxed text-white/80">
                                {{ $footerSettings->address }}
                            </span>
                        </div>
                    @endif

                    @if ($footerSettings->phone)
                        <div class="flex items-start gap-4">
                            <img src="/images/icon-phone-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="ltr text-[15px] leading-relaxed text-white/80">{{ $footerSettings->phone }}</span>
                        </div>
                    @endif

                    @if ($footerSettings->email)
                        <div class="flex items-start gap-4">
                            <img src="/images/icon-envelope-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="ltr text-[15px] leading-relaxed text-white/80">{{ $footerSettings->email }}</span>
                        </div>
                    @endif

                    @foreach ($contactLinks as $link)
                        @php($type = strtolower($link->type ?? ''))
                        @php($icon = match ($type) {
                            'phone' => '/images/icon-phone-outline.svg',
                            'email' => '/images/icon-envelope-outline.svg',
                            'address' => '/images/icon-map-outline.svg',
                            default => '/images/icon-university-outline.svg',
                        })
                        <div class="flex items-start gap-4">
                            <img src="{{ $icon }}" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="text-[15px] leading-relaxed text-white/80 {{ in_array($type, ['phone', 'email'], true) ? 'ltr' : '' }}">{{ $link->label }}: {{ $link->value }}</span>
                        </div>
                    @endforeach
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
                {{ $footerSettings->copyrightText }}
            </p>

            <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                @foreach ($footerSettings->legalLinks as $link)
                    <a href="{{ $link->url }}" @if ($link->target) target="{{ $link->target }}" @endif @if ($link->target === '_blank') rel="noreferrer" @endif class="text-white/50 transition-colors hover:text-white">{{ $link->label }}</a>
                @endforeach
                <a href="{{ $switchUrl }}" class="text-white/50 transition-colors hover:text-white">
                    {{ $isArabic ? 'English' : 'العربية' }}
                </a>
            </div>
        </div>
    </div>
</footer>
