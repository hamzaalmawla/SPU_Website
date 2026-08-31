{{--
    Shared error page body.

    Renders from the ErrorPageContentDTO only — no navigation, no settings, no
    translator, no database. The same markup is used by the standalone shell
    (500/503, and any degraded render) and by the full-layout shell
    (403/404/419/429), so the two can never drift apart.

    Both languages are always shown, active locale first, because the site is
    bilingual and an error page cannot know which language the reader needs.
--}}
@php
    $arabicPane = ['locale' => 'ar', 'dir' => 'rtl', 'title' => $error->arabicTitle, 'message' => $error->arabicMessage];
    $englishPane = ['locale' => 'en', 'dir' => 'ltr', 'title' => $error->englishTitle, 'message' => $error->englishMessage];
    $panes = $error->locale === 'ar' ? [$arabicPane, $englishPane] : [$englishPane, $arabicPane];
    $sectionLinks = array_values(array_filter($error->links, static fn ($link): bool => ! $link->isPrimary));
@endphp
<div class="spu-error">
    <div class="spu-error__mark">
        {{-- Drawn inline so SPU branding survives a missing or unbuilt asset. --}}
        <svg viewBox="0 0 84 84" role="img" aria-label="SPU" focusable="false">
            <circle cx="42" cy="42" r="40" fill="#202759"></circle>
            <text x="42" y="53" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="27" font-weight="700" fill="#caa949">SPU</text>
        </svg>
        {{-- Layered on top of the monogram; an empty alt keeps it decorative
             so a missing file degrades silently instead of showing alt text. --}}
        <img src="{{ $error->logoUrl }}" alt="" width="84" height="84" decoding="async">
    </div>

    <p class="spu-error__code">{{ $error->status }}</p>
    <hr class="spu-error__rule">

    @foreach ($panes as $index => $pane)
        <div class="spu-error__pane @if ($index > 0) spu-error__pane--alt @endif" lang="{{ $pane['locale'] }}" dir="{{ $pane['dir'] }}">
            @if ($index === 0)
                <h1>{{ $pane['title'] }}</h1>
            @else
                <h2>{{ $pane['title'] }}</h2>
            @endif
            <p>{{ $pane['message'] }}</p>
        </div>
    @endforeach

    <div class="spu-error__actions">
        <a class="spu-error__button" href="{{ $error->homeUrl }}">
            {{ $error->locale === 'ar' ? 'العودة إلى الرئيسية' : 'Back to homepage' }}
        </a>
        @if ($error->searchUrl !== null)
            <a class="spu-error__button spu-error__button--ghost" href="{{ $error->searchUrl }}">
                {{ $error->locale === 'ar' ? 'ابحث في الموقع' : 'Search the site' }}
            </a>
        @endif
    </div>

    @if ($sectionLinks !== [])
        <nav class="spu-error__links" aria-label="{{ $error->locale === 'ar' ? 'روابط بديلة' : 'Alternative links' }}">
            <h2>{{ $error->locale === 'ar' ? 'أقسام الموقع' : 'Site sections' }}</h2>
            <ul>
                @foreach ($sectionLinks as $link)
                    <li><a href="{{ $link->url }}">{{ $link->label }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
