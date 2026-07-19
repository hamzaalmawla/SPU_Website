import { horizontalKeyAction, observeReducedMotion } from '../utils/motionDirection.js';

export function createHeroSlider() {
    return {
        currentIndex: 0,
        heroVisible: false,
        images: [],
        _timer: null,
        _removeMotionObserver: null,
        reducedMotion: false,

        init() {
            try {
                this.images = JSON.parse(this.$el.dataset.images || '[]');
            } catch {
                this.images = [];
            }

            this.heroVisible = true;
            this._removeMotionObserver = observeReducedMotion((reduced) => {
                this.reducedMotion = reduced;
                reduced ? this.stopAuto() : this.startAuto();
            });
        },

        destroy() {
            this.stopAuto();
            this._removeMotionObserver?.();
        },

        startAuto() {
            this.stopAuto();
            if (this.reducedMotion || this.images.length <= 1 || this.hasActiveInteraction()) return;

            this._timer = setInterval(() => this.next(), 5000);
        },

        stopAuto() {
            if (this._timer) clearInterval(this._timer);
            this._timer = null;
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
            if (this.images.length > 0) this.currentIndex = (this.currentIndex + 1) % this.images.length;
        },

        previous() {
            if (this.images.length > 0) this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
        },

        goTo(index) {
            this.stopAuto();
            this.currentIndex = index;
        },

        manualNext() {
            this.stopAuto();
            this.next();
        },

        manualPrevious() {
            this.stopAuto();
            this.previous();
        },

        handleKey(event) {
            const action = horizontalKeyAction(event, this.$el);
            if (!action) return;

            event.preventDefault();
            this.stopAuto();
            action === 'next' ? this.next() : this.previous();
        },

        visibleClass() {
            return this.heroVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6';
        },

        isCurrent(index) {
            return this.currentIndex === index;
        },

        isHidden(index) {
            return !this.isCurrent(index);
        },

        dotClass(index) {
            return this.isCurrent(index) ? 'w-8 opacity-100' : 'w-2 opacity-60';
        },

        slideLabel(index) {
            const label = this.$el.dataset.slideLabel || 'Show slide';
            return `${label} ${index + 1} / ${this.images.length}`;
        },
    };
}
