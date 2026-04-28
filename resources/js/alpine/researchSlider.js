const RESEARCH_CARD_STEP = 342;

export function createResearchSlider() {
    return {
        slide(direction) {
            const track = this.$refs.researchTrack;
            if (!track) return;

            track.scrollBy({
                left: direction === 'right' ? RESEARCH_CARD_STEP : -RESEARCH_CARD_STEP,
                behavior: 'smooth',
            });
        },
    };
}
