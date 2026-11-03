{{--
    Critical CSS for every SPU error page.

    Deliberately self-contained: no @vite, no Tailwind. A 500 or 503 can be
    rendered while the asset build is missing or the CDN is unreachable, so the
    page must be legible from this block alone. The Hacen Tunisia faces load
    from /fonts (a plain public file, not the build) and fall back to system
    fonts if they are missing.

    The look follows the site's identity: navy page hero (#202759) with the
    gold accent used on dark surfaces (#e2b864), pill buttons, and the brand
    red (#6f1616) for hover on light surfaces. Layout uses logical properties
    so the same rules are correct in RTL and LTR.
--}}
<style>
    @font-face {
        font-family: "SPU Error Hacen";
        src: url("/fonts/Hacen Tunisia Regular.ttf") format("truetype");
        font-weight: 400;
        font-display: swap;
        unicode-range: U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF;
    }

    @font-face {
        font-family: "SPU Error Hacen";
        src: url("/fonts/Hacen Tunisia Bold Regular.ttf") format("truetype");
        font-weight: 700;
        font-display: swap;
        unicode-range: U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF;
    }

    .spu-error {
        --spu-navy: #202759;
        --spu-navy-deep: #151b43;
        --spu-night: #0b1030;
        --spu-red: #6f1616;
        --spu-gold: #e2b864;
        --spu-gold-strong: #caa949;
        --spu-ink: #20243a;
        --spu-muted: #5b6178;
        --spu-line: #e1e4ec;
        box-sizing: border-box;
        width: 100%;
        color: var(--spu-ink);
        font-family: "SPU Error Hacen", "HacenTunisia", "Segoe UI", Tahoma, Arial, sans-serif;
        line-height: 1.6;
        background: #f4f5f8;
    }

    .spu-error *,
    .spu-error *::before,
    .spu-error *::after {
        box-sizing: border-box;
    }

    /* ---------- Hero ---------- */

    .spu-error__hero {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        color: #ffffff;
        background:
            linear-gradient(160deg, var(--spu-navy) 0%, var(--spu-navy-deep) 55%, var(--spu-night) 100%);
    }

    /* Under the public layout the fixed header floats over the hero, exactly as
       on every other page; the padding matches .page-hero. */
    .spu-error--full .spu-error__hero {
        padding-block: clamp(8rem, 13vw, 11rem) clamp(4rem, 8vw, 6.5rem);
    }

    .spu-error--standalone .spu-error__hero {
        padding-block: clamp(3rem, 7vw, 5.5rem) clamp(3.5rem, 8vw, 6rem);
    }

    .spu-error__ambient,
    .spu-error__pattern {
        position: absolute;
        inset: 0;
        z-index: -1;
        pointer-events: none;
    }

    .spu-error__ambient {
        background:
            radial-gradient(circle at 80% 22%, rgba(226, 184, 100, 0.22) 0%, rgba(226, 184, 100, 0) 34%),
            radial-gradient(circle at 12% 88%, rgba(43, 125, 179, 0.20) 0%, rgba(43, 125, 179, 0) 38%);
    }

    [dir="rtl"] .spu-error__ambient {
        transform: scaleX(-1);
    }

    .spu-error__pattern {
        opacity: 0.5;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.045) 1px, transparent 1px);
        background-size: 56px 56px;
        -webkit-mask-image: radial-gradient(ellipse at center, #000 20%, transparent 75%);
        mask-image: radial-gradient(ellipse at center, #000 20%, transparent 75%);
    }

    .spu-error__inner {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
        align-items: center;
        gap: clamp(2rem, 5vw, 4.5rem);
        width: 100%;
        max-width: 1200px;
        margin-inline: auto;
        padding-inline: clamp(1rem, 4vw, 2.5rem);
    }

    .spu-error__copy {
        text-align: start;
    }

    .spu-error__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.85rem;
        margin: 0 0 1.4rem;
        padding: 0.6rem 1rem;
        border: 1px solid rgba(255, 255, 255, 0.16);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        color: rgba(255, 255, 255, 0.86);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    [lang="ar"] .spu-error__eyebrow,
    .spu-error__eyebrow:lang(ar) {
        letter-spacing: 0;
        font-size: 0.9rem;
    }

    .spu-error__eyebrow-line {
        display: block;
        width: 2.5rem;
        height: 2px;
        border-radius: 999px;
        background: var(--spu-gold);
    }

    .spu-error__title {
        margin: 0 0 1rem;
        font-size: clamp(2rem, 4.4vw, 3.25rem);
        font-weight: 700;
        line-height: 1.2;
        color: #ffffff;
    }

    .spu-error__lead {
        max-width: 36rem;
        margin: 0;
        font-size: clamp(1rem, 1.6vw, 1.15rem);
        color: rgba(255, 255, 255, 0.8);
    }

    .spu-error__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 2rem;
    }

    .spu-error__button {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        min-height: 48px;
        padding: 0.7rem 1.6rem;
        border: 1px solid var(--spu-gold);
        border-radius: 999px;
        background: var(--spu-gold);
        color: var(--spu-navy-deep);
        font-size: 0.98rem;
        font-weight: 700;
        line-height: 1.2;
        text-decoration: none;
        transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
    }

    .spu-error__button svg {
        width: 1.15rem;
        height: 1.15rem;
        flex: none;
    }

    .spu-error__button:hover,
    .spu-error__button:focus-visible {
        background: #ffffff;
        border-color: #ffffff;
        color: var(--spu-navy-deep);
        transform: translateY(-1px);
    }

    .spu-error__button--ghost {
        border-color: rgba(255, 255, 255, 0.4);
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .spu-error__button--ghost:hover,
    .spu-error__button--ghost:focus-visible {
        background: rgba(255, 255, 255, 0.14);
        border-color: #ffffff;
        color: #ffffff;
    }

    .spu-error__alt {
        max-width: 36rem;
        margin-top: 2.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid rgba(255, 255, 255, 0.14);
        text-align: start;
    }

    .spu-error__alt h2 {
        margin: 0 0 0.35rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.9);
    }

    .spu-error__alt p {
        margin: 0;
        font-size: 0.92rem;
        color: rgba(255, 255, 255, 0.66);
    }

    /* ---------- Status visual ---------- */

    .spu-error__visual {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        text-align: center;
    }

    .spu-error__mark {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 96px;
        height: 96px;
        padding: 14px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 0 0 8px rgba(255, 255, 255, 0.08), 0 18px 40px rgba(5, 8, 26, 0.45);
    }

    .spu-error__mark img {
        display: block;
        width: 100%;
        height: auto;
    }

    .spu-error__code {
        display: flex;
        justify-content: center;
        gap: 0.02em;
        margin: 0;
        font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        font-size: clamp(6rem, 15vw, 11rem);
        font-weight: 800;
        line-height: 0.95;
        letter-spacing: -0.02em;
        color: transparent;
        -webkit-text-stroke: 2px rgba(226, 184, 100, 0.85);
        text-shadow: 0 20px 60px rgba(226, 184, 100, 0.18);
        font-variant-numeric: tabular-nums;
    }

    .spu-error__code span:nth-child(2) {
        color: var(--spu-gold);
        -webkit-text-stroke: 0;
    }

    @supports not (-webkit-text-stroke: 1px) {
        .spu-error__code {
            color: var(--spu-gold);
        }
    }

    .spu-error__rule {
        display: block;
        width: 4.5rem;
        height: 4px;
        border-radius: 999px;
        background: linear-gradient(90deg, var(--spu-gold-strong), var(--spu-red));
    }

    /* ---------- Site sections ---------- */

    .spu-error__sections {
        padding-block: clamp(2.5rem, 6vw, 4rem);
        background: #f4f5f8;
    }

    .spu-error__sections-inner {
        max-width: 1200px;
        margin-inline: auto;
        padding-inline: clamp(1rem, 4vw, 2.5rem);
        text-align: start;
    }

    .spu-error__sections-kicker {
        margin: 0 0 0.25rem;
        color: var(--spu-red);
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .spu-error__sections-kicker:lang(ar),
    [lang="ar"] .spu-error__sections-kicker {
        letter-spacing: 0;
        font-size: 0.95rem;
    }

    .spu-error__sections h2 {
        margin: 0 0 1.5rem;
        color: var(--spu-navy);
        font-size: clamp(1.4rem, 2.6vw, 1.9rem);
        font-weight: 700;
        line-height: 1.3;
    }

    .spu-error__sections ul {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr));
        gap: 0.9rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .spu-error__sections a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-height: 64px;
        padding: 1rem 1.25rem;
        border: 1px solid var(--spu-line);
        border-inline-start: 4px solid var(--spu-gold-strong);
        border-radius: 16px;
        background: #ffffff;
        color: var(--spu-navy);
        font-size: 1rem;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 6px 18px rgba(32, 39, 89, 0.06);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, color 0.2s ease;
    }

    .spu-error__sections a svg {
        width: 1.25rem;
        height: 1.25rem;
        flex: none;
        color: var(--spu-gold-strong);
        transition: transform 0.2s ease, color 0.2s ease;
    }

    [dir="rtl"] .spu-error__sections a svg {
        transform: scaleX(-1);
    }

    .spu-error__sections a:hover,
    .spu-error__sections a:focus-visible {
        border-inline-start-color: var(--spu-red);
        color: var(--spu-red);
        box-shadow: 0 12px 28px rgba(32, 39, 89, 0.12);
        transform: translateY(-2px);
    }

    .spu-error__sections a:hover svg,
    .spu-error__sections a:focus-visible svg {
        color: var(--spu-red);
        transform: translateX(4px);
    }

    [dir="rtl"] .spu-error__sections a:hover svg,
    [dir="rtl"] .spu-error__sections a:focus-visible svg {
        transform: scaleX(-1) translateX(4px);
    }

    .spu-error :focus-visible {
        outline: 3px solid var(--spu-gold);
        outline-offset: 3px;
    }

    .spu-error__sections :focus-visible {
        outline-color: var(--spu-navy);
    }

    /* ---------- Responsive ---------- */

    @media (max-width: 860px) {
        .spu-error__inner {
            grid-template-columns: minmax(0, 1fr);
        }

        /* Number first on narrow screens, so the status is the first thing seen. */
        .spu-error__visual {
            order: -1;
            flex-direction: row;
            gap: 1.25rem;
        }

        .spu-error__mark {
            width: 72px;
            height: 72px;
            padding: 10px;
        }

        .spu-error__code {
            font-size: clamp(4.5rem, 22vw, 7rem);
        }

        .spu-error__rule {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .spu-error__actions {
            flex-direction: column;
        }

        .spu-error__button {
            justify-content: center;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .spu-error *,
        .spu-error *::before,
        .spu-error *::after {
            transition: none !important;
        }
    }
</style>
