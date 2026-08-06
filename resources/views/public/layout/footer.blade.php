@php
    $isArabic = $locale === 'ar';
    $homepageFooterPayload = $homepageFooterSection?->payload ?? null;
    $homepageFooterContent = is_array($homepageFooterPayload?->content ?? null) ? $homepageFooterPayload->content : [];
    $homepageBrandBlock = is_array($homepageFooterContent['brandBlock'] ?? null) ? $homepageFooterContent['brandBlock'] : [];
    $useHomepageFooter = $homepageFooterPayload !== null && ($homepageFooterSection?->isEnabled ?? false);
    $footerSettings = $navigation->footerSettings;
    $footerItems = $navigation->footer->items ?? [];
    $socialLinks = $navigation->socialContact->socialLinks ?? [];
    $contactLinks = $navigation->socialContact->contactLinks ?? [];
    $mapEmbedUrl = $footerSettings->mapEmbedUrl ?? null;

    if ($useHomepageFooter) {
        $socialLinks = $homepageFooterPayload->socialLinks;
        $contactLinks = $homepageFooterPayload->contactLinks;
    }

    $footerBrandTitle = $useHomepageFooter
        ? ($homepageBrandBlock['title'] ?? $homepageFooterPayload->title ?? $footerSettings->brandTitle)
        : $footerSettings->brandTitle;
    $footerBrandSummary = $useHomepageFooter
        ? ($homepageFooterPayload->summary ?? $homepageBrandBlock['summary'] ?? null)
        : $footerSettings->brandSummary;
    $footerLogo = $useHomepageFooter
        ? ($homepageBrandBlock['logoUrl'] ?? $homepageFooterContent['logo'] ?? null)
        : null;
    $footerCopyrightText = $useHomepageFooter
        ? ($homepageFooterContent['copyrightText'] ?? $footerSettings->copyrightText)
        : $footerSettings->copyrightText;
    $footerLegalLinks = $useHomepageFooter
        ? (is_array($homepageFooterContent['legalLinks'] ?? null) ? $homepageFooterContent['legalLinks'] : [])
        : $footerSettings->legalLinks;
@endphp

<footer id="site-footer" class="overflow-hidden bg-spu-blue pt-16 pb-8 font-hacen text-white">
    <div class="container">
        <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
            <div class="flex flex-col items-start lg:col-span-4">
                @if ($footerLogo)
                    <img src="{{ $footerLogo }}" alt="" class="mb-5 h-12 w-auto brightness-0 invert" aria-hidden="true">
                @endif
                <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">{{ $footerBrandTitle }}</h2>

                @if ($footerBrandSummary)
                    <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                        {{ $footerBrandSummary }}
                    </p>
                @endif

                @if ($socialLinks !== [])
                    <div class="flex items-center gap-6 text-[22px]">
                        @foreach ($socialLinks as $link)
                            @continue(! ($link->isEnabled ?? true))
                            @php
                                $platform = strtolower($link->platform ?? '');
                                $icon = match ($platform) {
                                    'facebook' => '/images/icon-facebook-outline.svg',
                                    'instagram' => '/images/icon-instagram-outline.svg',
                                    'telegram', 'telegram-plane' => '/images/icon-telegram-outline.svg',
                                    'youtube' => '/images/icon-youtube-outline.svg',
                                    default => '/images/icon-globe-outline.svg',
                                };
                            @endphp
                            <a href="{{ $link->url }}" target="_blank" rel="noreferrer" class="text-white/80 transition-all hover:scale-110 hover:text-spu-red" aria-label="{{ $link->platform ?? 'Social' }}">
                                <img src="{{ $icon }}" alt="" class="h-5 w-5 brightness-0 invert transition-opacity" aria-hidden="true">
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($useHomepageFooter && $homepageFooterPayload->footerColumns !== [])
                @foreach ($homepageFooterPayload->footerColumns as $column)
                    <div class="lg:col-span-2">
                        <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">{{ $column->title }}</h3>
                        <ul class="flex flex-col gap-4">
                            @foreach ($column->links as $link)
                                <li><a href="{{ $link->url }}" class="text-[16px] text-white/80 transition-colors hover:text-white">{{ $link->label }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif

            @if (! $useHomepageFooter && $footerItems !== [])
                <div class="lg:col-span-2">
                    <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                        {{ __('public.footer_explore') }}
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
                    {{ __('public.footer_contact') }}
                </h3>
                <div class="flex flex-col gap-6">
                    @if (! $useHomepageFooter && $footerSettings->address)
                        <div class="flex items-start gap-4">
                            <img src="/images/icon-map-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="text-[15px] leading-relaxed text-white/80">
                                {{ $footerSettings->address }}
                            </span>
                        </div>
                    @endif

                    @if (! $useHomepageFooter && $footerSettings->phone)
                        <div class="flex items-start gap-4">
                            <img src="/images/icon-phone-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="ltr text-[15px] leading-relaxed text-white/80">{{ $footerSettings->phone }}</span>
                        </div>
                    @endif

                    @if (! $useHomepageFooter && $footerSettings->email)
                        <div class="flex items-start gap-4">
                            <img src="/images/icon-envelope-outline.svg" alt="" class="mt-1.5 h-4 w-4 shrink-0 brightness-0 invert" aria-hidden="true">
                            <span class="ltr text-[15px] leading-relaxed text-white/80">{{ $footerSettings->email }}</span>
                        </div>
                    @endif

                    @foreach ($contactLinks as $link)
                        @php
                            $type = strtolower($link->type ?? '');
                            $icon = match ($type) {
                                'phone' => '/images/icon-phone-outline.svg',
                                'email' => '/images/icon-envelope-outline.svg',
                                'address' => '/images/icon-map-outline.svg',
                                default => '/images/icon-university-outline.svg',
                            };
                        @endphp
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
                        {{ __('public.footer_location') }}
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
                {{ $footerCopyrightText }}
            </p>

            <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                @foreach ($footerLegalLinks as $link)
                    @php
                        $legalUrl = is_array($link) ? ($link['url'] ?? '') : ($link->url ?? '');
                        $legalLabel = is_array($link) ? ($link['label'] ?? '') : ($link->label ?? '');
                        $legalTarget = is_array($link) ? ($link['target'] ?? null) : ($link->target ?? null);
                    @endphp
                    @if ($legalUrl !== '')
                        <a href="{{ $legalUrl }}" @if ($legalTarget) target="{{ $legalTarget }}" @endif @if ($legalTarget === '_blank') rel="noreferrer" @endif class="text-white/50 transition-colors hover:text-white">{{ $legalLabel }}</a>
                    @endif
                @endforeach
                @foreach ($languageSwitch as $switchLink)
                    @if (! $switchLink->isCurrent)
                        <a href="{{ $switchLink->url }}" class="text-white/50 transition-colors hover:text-white" data-language-switch>
                            {{ $switchLink->label }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</footer>
