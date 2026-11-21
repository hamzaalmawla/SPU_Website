// Behaviour that used to sit in inline <script> tags and on* attributes in the
// public views. The public Content-Security-Policy no longer allows inline
// script, so it lives here and is wired up through data attributes.

// <select data-autosubmit>: filter dropdowns that submit their form on change.
export function initAutoSubmit(doc = document) {
    doc.addEventListener('change', (event) => {
        const field = event.target;

        if (field instanceof HTMLElement && field.matches('[data-autosubmit]') && field.form) {
            field.form.submit();
        }
    });
}

function useFallback(image) {
    const fallback = image.dataset.fallbackSrc;

    if (!fallback || image.dataset.fallbackApplied === 'true') return;

    image.dataset.fallbackApplied = 'true';
    image.removeAttribute('srcset');
    image.src = fallback;
}

// <img data-fallback-src="...">: swap in a placeholder when the image fails.
// error does not bubble, so listen in the capture phase, and catch the images
// that failed before this module ran.
export function initImageFallbacks(doc = document) {
    doc.addEventListener('error', (event) => {
        if (event.target instanceof HTMLImageElement && event.target.dataset.fallbackSrc) {
            useFallback(event.target);
        }
    }, true);

    doc.querySelectorAll('img[data-fallback-src]').forEach((image) => {
        if (image.complete && image.naturalWidth === 0 && image.getAttribute('src')) {
            useFallback(image);
        }
    });
}

// Admin previews render the contact form, which must not send anything.
export function initPreviewForms(doc = document) {
    doc.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement && event.target.matches('[data-preview-form]')) {
            event.preventDefault();
        }
    });
}

// Achievements archive: filter and paginate without a full page load.
export function initAchievementArchive(doc = document) {
    const root = doc.querySelector('[data-achievement-archive]');
    if (!root) return;

    const form = root.querySelector('[data-achievement-filters]');
    const container = root.querySelector('[data-achievement-results-container]');
    const loading = root.querySelector('[data-achievement-loading]');
    if (!form || !container || !loading) return;

    let controller;

    const syncFilters = (url) => {
        const selected = new URL(url).searchParams.getAll('categories[]');
        form.querySelectorAll('input[name="categories[]"]').forEach((input) => {
            input.checked = selected.includes(input.value);
        });
    };

    const load = async (url, updateHistory = true) => {
        controller?.abort();
        controller = new AbortController();
        loading.classList.remove('hidden');
        loading.classList.add('flex');
        container.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
            if (!response.ok) throw new Error('Request failed');
            container.querySelector('[data-achievement-results]').outerHTML = await response.text();
            if (updateHistory) window.history.pushState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            loading.classList.add('hidden');
            loading.classList.remove('flex');
            container.setAttribute('aria-busy', 'false');
        }
    };

    form.addEventListener('change', () => load(`${form.action}?${new URLSearchParams(new FormData(form))}`));
    root.addEventListener('click', (event) => {
        const link = event.target.closest('[data-achievement-pagination] a');
        if (!link) return;
        event.preventDefault();
        load(link.href);
    });
    window.addEventListener('popstate', () => {
        syncFilters(window.location.href);
        load(window.location.href, false);
    });
}

// Centre course lists: a link from a study plan names one course
// (#course-<id>); open its details and scroll to it.
export function initCourseListAnchors(doc = document) {
    if (!doc.querySelector('[data-center-courses]')) return;

    const openLinkedCourse = () => {
        const id = decodeURIComponent((window.location.hash || '').slice(1));
        if (id.indexOf('course-') !== 0) return;

        const course = doc.getElementById(id);
        if (!course) return;

        const details = course.querySelector('[data-course-details]');
        if (details) details.open = true;

        course.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    };

    window.addEventListener('hashchange', openLinkedCourse);
    openLinkedCourse();
}

// Faculty overview gallery: thumbnails swap the main photo.
export function initFacultyDetailGallery(doc = document) {
    const gallery = doc.querySelector('[data-faculty-gallery]');
    if (!gallery || gallery.dataset.galleryBound === 'true') return;
    gallery.dataset.galleryBound = 'true';

    const mainImg = gallery.querySelector('[data-gallery-main]');
    const counter = gallery.querySelector('[data-gallery-counter]');
    const thumbs = Array.from(gallery.querySelectorAll('[data-gallery-thumb]'));

    const selectThumb = (selected) => {
        const src = selected.getAttribute('data-src');
        const num = selected.getAttribute('data-gallery-thumb');

        if (mainImg && src) mainImg.src = src;
        if (counter && num) counter.textContent = num;

        thumbs.forEach((thumb) => {
            const bar = thumb.querySelector('.gallery-thumb-bar');
            const active = thumb === selected;

            thumb.classList.toggle('opacity-40', !active);
            thumb.classList.toggle('ring-[2.5px]', active);
            thumb.classList.toggle('ring-offset-2', active);
            thumb.classList.toggle('opacity-100', active);
            if (bar) bar.classList.toggle('hidden', !active);
        });
    };

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', (event) => {
            event.preventDefault();
            selectThumb(thumb);
        });
    });

    if (mainImg && thumbs.length > 1) {
        mainImg.style.cursor = 'pointer';
        mainImg.addEventListener('click', () => {
            const currentIndex = thumbs.findIndex((thumb) => thumb.classList.contains('opacity-100'));
            selectThumb(thumbs[(currentIndex + 1) % thumbs.length]);
        });
    }
}

// Google Analytics bootstrap. The tag's <script data-gtag-id> carries the
// measurement ID and its options.
export function initAnalytics(doc = document) {
    const tag = doc.querySelector('script[data-gtag-id]');
    if (!tag) return;

    let options = {};
    try {
        options = JSON.parse(tag.dataset.gtagOptions || '{}');
    } catch {
        options = {};
    }

    window.dataLayer = window.dataLayer || [];
    // gtag.js reads the arguments object itself, so this must stay a function.
    window.gtag = function gtag() {
        window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', tag.dataset.gtagId, options);
}

export function initPageBehaviours(doc = document) {
    initAnalytics(doc);
    initAutoSubmit(doc);
    initPreviewForms(doc);
    initImageFallbacks(doc);
    initAchievementArchive(doc);
    initCourseListAnchors(doc);
    initFacultyDetailGallery(doc);
}
