// The dean's message beside their photo: when the text runs past the photo,
// it is cut at the photo's height and a "show more" button opens the rest.
export function createDeanMessage() {
    return {
        expanded: false,
        overflowing: false,
        collapsedHeight: 0,

        init() {
            this.measure();

            if (typeof ResizeObserver === 'function') {
                const observer = new ResizeObserver(() => this.measure());
                observer.observe(this.$refs.photo);
                observer.observe(this.$root);
            }
        },

        measure() {
            const photo = this.$refs.photo;
            const text = this.$refs.text;

            if (!photo || !text) return;

            // Room left beside the photo once the heading above the text is placed.
            const room = Math.max(photo.getBoundingClientRect().height - text.offsetTop, 160);
            this.collapsedHeight = Math.floor(room);
            this.overflowing = text.scrollHeight > room + 4;
        },

        isCollapsed() {
            return this.overflowing && !this.expanded;
        },

        textStyle() {
            return this.isCollapsed() ? `max-height: ${this.collapsedHeight}px` : '';
        },

        toggle() {
            this.expanded = !this.expanded;
        },

        toggleLabel() {
            return this.expanded ? this.$root.dataset.lessLabel : this.$root.dataset.moreLabel;
        },

        ariaExpanded() {
            return this.expanded ? 'true' : 'false';
        },
    };
}
