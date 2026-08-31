{{--
    Critical CSS for every SPU error page.

    Deliberately self-contained: no @vite, no Tailwind, no webfont request. A
    500 or 503 can be rendered while the asset build is missing or the CDN is
    unreachable, so the page must be legible from this block alone. Layout uses
    logical properties (margin-inline, text-align: start) so the same rules are
    correct in RTL and LTR without a second stylesheet.
--}}
<style>
    .spu-error {
        --spu-navy: #202759;
        --spu-navy-deep: #151b43;
        --spu-red: #6f1616;
        --spu-gold: #caa949;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 70vh;
        margin: 0 auto;
        padding: 48px 24px 64px;
        max-width: 760px;
        text-align: center;
        color: var(--spu-navy-deep);
        font-family: "Hacen", "Segoe UI", "Helvetica Neue", Arial, "Noto Naskh Arabic", sans-serif;
        line-height: 1.6;
    }

    .spu-error *,
    .spu-error *::before,
    .spu-error *::after {
        box-sizing: border-box;
    }

    .spu-error__mark {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 84px;
        height: 84px;
        margin-bottom: 24px;
    }

    /* The SVG monogram is always drawn. The bitmap logo sits on top of it and
       simply fails to paint if the asset is missing, leaving branding intact. */
    .spu-error__mark svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
    }

    .spu-error__mark img {
        position: relative;
        width: 100%;
        height: 100%;
        object-fit: contain;
        font-size: 11px;
        color: var(--spu-navy);
    }

    .spu-error__code {
        margin: 0 0 8px;
        font-size: 64px;
        font-weight: 800;
        letter-spacing: 0.04em;
        line-height: 1;
        color: var(--spu-navy);
    }

    .spu-error__rule {
        width: 64px;
        height: 4px;
        margin: 0 0 28px;
        border: 0;
        border-radius: 2px;
        background: var(--spu-gold);
    }

    .spu-error__pane {
        margin-bottom: 20px;
    }

    .spu-error__pane h1 {
        margin: 0 0 10px;
        font-size: 28px;
        font-weight: 700;
        color: var(--spu-navy-deep);
    }

    .spu-error__pane p {
        margin: 0;
        font-size: 17px;
        color: #4a5068;
    }

    .spu-error__pane--alt {
        padding-top: 18px;
        border-top: 1px solid rgba(32, 39, 89, 0.12);
        opacity: 0.85;
    }

    .spu-error__pane--alt h1 {
        font-size: 21px;
    }

    .spu-error__pane--alt p {
        font-size: 15px;
    }

    .spu-error__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: center;
        margin-top: 28px;
    }

    .spu-error__button {
        display: inline-block;
        padding: 12px 26px;
        border: 1px solid var(--spu-navy);
        border-radius: 999px;
        background: var(--spu-navy);
        color: #ffffff;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.2s ease, color 0.2s ease;
    }

    .spu-error__button:hover,
    .spu-error__button:focus-visible {
        background: var(--spu-navy-deep);
    }

    .spu-error__button--ghost {
        background: transparent;
        color: var(--spu-navy);
    }

    .spu-error__button--ghost:hover,
    .spu-error__button--ghost:focus-visible {
        background: rgba(32, 39, 89, 0.08);
        color: var(--spu-navy-deep);
    }

    .spu-error__links {
        margin-top: 36px;
        padding-top: 24px;
        border-top: 1px solid rgba(32, 39, 89, 0.12);
        width: 100%;
    }

    .spu-error__links h2 {
        margin: 0 0 14px;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #7b8199;
    }

    .spu-error__links ul {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 22px;
        justify-content: center;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .spu-error__links a {
        color: var(--spu-navy);
        font-size: 15px;
        text-decoration: none;
        border-bottom: 1px solid transparent;
    }

    .spu-error__links a:hover,
    .spu-error__links a:focus-visible {
        color: var(--spu-red);
        border-bottom-color: currentColor;
    }

    .spu-error :focus-visible {
        outline: 3px solid var(--spu-gold);
        outline-offset: 3px;
    }

    @media (max-width: 520px) {
        .spu-error__code {
            font-size: 48px;
        }

        .spu-error__pane h1 {
            font-size: 23px;
        }
    }
</style>
