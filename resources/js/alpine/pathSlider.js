import { horizontalKeyAction, scrollByDirection } from '../utils/motionDirection.js';

export function createPathSlider() {
    return {
        activePathCard: null,

        togglePathCard(index) {
            if (window.matchMedia('(hover: hover)').matches) return;
            this.activePathCard = this.activePathCard === index ? null : index;
        },

        slidePaths(action) {
            const track = this.$refs.pathsTrack;
            if (!track) return;

            const firstCard = track.querySelector('.path-card');
            const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 292;
            const distance = Math.round(cardWidth + 24);

            scrollByDirection(track, action, distance);
        },

        handleSliderKey(event) {
            const action = horizontalKeyAction(event, this.$refs.pathsTrack);
            if (!action) return;

            event.preventDefault();
            this.slidePaths(action);
        },
    };
}
