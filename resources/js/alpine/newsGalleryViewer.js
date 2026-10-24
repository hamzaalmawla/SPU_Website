export function createNewsGalleryViewer() {
    return {
        items: [],
        activeIndex: -1,
        trigger: null,

        init() {
            this.items = Array.from(this.$el.querySelectorAll('[data-gallery-item]')).map((item) => ({
                src: item.dataset.src || '',
                alt: item.dataset.alt || '',
                title: item.dataset.title || '',
                caption: item.dataset.caption || '',
            }));
        },

        open(event) {
            this.trigger = event.currentTarget;
            this.activeIndex = Number.parseInt(this.trigger.dataset.galleryIndex || '0', 10);
            this.$nextTick(() => this.$refs.closeButton?.focus());
        },

        close() {
            this.activeIndex = -1;
            this.$nextTick(() => this.trigger?.focus());
        },

        next() {
            if (this.activeIndex >= 0 && this.items.length > 0) this.activeIndex = (this.activeIndex + 1) % this.items.length;
        },

        previous() {
            if (this.activeIndex >= 0 && this.items.length > 0) this.activeIndex = (this.activeIndex - 1 + this.items.length) % this.items.length;
        },

        trapFocus(event) {
            const focusable = Array.from(this.$refs.dialog?.querySelectorAll('button:not([disabled])') || []);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        get isOpen() { return this.activeIndex >= 0; },
        get activeItem() { return this.items[this.activeIndex] || { src: '', alt: '', title: '', caption: '' }; },
    };
}
