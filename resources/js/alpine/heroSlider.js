import { horizontalKeyAction, observeReducedMotion } from '../utils/motionDirection.js';

export function createHeroSlider() {
    return {
        currentIndex: 0,
        pageLoaded: false,
        images: [],
        _timer: null,
        _preloadTimer: null,
        _removeMotionObserver: null,
        reducedMotion: false,

        init() {
            try {
                this.images = JSON.parse(this.$el.dataset.images || '[]');
            } catch {
                this.images = [];
            }

            // Rotation starts counting once the page has loaded. A slide that
            // swaps in while the page is still loading paints a new, larger
            // image, and Chrome reports it as a later Largest Contentful Paint.
            this.pageLoaded = typeof document === 'undefined' || document.readyState === 'complete';
            if (!this.pageLoaded) {
                window.addEventListener('load', () => {
                    this.pageLoaded = true;
                    if (!this.reducedMotion) this.startAuto();
                }, { once: true });
            }

            this._removeMotionObserver = observeReducedMotion((reduced) => {
                this.reducedMotion = reduced;
                reduced ? this.stopAuto() : this.startAuto();
            });
        },

        // The hidden slides are lazy and display:none, so nothing fetches them
        // during the page load. Fetching every one on window.load pulled ~2 MB
        // of editor-added slides into each visit, so only the slide about to
        // rotate in is fetched, a second before it does. Switching its <img>
        // to eager lets the browser pick from its srcset as it would on screen.
        preloadSlide(index) {
            if (this.images.length <= 1 || typeof this.$el.querySelectorAll !== 'function') return;

            const slide = this.$el.querySelectorAll('img[data-hero-slide]')[index % this.images.length];
            if (slide && slide.loading === 'lazy') slide.loading = 'eager';
        },

        destroy() {
            this.stopAuto();
            this._removeMotionObserver?.();
        },

        startAuto() {
            this.stopAuto();
            if (!this.pageLoaded || this.reducedMotion || this.images.length <= 1 || this.hasActiveInteraction()) return;

            this._timer = setInterval(() => this.next(), 9000);
            this._preloadTimer = setTimeout(() => this.preloadSlide(this.currentIndex + 1), 8000);
        },

        stopAuto() {
            if (this._timer) clearInterval(this._timer);
            if (this._preloadTimer) clearTimeout(this._preloadTimer);
            this._timer = null;
            this._preloadTimer = null;
        },

        hasActiveInteraction() {
            const hovered = typeof this.$el.matches === 'function' && this.$el.matches(':hover');
            const focused = typeof document !== 'undefined' && this.$el.contains(document.activeElement);

            return hovered || focused;
        },

        resumeAuto(event) {
            if (event?.relatedTarget && this.$el.contains(event.relatedTarget)) return;
            this.startAuto();
        },

        next() {
            if (this.images.length === 0) return;

            this.currentIndex = (this.currentIndex + 1) % this.images.length;

            if (this._timer) {
                if (this._preloadTimer) clearTimeout(this._preloadTimer);
                this._preloadTimer = setTimeout(() => this.preloadSlide(this.currentIndex + 1), 8000);
            }
        },

        previous() {
            if (this.images.length > 0) this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
        },

        handleKey(event) {
            const action = horizontalKeyAction(event, this.$el);
            if (!action) return;

            event.preventDefault();
            this.stopAuto();
            action === 'next' ? this.next() : this.previous();
        },

        isCurrent(index) {
            return this.currentIndex === index;
        },

        isHidden(index) {
            return !this.isCurrent(index);
        },

        slideLabel(index) {
            const label = this.$el.dataset.slideLabel || 'Show slide';
            return `${label} ${index + 1} / ${this.images.length}`;
        },
    };
}
