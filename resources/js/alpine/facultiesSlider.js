export function createFacultiesSlider() {
    return {
        activeFaculty: null,

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
