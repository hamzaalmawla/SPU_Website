import Alpine from '@alpinejs/csp';

import { createMobileNav }      from './alpine/mobileNav.js';
import { createAboutNavigation } from './alpine/aboutNavigation.js';
import { initRevealSections }   from './alpine/scrollReveal.js';

// Alpine components (x-data="name()")
Alpine.data('mobileNav',       createMobileNav);
Alpine.data('aboutNavigation', createAboutNavigation);

window.Alpine = Alpine;

function whenDomReady() {
    if (document.readyState !== 'loading') {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
}

async function registerPageComponents() {
    if (!document.querySelector('[data-homepage]')) {
        return;
    }

    const { registerHomepageComponents } = await import('./homepage.js');
    registerHomepageComponents(Alpine);
}

function initAfterAlpineStart() {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            initRevealSections();
        });
    });
}

async function bootAlpine() {
    await whenDomReady();
    await registerPageComponents();

    Alpine.start();
    initAfterAlpineStart();
}

bootAlpine().catch((error) => {
    console.error('Failed to initialize public Alpine components.', error);
});
