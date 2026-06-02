<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $seo->title }}</title>
        <meta name="robots" content="{{ $seo->robots ?? 'index,follow' }}">
        @if ($seo->metaDescription)
            <meta name="description" content="{{ $seo->metaDescription }}">
        @endif
        <meta property="og:locale" content="{{ $locale }}">
        <meta property="og:title" content="{{ $seo->ogTitle ?? $seo->title }}">
        @if ($seo->ogDescription)
            <meta property="og:description" content="{{ $seo->ogDescription }}">
        @endif
        @if ($seo->ogImage)
            <meta property="og:image" content="{{ $seo->ogImage }}">
        @endif
        @if ($seo->canonicalUrl)
            <link rel="canonical" href="{{ $seo->canonicalUrl }}">
        @endif
        @foreach ($seo->hreflang as $hreflang)
            <link rel="alternate" hreflang="{{ $hreflang['locale'] }}" href="{{ $hreflang['url'] }}">
        @endforeach
        <link rel="preconnect" href="https://fonts.bunny.net">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @elseif (file_exists(public_path('build/manifest.json')))
            @php($viteManifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true))
            @php($cssEntry = $viteManifest['resources/css/app.css'] ?? null)
            @php($jsEntry = $viteManifest['resources/js/app.js'] ?? null)

            @if (is_array($cssEntry) && ! empty($cssEntry['file']))
                <link rel="stylesheet" href="{{ asset('build/'.$cssEntry['file']) }}">
            @endif

            @if (is_array($jsEntry))
                @foreach (($jsEntry['css'] ?? []) as $cssFile)
                    <link rel="stylesheet" href="{{ asset('build/'.$cssFile) }}">
                @endforeach

                @if (! empty($jsEntry['file']))
                    <script type="module" src="{{ asset('build/'.$jsEntry['file']) }}"></script>
                @endif
            @endif
        @endif
    </head>
    <body class="min-h-screen antialiased font-hacen">
        @include('public.layout.preview-banner')
        @include('public.layout.header')

        <main>@yield('content')</main>

        @include('public.layout.footer')
    </body>
</html>
