export function createHeroSlider() {
    return {
        currentIndex: 0,
        heroVisible: false,
        images: [],
        _timer: null,

        init() {
            try {
                this.images = JSON.parse(this.$el.dataset.images || '[]');
            } catch {
                this.images = [];
            }

            this.heroVisible = true;
            if (this.images.length > 1) {
                this._timer = setInterval(() => {
                    this.currentIndex = (this.currentIndex + 1) % this.images.length;
                }, 5000);
            }
        },

        destroy() {
            if (this._timer) clearInterval(this._timer);
        },

        visibleClass() {
            return this.heroVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6';
        },

        isCurrent(index) {
            return this.currentIndex === index;
        },
    };
}
