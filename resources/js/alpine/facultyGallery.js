/**
 * Cross-fades the faculties hub panorama as each faculty card is hovered or
 * focused.
 *
 * The hub used to declare this inline: `x-data="{ active: null, images: @js(...) }"`.
 * Alpine here is the CSP build, which evaluates no expressions at all — so that
 * object literal never parsed, and every page on /faculties threw "Undefined
 * variable: JSON" and "Undefined variable: active" into the console while the
 * gallery sat inert in both locales. It went unnoticed because the page looks
 * finished without it: the default panorama still renders, and only the
 * cross-fade is missing.
 *
 * Registered as a component with its data arriving through a `data-images`
 * attribute, which is markup rather than an expression, and is the same shape as
 * every other component in this build.
 */
export function createFacultyGallery() {
    return {
        active: null,
        images: [],

        init() {
            try {
                const raw = this.$el.dataset.images;
                const parsed = raw ? JSON.parse(raw) : [];
                this.images = Array.isArray(parsed) ? parsed : [];
            } catch {
                // A malformed payload must not take the hero down with it. The
                // default panorama is already rendered underneath.
                this.images = [];
            }
        },

        show(event) {
            const index = Number.parseInt(event.currentTarget?.dataset.galleryIndex ?? '', 10);
            this.active = Number.isInteger(index) && this.images[index] ? index : null;
        },

        clear() {
            this.active = null;
        },

        layerSrc() {
            return this.active === null ? '' : (this.images[this.active] ?? '');
        },

        layerClass() {
            return this.active === null ? 'opacity-0' : 'opacity-100';
        },
    };
}
