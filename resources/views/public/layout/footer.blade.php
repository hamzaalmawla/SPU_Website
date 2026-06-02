@php($homepageFooterPayload = ($homepageFooterSection ?? null)?->payload)

<footer id="site-footer" class="overflow-hidden bg-spu-blue pt-16 pb-8 font-hacen text-white">
    @if ($homepageFooterPayload)
        <div class="container">
            <div class="mb-16 grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-12">
                <div class="flex flex-col items-start lg:col-span-4">
                    @if ($homepageFooterPayload->content['brandBlock']['title'] ?? null)
                        <h2 class="mb-6 text-[24px] font-bold uppercase leading-tight tracking-wider">
                            {{ $homepageFooterPayload->content['brandBlock']['title'] }}
                        </h2>
                    @endif
                    @if ($homepageFooterPayload->content['brandBlock']['body'] ?? null)
                        <p class="mb-8 max-w-[320px] text-[16px] leading-[1.6] text-white/70">
                            {{ $homepageFooterPayload->content['brandBlock']['body'] }}
                        </p>
                    @endif

                    @if ($homepageFooterPayload->socialLinks !== [])
                        <div class="flex items-center gap-6 text-[22px]">
                            @foreach ($homepageFooterPayload->socialLinks as $link)
                                @continue(! ($link->isEnabled ?? true))
                                @php($platform = strtolower($link->platform ?? ''))
                                <a href="{{ $link->url }}" target="_blank" rel="noreferrer" class="text-white/80 transition-all hover:scale-110 hover:text-spu-red" aria-label="{{ $link->platform ?? 'Social' }}">
                                    <i class="fa-brands fa-{{ $platform === 'telegram' ? 'telegram-plane' : $platform }}"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                @foreach ($homepageFooterPayload->footerColumns as $column)
                    <div class="lg:col-span-2">
                        <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">{{ $column->title }}</h3>
                        <ul class="flex flex-col gap-4">
                            @foreach ($column->links as $link)
                                <li>
                                    <a href="{{ $link->url }}"
                                       @if ($link->target) target="{{ $link->target }}" @endif
                                       @if ($link->target === '_blank') rel="noreferrer" @endif
                                       class="text-[16px] text-white/80 transition-colors hover:text-white">
                                        {{ $link->label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                <div class="lg:col-span-3">
                    <h3 class="mb-8 text-[18px] font-bold uppercase tracking-widest text-white/50">
                        {{ $homepageFooterPayload->content['contactBlock']['title'] ?? __('public.contact_heading') }}
                    </h3>
                    <div class="flex flex-col gap-6">
                        @foreach ($homepageFooterPayload->contactLinks as $link)
                            @php($type = strtolower($link->type ?? ''))
                            <div class="flex items-start gap-4">
                                <i class="{{ match ($type) { 'phone' => 'fa-solid fa-phone-alt', 'email' => 'fa-solid fa-envelope', 'address' => 'fa-solid fa-map-marker-alt', default => 'fa-solid fa-university' } }} mt-1.5 text-spu-red"></i>
                                <span class="text-[15px] leading-relaxed text-white/80 {{ in_array($type, ['phone', 'email'], true) ? 'ltr' : '' }}">
                                    {{ $link->label }}: {{ $link->value }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($homepageFooterPayload->content['mapEmbed']['url'] ?? null)
                    <div class="flex flex-col items-start lg:col-span-3 lg:items-end">
                        @if ($homepageFooterPayload->content['mapEmbed']['label'] ?? null)
                            <h3 class="mb-8 w-full text-[18px] font-bold uppercase tracking-widest text-white/50">
                                {{ $homepageFooterPayload->content['mapEmbed']['label'] }}
                            </h3>
                        @endif
                        <div class="group h-[180px] w-full overflow-hidden rounded-[12px] border border-white/10 shadow-2xl">
                            <iframe src="{{ $homepageFooterPayload->content['mapEmbed']['url'] }}" class="h-full w-full grayscale-[0.3] opacity-80 transition-all duration-700 group-hover:grayscale-0 group-hover:opacity-100" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                @endif
            </div>

            <hr class="mb-8 border-white/10">

            <div class="flex flex-col items-center justify-between gap-6 md:flex-row">
                <p class="text-[14px] text-white/50" translate="no">{{ $homepageFooterPayload->content['copyrightText'] ?? $seo->title }}</p>
                @if (!empty($homepageFooterPayload->content['legalLinks'] ?? []))
                    <div class="flex flex-wrap items-center justify-center gap-6 text-[14px]">
                        @foreach ($homepageFooterPayload->content['legalLinks'] as $link)
                            @if (!empty($link['label']) && !empty($link['url']))
                                <a href="{{ $link['url'] }}" class="text-white/50 transition-colors hover:text-white" @if (str_starts_with($link['url'] ?? '', 'http')) target="_blank" rel="noreferrer" @endif>{{ $link['label'] }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @else
        @include('public.layout.footer-fallback')
    @endif
</footer>
