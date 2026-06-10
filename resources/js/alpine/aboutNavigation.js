export function createAboutNavigation() {
    return {
        activeSlide: 0,
        maxSlide: 0,

        init() {
            const slideCount = Number.parseInt(this.$el.dataset.slideCount || '1', 10);
            this.maxSlide = Math.max(slideCount - 1, 0);
        },

        nextSlide() {
            this.activeSlide = this.activeSlide >= this.maxSlide ? 0 : this.activeSlide + 1;
        },

        previousSlide() {
            this.activeSlide = this.activeSlide <= 0 ? this.maxSlide : this.activeSlide - 1;
        },

        goToSlide(index) {
            this.activeSlide = Math.min(Math.max(index, 0), this.maxSlide);
        },

        dotClass(index) {
            return this.activeSlide === index ? 'w-8 bg-spu-blue' : 'w-2 bg-spu-blue/20';
        },

        slideStyle() {
            const direction = document.documentElement.dir === 'rtl' ? 1 : -1;
            return `transform: translateX(${this.activeSlide * direction * 100}%);`;
        },
    };
}
