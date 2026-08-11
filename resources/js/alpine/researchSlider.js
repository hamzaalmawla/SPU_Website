import { horizontalKeyAction, scrollByDirection } from '../utils/motionDirection.js';

export function createResearchSlider() {
    return {
        slide(action) {
            const track = this.$refs.researchTrack;
            if (!track) return;

            const firstCard = track.querySelector('.research-card');
            const cardWidth = firstCard?.getBoundingClientRect().width ?? 320;
            const styles = typeof window !== 'undefined' && typeof window.getComputedStyle === 'function'
                ? window.getComputedStyle(track)
                : null;
            const gap = Number.parseFloat(styles?.columnGap || styles?.gap || '0') || 0;

            scrollByDirection(track, action, Math.round(cardWidth + gap));
        },

        handleSliderKey(event) {
            const action = horizontalKeyAction(event, this.$refs.researchTrack);
            if (!action) return;

            event.preventDefault();
            this.slide(action);
        },
    };
}
