import Alpine from 'alpinejs';

import { createHeroSlider }     from './alpine/heroSlider.js';
import { createStatsCounter }   from './alpine/statsCounter.js';
import { createFacultiesSlider } from './alpine/facultiesSlider.js';
import { createHonorPanel }     from './alpine/honorPanel.js';
import { createResearchSlider } from './alpine/researchSlider.js';
import { createCalendarApp }    from './alpine/calendarApp.js';
import { createMobileNav }      from './alpine/mobileNav.js';
import { initRevealSections }   from './alpine/scrollReveal.js';

// Alpine components (x-data="name()")
Alpine.data('heroSlider',      createHeroSlider);
Alpine.data('statsCounter',    createStatsCounter);
Alpine.data('facultiesSlider', createFacultiesSlider);
Alpine.data('honorPanel',      createHonorPanel);
Alpine.data('researchSlider',  createResearchSlider);
Alpine.data('calendarApp',     createCalendarApp);
Alpine.data('mobileNav',       createMobileNav);

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initRevealSections();
});
