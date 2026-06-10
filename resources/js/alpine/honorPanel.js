export function createHonorPanel() {
    return {
        activeIndex: 0,
        items: [],
        _timer: null,

        init() {
            try {
                this.items = JSON.parse(this.$el.dataset.items || '[]');
            } catch {
                this.items = [];
            }

            this.startAuto();
        },

        startAuto() {
            this.stopAuto();
            if (this.items.length > 1) {
                this._timer = setInterval(() => this.next(), 6000);
            }
        },

        stopAuto() {
            if (this._timer) clearInterval(this._timer);
            this._timer = null;
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
            setTimeout(() => this.startAuto(), 8000);
        },

        getPos(index) {
            if (!this.items.length) return 0;
            return (index - this.activeIndex + this.items.length) % this.items.length;
        },

        panelClass(index) {
            const position = this.getPos(index);

            if (position === 0) {
                return 'w-full lg:w-[65%] h-full z-30 left-0 top-0 opacity-100 shadow-2xl scale-100';
            }

            if (position === 1) {
                return 'w-0 lg:w-[32%] h-[48%] z-20 left-0 lg:left-[68%] top-0 opacity-0 lg:opacity-100 scale-95 brightness-90';
            }

            if (position === 2) {
                return 'w-0 lg:w-[32%] h-[48%] z-10 left-0 lg:left-[68%] top-[52%] opacity-0 lg:opacity-100 scale-90 brightness-75';
            }

            return 'w-0 h-0 opacity-0';
        },

        isPrimary(index) {
            return this.getPos(index) === 0;
        },

        isSecondary(index) {
            return this.getPos(index) !== 0;
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
            return `Item ${index + 1}`;
        },
    };
}
