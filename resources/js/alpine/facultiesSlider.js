export function createFacultiesSlider() {
    return {
        activeFaculty: null,

        setActiveFaculty(index) {
            this.activeFaculty = index;
        },

        clearActiveFaculty() {
            this.activeFaculty = null;
        },

        facultyCardClass(index) {
            if (this.activeFaculty === null) {
                return 'opacity-100';
            }

            if (this.activeFaculty === index) {
                return 'opacity-100 scale-[1.02] z-20 shadow-2xl border-transparent';
            }

            return 'opacity-50 grayscale-[0.2]';
        },

        slideFaculties(direction) {
            const track = this.$refs.facultiesTrack;
            if (!track) return;
            const firstCard = track.querySelector('.faculty-card');
            const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 292;
            const gap = 24;
            const step = Math.round(cardWidth + gap);
            track.scrollBy({ left: direction === 'right' ? step : -step, behavior: 'smooth' });
        },
    };
}
