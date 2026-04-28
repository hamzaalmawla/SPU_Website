export function createHeroSlider() {
    return {
        currentIndex: 0,
        images: [],
        _timer: null,

        init() {
            this.images = window.spuHeroImages ?? [];
            if (this.images.length > 1) {
                this._timer = setInterval(() => {
                    this.currentIndex = (this.currentIndex + 1) % this.images.length;
                }, 5000);
            }
        },

        destroy() {
            if (this._timer) clearInterval(this._timer);
        },
    };
}
