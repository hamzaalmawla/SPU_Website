{{--
    Shared error page body.

    Renders from the ErrorPageContentDTO only — no navigation, no settings, no
    translator, no database. The same markup is used by the standalone shell
    (500/503, and any degraded render) and by the full-layout shell
    (403/404/419/429), so the two can never drift apart.

    Layout follows the site's page heroes: a navy hero with a gold accent (the
    public header floats over it in the full layout), then a light band with
    the site sections. Both languages are always shown, active locale first,
    because an error page cannot know which language the reader needs.

    $shell is 'full' (inside layouts.public, under the fixed header) or
    'standalone' (own document, with its own slim brand bar).
--}}
@php
    $shell = ($shell ?? 'standalone') === 'full' ? 'full' : 'standalone';
    $isArabic = $error->locale === 'ar';
    $arabicPane = ['locale' => 'ar', 'dir' => 'rtl', 'title' => $error->arabicTitle, 'message' => $error->arabicMessage];
    $englishPane = ['locale' => 'en', 'dir' => 'ltr', 'title' => $error->englishTitle, 'message' => $error->englishMessage];
    [$primaryPane, $altPane] = $isArabic ? [$arabicPane, $englishPane] : [$englishPane, $arabicPane];
    $sectionLinks = array_values(array_filter($error->links, static fn ($link): bool => ! $link->isPrimary));
    $digits = str_split((string) $error->status);
@endphp
<div class="spu-error spu-error--{{ $shell }}" data-status="{{ $error->status }}">
    <section class="spu-error__hero" aria-labelledby="spu-error-title">
        <div class="spu-error__ambient" aria-hidden="true"></div>
        <div class="spu-error__pattern" aria-hidden="true"></div>

        <div class="spu-error__inner">
            <div class="spu-error__copy" lang="{{ $primaryPane['locale'] }}" dir="{{ $primaryPane['dir'] }}">
                <p class="spu-error__eyebrow">
                    <span class="spu-error__eyebrow-line" aria-hidden="true"></span>
                    <span>{{ $isArabic ? 'خطأ' : 'Error' }} {{ $error->status }}</span>
                </p>

                <h1 id="spu-error-title" class="spu-error__title">{{ $primaryPane['title'] }}</h1>
                <p class="spu-error__lead">{{ $primaryPane['message'] }}</p>

                <div class="spu-error__actions">
                    <a class="spu-error__button" href="{{ $error->homeUrl }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                        <span>{{ $isArabic ? 'العودة إلى الرئيسية' : 'Back to homepage' }}</span>
                    </a>
                    @if ($error->searchUrl !== null)
                        <a class="spu-error__button spu-error__button--ghost" href="{{ $error->searchUrl }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            <span>{{ $isArabic ? 'ابحث في الموقع' : 'Search the site' }}</span>
                        </a>
                    @endif
                </div>

                <div class="spu-error__alt" lang="{{ $altPane['locale'] }}" dir="{{ $altPane['dir'] }}">
                    <h2>{{ $altPane['title'] }}</h2>
                    <p>{{ $altPane['message'] }}</p>
                </div>
            </div>

            <div class="spu-error__visual" aria-hidden="true">
                <div class="spu-error__mark">
                    {{-- The logo is embedded (errors/partials/logo-uri, a 160px WebP made
                         from public/images/single-logo.png) so SPU branding never depends
                         on a file, the asset build or a second request. --}}
                    <img src="@include('errors.partials.logo-uri')" alt="" width="160" height="136" decoding="async">
                </div>
                <p class="spu-error__code" dir="ltr">
                    @foreach ($digits as $digit)<span>{{ $digit }}</span>@endforeach
                </p>
                <span class="spu-error__rule"></span>
            </div>
        </div>
    </section>

    @if ($sectionLinks !== [])
        <nav class="spu-error__sections" aria-labelledby="spu-error-sections-title">
            <div class="spu-error__sections-inner">
                <p class="spu-error__sections-kicker">{{ $isArabic ? 'تابع التصفح' : 'Keep exploring' }}</p>
                <h2 id="spu-error-sections-title">{{ $isArabic ? 'أقسام الموقع' : 'Site sections' }}</h2>
                <ul>
                    @foreach ($sectionLinks as $link)
                        <li>
                            <a href="{{ $link->url }}">
                                <span>{{ $link->label }}</span>
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14m-5-5 5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
    @endif
</div>
