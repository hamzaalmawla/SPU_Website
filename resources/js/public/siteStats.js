// Reports this page view to the site's own visitor statistics (admin
// dashboard, config/site_stats.php). Nothing is stored in the browser and no
// cookie is sent; previews and error pages opt out with
// <meta name="spu-stats" content="off">.
const ENDPOINT = '/stats/hit';

function send() {
    const searchTotal = document.querySelector('[data-search-total]');
    const payload = { p: location.pathname + location.search, r: document.referrer || '' };

    if (searchTotal) {
        payload.n = Number(searchTotal.getAttribute('data-search-total')) || 0;
    }

    const body = JSON.stringify(payload);

    try {
        if (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, new Blob([body], { type: 'text/plain' }))) {
            return;
        }
    } catch {
        // Fall through to fetch.
    }

    fetch(ENDPOINT, { method: 'POST', body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } }).catch(() => {});
}

export function reportPageView() {
    if (navigator.webdriver || document.querySelector('meta[name="spu-stats"][content="off"]')) {
        return;
    }

    // A page the browser prerenders counts only once the visitor opens it.
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', send, { once: true });

        return;
    }

    send();
}
