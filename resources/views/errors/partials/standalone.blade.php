{{--
    Self-contained error document.

    This shell is what 500 and 503 always render, and what 403/404/419/429 fall
    back to when the public layout cannot be built. It deliberately depends on
    NOTHING beyond the ErrorPageContentDTO handed to it:

      * no @extends / layouts.public   — the layout needs navigation + settings
      * no @vite / build manifest      — assets may be missing or unbuilt
      * no __() / translator           — copy is carried on the DTO
      * no navigation, settings, CMS   — those need the database and the cache
      * no analytics                   — an outage page must not phone home

    The only external references are plain public files (the header logo and
    the Hacen Tunisia fonts); each degrades to text or system fonts if missing.
    The hero emblem is embedded in the page, so the branding never breaks.
--}}
@php
    $isArabic = $error->locale === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $error->locale }}" dir="{{ $error->direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#202759">
    <title>{{ $error->status }} — {{ $error->title }} | {{ $isArabic ? 'الجامعة السورية الخاصة' : 'Syrian Private University' }}</title>
    <link rel="icon" href="{{ $error->logoUrl }}" type="image/png">
    <style @cspNonce>
        html {
            background: #f4f5f8;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            min-height: 100svh;
            margin: 0;
            background: #f4f5f8;
            -webkit-text-size-adjust: 100%;
        }

        .spu-error-bar {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem clamp(1rem, 4vw, 2.5rem);
            background: #ffffff;
            border-bottom: 3px solid #caa949;
            box-shadow: 0 6px 20px rgba(32, 39, 89, 0.08);
            font-family: "SPU Error Hacen", "Segoe UI", Tahoma, Arial, sans-serif;
        }

        .spu-error-bar a {
            display: inline-flex;
            align-items: center;
            color: #202759;
            text-decoration: none;
            font-weight: 700;
        }

        .spu-error-bar img {
            display: block;
            width: auto;
            height: 44px;
        }

        .spu-error-bar a:focus-visible {
            outline: 3px solid #caa949;
            outline-offset: 4px;
            border-radius: 6px;
        }

        .spu-error-main {
            display: flex;
            flex: 1 0 auto;
            flex-direction: column;
        }

        .spu-error-main > .spu-error {
            display: flex;
            flex: 1 0 auto;
            flex-direction: column;
        }

        .spu-error-main .spu-error__hero {
            display: flex;
            flex: 1 0 auto;
            align-items: center;
        }

        .spu-error-foot {
            padding: 1rem clamp(1rem, 4vw, 2.5rem);
            background: #0b1030;
            color: rgba(255, 255, 255, 0.62);
            font-family: "SPU Error Hacen", "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 0.85rem;
            text-align: center;
        }
    </style>
    @include('errors.partials.styles')
</head>
<body>
    <header class="spu-error-bar">
        <a href="{{ $error->homeUrl }}">
            <img src="/images/logo-spu.png" alt="{{ $isArabic ? 'الجامعة السورية الخاصة' : 'Syrian Private University' }}" width="210" height="44" decoding="async">
        </a>
    </header>

    <main class="spu-error-main" id="main-content">
        @include('errors.partials.body', ['error' => $error, 'shell' => 'standalone'])
    </main>

    <footer class="spu-error-foot">
        <span lang="ar" dir="rtl">الجامعة السورية الخاصة</span>
        <span aria-hidden="true"> · </span>
        <span lang="en" dir="ltr">Syrian Private University</span>
    </footer>
</body>
</html>
