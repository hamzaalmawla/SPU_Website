export function createHonorPanel() {
    return {
        activeIndex: 0,
        items: [],
        _timer: null,

        init() {
            this.items = window.spuHonorItems ?? [];
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
    };
}
