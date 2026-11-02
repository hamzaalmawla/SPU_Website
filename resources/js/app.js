import Alpine from '@alpinejs/csp';

import { createMobileNav }      from './alpine/mobileNav.js';
import { createAboutNavigation } from './alpine/aboutNavigation.js';
import { createLeadershipDirectory } from './alpine/leadershipDirectory.js';
import { createAdmissionsFaq }   from './alpine/admissionsFaq.js';
import { createAdmissionsTabs }  from './alpine/admissionsTabs.js';
import { createAdmissionsTuition } from './alpine/admissionsTuition.js';
import { createAdmissionsDocuments } from './alpine/admissionsDocuments.js';
import { createCampusLifeGallery } from './alpine/campusLifeGallery.js';
import { createFacultyGallery } from './alpine/facultyGallery.js';
import { createDeanMessage } from './alpine/deanMessage.js';
import { createCampusLifeReveal } from './alpine/campusLifeReveal.js';
import { createNewsGalleryViewer } from './alpine/newsGalleryViewer.js';
import { createNewsShare } from './alpine/newsShare.js';
import { createPageShare } from './alpine/pageShare.js';
import { createFacultyMembers } from './alpine/facultyMembers.js';
import { createVirtualTour } from './alpine/virtualTour.js';
import { registerDynamicFormStore } from './alpine/dynamicFormStore.js';
import { createDynamicFormShell, createDynamicFormView } from './alpine/dynamicFormView.js';
import { initRevealSections }   from './alpine/scrollReveal.js';
import { createVirtualizedSchedule } from './alpine/virtualizedSchedule.js';
import { registerHomepageComponents } from './homepage.js';
import { initPageBehaviours } from './public/pageBehaviours.js';

// Alpine components (x-data="name()")
Alpine.data('mobileNav',       createMobileNav);
Alpine.data('aboutNavigation', createAboutNavigation);
Alpine.data('leadershipDirectory', createLeadershipDirectory);
Alpine.data('admissionsFaq',   createAdmissionsFaq);
Alpine.data('admissionsTabs',  createAdmissionsTabs);
Alpine.data('admissionsTuition', createAdmissionsTuition);
Alpine.data('admissionsDocuments', createAdmissionsDocuments);
Alpine.data('campusLifeGallery', createCampusLifeGallery);
Alpine.data('facultyGallery',   createFacultyGallery);
Alpine.data('deanMessage',      createDeanMessage);
Alpine.data('campusLifeReveal', createCampusLifeReveal);
Alpine.data('newsGalleryViewer', createNewsGalleryViewer);
Alpine.data('newsShare', createNewsShare);
Alpine.data('pageShare', createPageShare);
Alpine.data('facultyMembers', createFacultyMembers);
Alpine.data('virtualTour', createVirtualTour);
Alpine.data('dynamicFormShell', createDynamicFormShell);
Alpine.data('dynamicFormView', createDynamicFormView);
Alpine.data('virtualizedSchedule', createVirtualizedSchedule);
registerDynamicFormStore(Alpine);
registerHomepageComponents(Alpine);

window.Alpine = Alpine;

function whenDomReady() {
    if (document.readyState !== 'loading') {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        document.addEventListener('DOMContentLoaded', resolve, { once: true });
    });
}

// The study plan board is the largest public module, and admin.js shares it,
// which made it a separate chunk every page waited for before app.js could
// run. Only the pages that show a plan fetch it now.
function initPlanPages() {
    if (document.querySelector('[data-study-plan]')) {
        import('./alpine/studyPlan.js').then(({ initStudyPlanPages }) => initStudyPlanPages());
    }

    if (document.querySelector('[data-master-program]')) {
        import('./alpine/masterProgram.js').then(({ initMasterProgramPages }) => initMasterProgramPages());
    }
}

function initAfterAlpineStart() {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            initRevealSections();
            initPlanPages();
        });
    });
}

async function bootAlpine() {
    await whenDomReady();

    initPageBehaviours();

    // Evaluating this bundle and starting Alpine ran as one task long enough to
    // block input; yielding once lets the browser handle anything pending
    // in between.
    await new Promise((resolve) => setTimeout(resolve, 0));

    Alpine.start();
    initAfterAlpineStart();
}

bootAlpine().catch((error) => {
    console.error('Failed to initialize public Alpine components.', error);
});
