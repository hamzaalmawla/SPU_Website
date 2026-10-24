{{-- Standalone page for staff intake links: no site navigation, never indexed. --}}
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex,nofollow,noarchive">
        <meta name="referrer" content="no-referrer">
        <title>{{ __('staff_intake.public.page_title', [], $locale) }} | {{ __('staff_intake.public.university', [], $locale) }}</title>
        <link rel="icon" href="{{ asset('images/single-logo.png') }}" type="image/png">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/staffIntake.js'])
        @elseif (file_exists(public_path('build/manifest.json')))
            @php($viteManifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true))
            @foreach (['resources/css/app.css', 'resources/js/staffIntake.js'] as $viteEntry)
                @php($entry = $viteManifest[$viteEntry] ?? null)
                @if (is_array($entry) && ! empty($entry['file']))
                    @if (str_ends_with($entry['file'], '.css'))
                        <link rel="stylesheet" href="{{ asset('build/'.$entry['file']) }}">
                    @else
                        <script type="module" src="{{ asset('build/'.$entry['file']) }}"></script>
                    @endif
                @endif
            @endforeach
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 font-hacen text-slate-800">
        <header class="bg-spu-blue text-white">
            <div class="mx-auto flex max-w-4xl items-center gap-4 px-4 py-5">
                <img src="{{ asset('images/single-logo.png') }}" alt="" class="h-12 w-12 rounded-full bg-white p-1">
                <div>
                    <p class="text-sm opacity-80">{{ __('staff_intake.public.university', [], $locale) }}</p>
                    <h1 class="text-xl font-bold sm:text-2xl">{{ __('staff_intake.public.page_title', [], $locale) }}</h1>
                </div>
            </div>
        </header>
        <main id="main-content" class="mx-auto max-w-4xl px-4 py-8">
            @yield('content')
        </main>
    </body>
</html>
