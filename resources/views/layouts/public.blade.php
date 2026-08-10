<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
    <head>
        <script>document.documentElement.classList.add('js');</script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $seo->title }}</title>
        <meta name="robots" content="{{ ($isPreview ?? false) ? 'noindex,nofollow,noarchive' : ($seo->robots ?? 'index,follow') }}">
        @if ($seo->metaDescription)
            <meta name="description" content="{{ $seo->metaDescription }}">
        @endif
        <meta property="og:locale" content="{{ $locale }}">
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Syrian Private University') }}">
        @if ($seo->canonicalUrl)<meta property="og:url" content="{{ $seo->canonicalUrl }}">@endif
        <meta property="og:title" content="{{ $seo->ogTitle ?? $seo->title }}">
        @if ($seo->ogDescription)
            <meta property="og:description" content="{{ $seo->ogDescription }}">
        @endif
        @if ($seo->ogImage)
            <meta property="og:image" content="{{ str_starts_with($seo->ogImage, 'http://') || str_starts_with($seo->ogImage, 'https://') ? $seo->ogImage : url($seo->ogImage) }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo->ogTitle ?? $seo->title }}">
        @if ($seo->ogDescription)<meta name="twitter:description" content="{{ $seo->ogDescription }}">@endif
        @if ($seo->ogImage)<meta name="twitter:image" content="{{ str_starts_with($seo->ogImage, 'http://') || str_starts_with($seo->ogImage, 'https://') ? $seo->ogImage : url($seo->ogImage) }}">@endif
        @if ($seo->canonicalUrl)
            <link rel="canonical" href="{{ $seo->canonicalUrl }}">
        @endif
        @foreach ($seo->hreflang as $hreflang)
            <link rel="alternate" hreflang="{{ $hreflang['locale'] }}" href="{{ $hreflang['url'] }}">
        @endforeach
        @if (isset($structuredData) && is_array($structuredData))
            <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif
        @if (isset($citationMeta) && is_array($citationMeta))
            @foreach ($citationMeta as $name => $content)
                @if (is_string($name) && is_scalar($content) && (string) $content !== '')
                    <meta name="{{ $name }}" content="{{ $content }}">
                @endif
            @endforeach
        @endif
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="icon" href="{{ asset('images/single-logo.png') }}" type="image/png">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        @php
            $publicViteJsEntries = ['resources/js/app.js'];
            $requestedPublicViteJsEntries = trim($__env->yieldContent('publicViteJsEntries'));

            if ($requestedPublicViteJsEntries !== '') {
                $decodedPublicViteJsEntries = json_decode($requestedPublicViteJsEntries, true);

                if (is_array($decodedPublicViteJsEntries)) {
                    $publicViteJsEntries = array_values(array_filter(
                        $decodedPublicViteJsEntries,
                        static fn (mixed $entry): bool => is_string($entry) && $entry !== '',
                    ));
                }
            }

            if (! in_array('resources/js/app.js', $publicViteJsEntries, true)) {
                $publicViteJsEntries[] = 'resources/js/app.js';
            }

            $publicViteEntries = array_merge(['resources/css/app.css'], $publicViteJsEntries);
        @endphp
        @if (file_exists(public_path('hot')))
            @vite($publicViteEntries)
        @elseif (file_exists(public_path('build/manifest.json')))
            @php($viteManifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true))
            @php($cssEntry = $viteManifest['resources/css/app.css'] ?? null)
            @php($loadedViteCss = [])

            @if (is_array($cssEntry) && ! empty($cssEntry['file']))
                <link rel="stylesheet" href="{{ asset('build/'.$cssEntry['file']) }}">
                @php($loadedViteCss[] = $cssEntry['file'])
            @endif

            @foreach ($publicViteJsEntries as $publicViteJsEntry)
                @php($jsEntry = $viteManifest[$publicViteJsEntry] ?? null)

                @if (is_array($jsEntry))
                    @foreach (($jsEntry['css'] ?? []) as $cssFile)
                        @continue(in_array($cssFile, $loadedViteCss, true))
                        <link rel="stylesheet" href="{{ asset('build/'.$cssFile) }}">
                        @php($loadedViteCss[] = $cssFile)
                    @endforeach

                    @if (! empty($jsEntry['file']))
                        <script type="module" src="{{ asset('build/'.$jsEntry['file']) }}"></script>
                    @endif
                @endif
            @endforeach
        @endif
        @stack('styles')
        @stack('head')
    </head>
    <body class="min-h-screen antialiased font-hacen">
        <a class="skip-link" href="#main-content">{{ $locale === 'ar' ? 'انتقل إلى المحتوى الرئيسي' : 'Skip to main content' }}</a>
        @include('public.layout.preview-banner')
        @include('public.layout.header')

        <main id="main-content" tabindex="-1">@yield('content')</main>

        @include('public.layout.footer')
        @stack('scripts')
    </body>
</html>
