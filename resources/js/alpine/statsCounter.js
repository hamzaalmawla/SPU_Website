import { prefersReducedMotion } from '../utils/motionDirection.js';

function animateValue(el, end, duration = 1500) {
    const finalText = el.textContent;
    const locale = typeof document !== 'undefined' ? document.documentElement.lang : 'en';
    const format = new Intl.NumberFormat(locale);
    let start = null;

    el.textContent = format.format(0);

    // One text write per frame, in step with the browser's own rendering,
    // instead of a 16 ms interval that runs off-frame and keeps going while
    // the tab is hidden.
    const step = (now) => {
        start ??= now;
        const progress = Math.min((now - start) / duration, 1);

        if (progress >= 1) {
            el.textContent = finalText;
            return;
        }

        el.textContent = format.format(Math.round(end * progress));
        requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
}

export function createStatsCounter() {
    return {
        _observed: false,

        init() {
            const targets = this.$el.querySelectorAll('[data-value]');
            if (!targets.length) return;

            if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;

            // The observer reports the section's initial state on its first
            // callback, so a counter already in view starts without reading
            // its geometry here. A getBoundingClientRect() during Alpine's
            // start forced a synchronous layout of the whole homepage.
            const observer = new IntersectionObserver((entries) => {
                if (!entries[0].isIntersecting || this._observed) return;
                this._observed = true;
                observer.disconnect();

                targets.forEach((el) => {
                    const end = Number.parseFloat(el.dataset.value.replaceAll(',', '')) || 0;
                    animateValue(el, end);
                });
            }, { threshold: 0.2 });

            observer.observe(this.$el);
        },
    };
}
