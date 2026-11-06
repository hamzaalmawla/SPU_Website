export function createCampusLifeGallery() {
    return {
        activeSrc: '',
        activeAlt: '',

        open(event) {
            const trigger = event.currentTarget;
            this.activeSrc = trigger.dataset.src || '';
            this.activeAlt = trigger.dataset.alt || '';
        },

        close() {
            this.activeSrc = '';
            this.activeAlt = '';
        },

        isOpen() {
            return this.activeSrc !== '';
        },
    };
}
