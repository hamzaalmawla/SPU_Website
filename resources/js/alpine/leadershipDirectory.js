export function createLeadershipDirectory() {
    return {
        faculty: '',
        currentDeanIndex: 0,
        deanCount: 0,
        visibleDeanCount: 1,
        touchStartX: null,
        resizeHandler: null,

        init() {
            this.faculty = this.$el.dataset.initialFaculty || '';
            this.deanCount = Number.parseInt(this.$el.dataset.deanCount || '0', 10);
            this.resizeHandler = () => this.updateVisibleDeanCount();
            this.updateVisibleDeanCount();
            window.addEventListener('resize', this.resizeHandler, { passive: true });
        },

        destroy() {
            if (this.resizeHandler) {
                window.removeEventListener('resize', this.resizeHandler);
            }
        },

        updateVisibleDeanCount() {
            this.visibleDeanCount = window.matchMedia('(min-width: 1025px)').matches ? 3 : 1;
            this.currentDeanIndex = Math.min(this.currentDeanIndex, this.maxDeanIndex());
        },

        maxDeanIndex() {
            return Math.max(this.deanCount - this.visibleDeanCount, 0);
        },

        showInstitutional() {
            return this.faculty === '';
        },

        deanVisible(index, facultySlug) {
            if (this.faculty !== '') {
                return this.faculty === facultySlug;
            }

            return index >= this.currentDeanIndex && index < this.currentDeanIndex + this.visibleDeanCount;
        },

        changeFaculty() {
            this.currentDeanIndex = 0;
            const url = new URL(window.location.href);

            if (this.faculty === '') {
                url.searchParams.delete('faculty');
            } else {
                url.searchParams.set('faculty', this.faculty);
            }

            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
            this.syncLanguageLinks();
        },

        syncLanguageLinks() {
            document.querySelectorAll('[data-language-switch]').forEach((link) => {
                const url = new URL(link.href, window.location.origin);

                if (this.faculty === '') {
                    url.searchParams.delete('faculty');
                } else {
                    url.searchParams.set('faculty', this.faculty);
                }

                link.href = `${url.pathname}${url.search}${url.hash}`;
            });
        },

        previousDean() {
            if (this.faculty === '') {
                this.currentDeanIndex = Math.max(this.currentDeanIndex - 1, 0);
            }
        },

        nextDean() {
            if (this.faculty === '') {
                this.currentDeanIndex = Math.min(this.currentDeanIndex + 1, this.maxDeanIndex());
            }
        },

        previousDisabled() {
            return this.faculty !== '' || this.currentDeanIndex <= 0;
        },

        nextDisabled() {
            return this.faculty !== '' || this.currentDeanIndex >= this.maxDeanIndex();
        },

        handleArrowLeft() {
            document.documentElement.dir === 'rtl' ? this.nextDean() : this.previousDean();
        },

        handleArrowRight() {
            document.documentElement.dir === 'rtl' ? this.previousDean() : this.nextDean();
        },

        startTouch(event) {
            this.touchStartX = event.touches?.[0]?.clientX ?? null;
        },

        endTouch(event) {
            const endX = event.changedTouches?.[0]?.clientX;

            if (this.touchStartX === null || typeof endX !== 'number') {
                return;
            }

            const distance = endX - this.touchStartX;
            this.touchStartX = null;

            if (Math.abs(distance) < 45) {
                return;
            }

            if (document.documentElement.dir === 'rtl') {
                distance > 0 ? this.nextDean() : this.previousDean();
            } else {
                distance > 0 ? this.previousDean() : this.nextDean();
            }
        },

        statusText() {
            const ofLabel = this.$el.dataset.ofLabel || 'of';

            if (this.faculty !== '') {
                return `1 ${ofLabel} 1`;
            }

            const start = Math.min(this.currentDeanIndex + 1, this.deanCount);
            const end = Math.min(this.currentDeanIndex + this.visibleDeanCount, this.deanCount);

            return `${start}-${end} ${ofLabel} ${this.deanCount}`;
        },
    };
}
