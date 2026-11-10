import { prefersReducedMotion } from '../utils/motionDirection.js';

let revealObserver;
let mutationObserver;

const REVEAL_SELECTOR = '.reveal, .reveal-up, .reveal-left, .reveal-right';

function revealElements(root) {
    const elements = [];

    if (typeof root.matches === 'function' && root.matches(REVEAL_SELECTOR)) elements.push(root);
    if (typeof root.querySelectorAll === 'function') elements.push(...root.querySelectorAll(REVEAL_SELECTOR));

    const revealImmediately = prefersReducedMotion() || typeof IntersectionObserver === 'undefined' || !revealObserver;

    elements.forEach((element) => {
        if (element.dataset.revealObserved) return;

        element.dataset.revealObserved = 'true';
        if (revealImmediately) {
            element.classList.add('reveal-visible');
            return;
        }

        revealObserver.observe(element);
    });
}

export function initRevealSections(root = document) {
    if (!revealObserver && !prefersReducedMotion() && typeof IntersectionObserver !== 'undefined') {
        revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        entry.target.classList.add('reveal-visible');
                    });
                });

                revealObserver.unobserve(entry.target);
            });
        }, {
            threshold: 0.05,
            rootMargin: '100px 0px',
        });
    }

    revealElements(root);

    if (!mutationObserver && typeof MutationObserver !== 'undefined') {
        mutationObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) revealElements(node);
                });
            });
        });

        const target = root.body || root.documentElement || root;
        mutationObserver.observe(target, { childList: true, subtree: true });
    }
}
