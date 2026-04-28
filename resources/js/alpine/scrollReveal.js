let revealObserver;

export function initRevealSections(root = document) {
    if (!revealObserver) {
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

    root.querySelectorAll('.reveal').forEach((el) => {
        if (el.dataset.revealObserved) return;
        revealObserver.observe(el);
        el.dataset.revealObserved = 'true';
    });
}
