import { horizontalKeyAction, scrollByDirection } from '../utils/motionDirection.js';

const RESEARCH_CARD_STEP = 342;

export function createResearchSlider() {
    return {
        slide(action) {
            const track = this.$refs.researchTrack;
            if (!track) return;

            scrollByDirection(track, action, RESEARCH_CARD_STEP);
        },

        handleSliderKey(event) {
            const action = horizontalKeyAction(event, this.$refs.researchTrack);
            if (!action) return;

            event.preventDefault();
            this.slide(action);
        },
    };
}
