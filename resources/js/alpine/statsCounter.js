import { prefersReducedMotion } from '../utils/motionDirection.js';

function animateValue(el, end, duration = 1500) {
    const frameRate = 1000 / 60;
    const totalFrames = Math.round(duration / frameRate);
    const increment = end / totalFrames;
    let currentFrame = 0;
    const finalText = el.textContent;
    const locale = typeof document !== 'undefined' ? document.documentElement.lang : 'en';

    el.textContent = new Intl.NumberFormat(locale).format(0);

    const timer = setInterval(() => {
        currentFrame += 1;
        const next = Math.round(increment * currentFrame);

        if (currentFrame >= totalFrames) {
            el.textContent = finalText;
            clearInterval(timer);
            return;
        }

        el.textContent = new Intl.NumberFormat(locale).format(next);
    }, frameRate);
}

export function createStatsCounter() {
    return {
        _observed: false,

        init() {
            const targets = this.$el.querySelectorAll('[data-value]');
            if (!targets.length) return;

            if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;

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

            // Trigger immediately if already in view
            const rect = this.$el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                this._observed = true;
                observer.disconnect();
                targets.forEach((el) => {
                    const end = Number.parseFloat(el.dataset.value.replaceAll(',', '')) || 0;
                    animateValue(el, end);
                });
            }
        },
    };
}
