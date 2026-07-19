import { createCalendarApp } from './alpine/calendarApp.js';
import { createFacultiesSlider } from './alpine/facultiesSlider.js';
import { createHeroSlider } from './alpine/heroSlider.js';
import { createHonorPanel } from './alpine/honorPanel.js';
import { createResearchSlider } from './alpine/researchSlider.js';
import { createStatsCounter } from './alpine/statsCounter.js';
import { horizontalKeyAction, scrollByDirection } from './utils/motionDirection.js';

function createPathSlider() {
    return {
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

export function registerHomepageComponents(Alpine) {
    Alpine.data('calendarApp', createCalendarApp);
    Alpine.data('facultiesSlider', createFacultiesSlider);
    Alpine.data('heroSlider', createHeroSlider);
    Alpine.data('honorPanel', createHonorPanel);
    Alpine.data('pathSlider', createPathSlider);
    Alpine.data('researchSlider', createResearchSlider);
    Alpine.data('statsCounter', createStatsCounter);
}
