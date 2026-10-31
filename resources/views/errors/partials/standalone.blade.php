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

    The only external reference is the logo <img>, layered over an inline SVG
    monogram so branding survives even when the asset is gone.
--}}
<!DOCTYPE html>
<html lang="{{ $error->locale }}" dir="{{ $error->direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $error->status }} — {{ $error->title }}</title>
    <link rel="icon" href="{{ $error->logoUrl }}" type="image/png">
    <style>
        html {
            background: #f5f6fa;
        }

        body {
            margin: 0;
            background: #f5f6fa;
            -webkit-text-size-adjust: 100%;
        }
    </style>
    @include('errors.partials.styles')
</head>
<body>
    @include('errors.partials.body', ['error' => $error])
</body>
</html>
