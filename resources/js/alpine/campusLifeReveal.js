export function createCampusLifeReveal() {
    return {
        init() {
            if (!('IntersectionObserver' in window)) {
                this.$root.querySelectorAll('[data-campus-reveal]').forEach((element) => element.classList.add('is-visible'));
                return;
            }

            this.$root.querySelectorAll('[data-campus-reveal]').forEach((element) => {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    });
                }, { threshold: 0.15 });

                observer.observe(element);
            });
        },
    };
}
