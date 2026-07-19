const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

function motionMediaQuery() {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return null;
    }

    return window.matchMedia(REDUCED_MOTION_QUERY);
}

export function prefersReducedMotion() {
    return motionMediaQuery()?.matches ?? false;
}

export function observeReducedMotion(callback) {
    const mediaQuery = motionMediaQuery();
    callback(mediaQuery?.matches ?? false);

    if (!mediaQuery || typeof mediaQuery.addEventListener !== 'function') {
        return () => {};
    }

    const handleChange = (event) => callback(event.matches);
    mediaQuery.addEventListener('change', handleChange);

    return () => mediaQuery.removeEventListener('change', handleChange);
}

export function elementDirection(element) {
    const fallback = typeof document !== 'undefined' ? document.documentElement : null;

    for (const candidate of [element, fallback]) {
        if (!candidate || typeof window === 'undefined' || typeof window.getComputedStyle !== 'function') {
            continue;
        }

        const direction = window.getComputedStyle(candidate).direction;
        if (direction === 'rtl' || direction === 'ltr') {
            return direction;
        }
    }

    return 'ltr';
}

export function horizontalKeyAction(event, element) {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
        return null;
    }

    const pointsForward = event.key === 'ArrowRight'
        ? elementDirection(element) === 'ltr'
        : elementDirection(element) === 'rtl';

    return pointsForward ? 'next' : 'previous';
}

export function scrollByDirection(track, action, distance) {
    const forward = action === 'next' ? 1 : -1;
    const direction = elementDirection(track) === 'rtl' ? -1 : 1;

    track.scrollBy({
        left: forward * direction * distance,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
}
