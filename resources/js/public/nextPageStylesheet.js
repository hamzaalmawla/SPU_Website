/**
 * Pages that carry the stylesheet inline (<style id="public-stylesheet">,
 * the homepage) still want the file cached for the next page. A
 * <link rel="prefetch"> in <head> started that 47 KB download during the
 * first second, next to the hero image, the fonts and app.js. On the host's
 * HTTP/1.1 connections and slow upstream it took a quarter of the bandwidth
 * the first paint needed, so the hero photo and logo arrived late (Speed
 * Index, LCP load delay). The prefetch is now added once the page has
 * loaded and the browser is idle, and never for visitors saving data.
 */
export function prefetchNextPageStylesheet(doc = document, win = window) {
    const inline = doc.getElementById('public-stylesheet');
    const href = inline?.dataset?.stylesheetHref;
    if (!href || win.navigator?.connection?.saveData === true) {
        return false;
    }

    const add = () => {
        const exists = Array.from(doc.querySelectorAll('link[rel="prefetch"], link[rel="stylesheet"]')).some((link) => link.getAttribute('href') === href);
        if (exists) {
            return;
        }
        const link = doc.createElement('link');
        link.rel = 'prefetch';
        link.as = 'style';
        link.href = href;
        doc.head.append(link);
    };

    const whenIdle = () => {
        if (typeof win.requestIdleCallback === 'function') {
            win.requestIdleCallback(add, { timeout: 5_000 });
        } else {
            win.setTimeout(add, 2_000);
        }
    };

    if (doc.readyState === 'complete') {
        whenIdle();
    } else {
        win.addEventListener('load', whenIdle, { once: true });
    }

    return true;
}
