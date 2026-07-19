import { horizontalKeyAction, observeReducedMotion } from '../utils/motionDirection.js';

export function createHonorPanel() {
    return {
        activeIndex: 0,
        items: [],
        _timer: null,
        _removeMotionObserver: null,
        reducedMotion: false,

        init() {
            try {
                this.items = JSON.parse(this.$el.dataset.items || '[]');
            } catch {
                this.items = [];
            }

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
            if (!this.reducedMotion && this.items.length > 1 && !this.hasActiveInteraction()) {
                this._timer = setInterval(() => this.next(), 6000);
            }
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
            if (this.items.length > 0) {
                this.activeIndex = (this.activeIndex + 1) % this.items.length;
            }
        },

        prev() {
            if (this.items.length > 0) {
                this.activeIndex = (this.activeIndex - 1 + this.items.length) % this.items.length;
            }
        },

        handleManual(action, val) {
            this.stopAuto();
            if (action === 'next') this.next();
            if (action === 'prev') this.prev();
            if (action === 'goto') this.activeIndex = val;
        },

        handleKey(event) {
            const action = horizontalKeyAction(event, this.$el);
            if (!action) return;

            event.preventDefault();
            this.handleManual(action === 'next' ? 'next' : 'prev');
        },

        getPos(index) {
            if (!this.items.length) return 0;
            return (index - this.activeIndex + this.items.length) % this.items.length;
        },

        panelClass(index) {
            const position = this.getPos(index);

            if (position === 0) {
                return 'w-full lg:w-[65%] h-full z-30 start-0 top-0 opacity-100 shadow-2xl scale-100';
            }

            if (position === 1) {
                return 'w-0 lg:w-[32%] h-[48%] z-20 start-0 lg:start-[68%] top-0 opacity-0 lg:opacity-100 scale-95 brightness-90';
            }

            if (position === 2) {
                return 'w-0 lg:w-[32%] h-[48%] z-10 start-0 lg:start-[68%] top-[52%] opacity-0 lg:opacity-100 scale-90 brightness-75';
            }

            return 'w-0 h-0 opacity-0';
        },

        isPrimary(index) {
            return this.getPos(index) === 0;
        },

        isSecondary(index) {
            const position = this.getPos(index);
            return position === 1 || position === 2;
        },

        isHidden(index) {
            return this.getPos(index) > 2;
        },

        itemKey(item, index) {
            return item.id || index;
        },

        dotKey(item, index) {
            return `dot-${item.id || index}`;
        },

        dotClass(index) {
            return this.activeIndex === index ? 'w-10 bg-spu-red' : 'w-2 bg-slate-200';
        },

        itemAlt(item) {
            return item.title || '';
        },

        actionTarget(item) {
            return item.action?.target || null;
        },

        hasAction(item) {
            return Boolean(item.action?.url);
        },

        actionRel(item) {
            return item.action?.target ? 'noreferrer' : null;
        },

        itemLabel(index) {
            const label = this.$el.dataset.itemLabel || 'Show item';
            return `${label} ${index + 1} / ${this.items.length}`;
        },
    };
}
