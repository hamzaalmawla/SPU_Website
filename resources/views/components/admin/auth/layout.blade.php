@php
    $locale = $adminLocale ?? app()->getLocale();
    $direction = $adminDirection ?? ($locale === 'ar' ? 'rtl' : 'ltr');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('admin.auth.panel_title') }}</title>
    <style>
        :root {
            --spu-blue: #082b5f;
            --spu-blue-2: #0d3f83;
            --spu-red: #b4232a;
            --spu-gold: #d6a342;
            --spu-ink: #172033;
            --spu-muted: #667085;
            --spu-border: #d9e2ef;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Inter", "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--spu-ink);
            background: radial-gradient(circle at top left, rgba(214, 163, 66, 0.22), transparent 32rem), linear-gradient(135deg, #071f49 0%, #0a316b 46%, #f4f7fb 46.2%, #ffffff 100%);
        }
        [dir="rtl"] body {
            font-family: Tahoma, Arial, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top right, rgba(214, 163, 66, 0.22), transparent 32rem), linear-gradient(225deg, #071f49 0%, #0a316b 46%, #f4f7fb 46.2%, #ffffff 100%);
        }
        .auth-shell { display: grid; min-height: 100vh; grid-template-columns: minmax(0, 1fr) minmax(22rem, 30rem); gap: clamp(2rem, 6vw, 5rem); align-items: center; padding: clamp(1.25rem, 4vw, 4rem); }
        .brand-panel { color: #fff; max-width: 42rem; }
        .brand-mark { display: inline-flex; align-items: center; gap: 1rem; padding: .75rem 1rem; border: 1px solid rgba(255, 255, 255, .18); border-radius: 1.25rem; background: rgba(255, 255, 255, .08); backdrop-filter: blur(12px); }
        .brand-mark img { width: 4rem; height: 4rem; object-fit: contain; }
        .brand-kicker { margin: 0; color: #f4d58c; font-size: .83rem; letter-spacing: .12em; text-transform: uppercase; }
        .brand-name { margin: .15rem 0 0; font-size: 1.05rem; font-weight: 800; }
        .brand-panel h1 { margin: 2.4rem 0 1rem; font-size: clamp(2.1rem, 5vw, 4.7rem); line-height: 1; letter-spacing: -.045em; }
        [dir="rtl"] .brand-panel h1 { letter-spacing: 0; line-height: 1.12; }
        .brand-panel p { max-width: 34rem; margin: 0; color: rgba(255, 255, 255, .78); font-size: 1.05rem; line-height: 1.8; }
        .auth-card { position: relative; overflow: hidden; border: 1px solid rgba(8, 43, 95, .12); border-radius: 1.65rem; background: rgba(255, 255, 255, .94); box-shadow: 0 1.5rem 4rem rgba(8, 24, 54, .22); }
        .auth-card::before { content: ""; display: block; height: .35rem; background: linear-gradient(90deg, var(--spu-red), var(--spu-gold), var(--spu-blue)); }
        .auth-card-inner { padding: clamp(1.35rem, 4vw, 2.25rem); }
        .locale-switcher { display: flex; gap: .5rem; justify-content: flex-end; margin-bottom: 1.25rem; }
        .locale-switcher form { margin: 0; }
        .locale-switcher button { cursor: pointer; padding: .42rem .72rem; border: 1px solid var(--spu-border); border-radius: 999px; background: #fff; color: var(--spu-blue); font-size: .83rem; font-weight: 800; text-decoration: none; }
        .locale-switcher button[aria-current="true"] { background: var(--spu-blue); color: #fff; border-color: var(--spu-blue); }
        .form-heading { margin: 0 0 .45rem; color: var(--spu-blue); font-size: 1.75rem; line-height: 1.15; }
        .form-copy { margin: 0 0 1.4rem; color: var(--spu-muted); line-height: 1.7; }
        .alert { margin-bottom: 1rem; padding: .85rem 1rem; border: 1px solid #fecaca; border-radius: .95rem; background: #fff1f2; color: #991b1b; font-size: .92rem; }
        .field { margin-top: 1rem; }
        .field label { display: block; margin-bottom: .45rem; color: #24324a; font-size: .9rem; font-weight: 800; }
        .field input[type="email"], .field input[type="password"], .field input[type="text"], .field input[type="url"], .field select { width: 100%; min-height: 3.15rem; border: 1px solid var(--spu-border); border-radius: .95rem; padding: .85rem 1rem; color: var(--spu-ink); font-size: 1rem; outline: none; transition: border-color .18s ease, box-shadow .18s ease; background: #fff; }
        .field input:focus, .field select:focus { border-color: var(--spu-gold); box-shadow: 0 0 0 .22rem rgba(214, 163, 66, .2); }
        .field-error { margin: .4rem 0 0; color: #b4232a; font-size: .83rem; }
        .form-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin: 1rem 0 1.35rem; }
        .remember { display: inline-flex; align-items: center; gap: .55rem; color: #344054; font-size: .92rem; font-weight: 700; }
        .remember input { width: 1rem; height: 1rem; accent-color: var(--spu-blue); }
        .primary-button { width: 100%; min-height: 3.15rem; border: 0; border-radius: 1rem; background: linear-gradient(135deg, var(--spu-blue), var(--spu-blue-2)); color: #fff; cursor: pointer; font-size: 1rem; font-weight: 900; box-shadow: 0 .85rem 1.6rem rgba(8, 43, 95, .28); }
        .primary-button:focus { outline: .22rem solid rgba(214, 163, 66, .32); outline-offset: .15rem; }
        .security-note { margin: 1.25rem 0 0; color: var(--spu-muted); font-size: .82rem; line-height: 1.65; }
        @media (max-width: 900px) { body { background: linear-gradient(180deg, #071f49 0%, #0a316b 42%, #f4f7fb 42.2%, #ffffff 100%); } .auth-shell { grid-template-columns: 1fr; gap: 1.5rem; align-items: start; } .brand-panel h1 { margin-top: 1.4rem; } }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="brand-panel" aria-label="{{ __('admin.auth.brand') }}">
            <div class="brand-mark">
                <img src="{{ asset('images/logo-spu.png') }}" alt="{{ __('admin.auth.logo_alt') }}">
                <div>
                    <p class="brand-kicker">SPU CMS</p>
                    <p class="brand-name">{{ __('admin.auth.university_name') }}</p>
                </div>
            </div>
            <h1>{{ __('admin.auth.hero_title') }}</h1>
            <p>{{ __('admin.auth.hero_body') }}</p>
        </section>

        <section class="auth-card">
            <div class="auth-card-inner">
                <nav class="locale-switcher" aria-label="{{ __('admin.auth.language') }}">
                    <form method="POST" action="{{ route('admin.locale', ['locale' => 'ar']) }}">
                        @csrf
                        <button type="submit" aria-current="{{ $locale === 'ar' ? 'true' : 'false' }}">العربية</button>
                    </form>
                    <form method="POST" action="{{ route('admin.locale', ['locale' => 'en']) }}">
                        @csrf
                        <button type="submit" aria-current="{{ $locale === 'en' ? 'true' : 'false' }}">English</button>
                    </form>
                </nav>

                {{ $slot }}
            </div>
        </section>
    </main>
</body>
</html>
