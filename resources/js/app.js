import Alpine from '@alpinejs/csp';

import { config, dom, library } from '@fortawesome/fontawesome-svg-core';
import '@fortawesome/fontawesome-svg-core/styles.css';
import { faArrowLeft, faArrowRight, faBars, faCheck, faChevronDown, faChevronLeft, faChevronRight, faEnvelope, faGlobe, faHandshake, faHistory, faMapMarkerAlt, faPhoneAlt, faSitemap, faTimes, faUniversity, faUserGraduate, faUsers } from '@fortawesome/free-solid-svg-icons';
import { faFacebookF, faInstagram, faTelegramPlane, faYoutube } from '@fortawesome/free-brands-svg-icons';

config.autoAddCss = false;
library.add(faArrowLeft, faArrowRight, faBars, faCheck, faChevronDown, faChevronLeft, faChevronRight, faEnvelope, faFacebookF, faGlobe, faHandshake, faHistory, faInstagram, faMapMarkerAlt, faPhoneAlt, faSitemap, faTelegramPlane, faTimes, faUniversity, faUserGraduate, faUsers, faYoutube);

import { createHeroSlider }     from './alpine/heroSlider.js';
import { createStatsCounter }   from './alpine/statsCounter.js';
import { createFacultiesSlider } from './alpine/facultiesSlider.js';
import { createHonorPanel }     from './alpine/honorPanel.js';
import { createResearchSlider } from './alpine/researchSlider.js';
import { createCalendarApp }    from './alpine/calendarApp.js';
import { createMobileNav }      from './alpine/mobileNav.js';
import { initRevealSections }   from './alpine/scrollReveal.js';

function createPathSlider() {
    return {
        slidePaths(direction) {
            const track = this.$refs.pathsTrack;
            if (!track) return;

            const firstCard = track.querySelector('.path-card');
            const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 292;
            const distance = Math.round(cardWidth + 24);

            track.scrollBy({
                left: direction === 'right' ? distance : -distance,
                behavior: 'smooth',
            });
        },
    };
}

// Alpine components (x-data="name()")
Alpine.data('heroSlider',      createHeroSlider);
Alpine.data('statsCounter',    createStatsCounter);
Alpine.data('facultiesSlider', createFacultiesSlider);
Alpine.data('honorPanel',      createHonorPanel);
Alpine.data('researchSlider',  createResearchSlider);
Alpine.data('calendarApp',     createCalendarApp);
Alpine.data('mobileNav',       createMobileNav);
Alpine.data('pathSlider',      createPathSlider);

window.Alpine = Alpine;
Alpine.start();

// Init reveal after Alpine has rendered the DOM
requestAnimationFrame(() => {
    requestAnimationFrame(() => {
        initRevealSections();
        dom.watch();
    });
});
