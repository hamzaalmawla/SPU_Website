import { createCalendarApp } from './alpine/calendarApp.js';
import { createFacultiesSlider } from './alpine/facultiesSlider.js';
import { createHeroSlider } from './alpine/heroSlider.js';
import { createHonorPanel } from './alpine/honorPanel.js';
import { createPathSlider } from './alpine/pathSlider.js';
import { createResearchSlider } from './alpine/researchSlider.js';
import { createStatsCounter } from './alpine/statsCounter.js';
import { horizontalKeyAction, scrollByDirection } from './utils/motionDirection.js';

export function registerHomepageComponents(Alpine) {
    Alpine.data('calendarApp', createCalendarApp);
    Alpine.data('facultiesSlider', createFacultiesSlider);
    Alpine.data('heroSlider', createHeroSlider);
    Alpine.data('honorPanel', createHonorPanel);
    Alpine.data('pathSlider', createPathSlider);
    Alpine.data('researchSlider', createResearchSlider);
    Alpine.data('statsCounter', createStatsCounter);
}
