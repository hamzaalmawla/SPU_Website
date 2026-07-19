import assert from 'node:assert/strict';
import test from 'node:test';
import { createVirtualTour } from '../../resources/js/alpine/virtualTour.js';

function component({ reducedMotion = false } = {}) {
    global.window = {
        matchMedia: () => ({ matches: reducedMotion }),
        setInterval: () => 12,
        clearInterval: () => {},
    };
    global.document = {
        fullscreenElement: null,
        addEventListener: () => {},
        exitFullscreen: async () => {},
    };
    const tour = createVirtualTour();
    tour.$el = { dataset: { autoplayInterval: '4000', playLabel: 'Play', pauseLabel: 'Pause', fullscreenLabel: 'Full', exitFullscreenLabel: 'Exit' } };
    tour.$refs = { sceneData: { textContent: JSON.stringify([
        { id: 'one', title: 'One', image: '/one.jpg', hotspots: [{ id: 'h', label: 'Hotspot', x: 150, y: -5, targetSceneId: 'two' }] },
        { id: 'two', title: 'Two', image: '/two.jpg', hotspots: [] },
    ]) } };
    tour.init();
    return tour;
}

test('virtual tour bounds zoom, pan, hotspot positions, and scene switching', () => {
    const tour = component();
    tour.setZoom(10);
    tour.panX = 200;
    tour.panY = -200;
    tour.boundPan();
    assert.equal(tour.zoom, 3);
    assert.equal(tour.panX, 50);
    assert.equal(tour.panY, -50);
    assert.equal(tour.activeHotspots[0].style, 'left:100%;top:0%');
    tour.activateHotspot({ currentTarget: { dataset: { targetScene: 'two' } } });
    assert.equal(tour.activeIndex, 1);
    assert.equal(tour.zoom, 1);
});

test('virtual tour supports keyboard pan and zoom', () => {
    const tour = component();
    const event = (key) => ({ key, preventDefault() {} });
    tour.handleKey(event('+'));
    tour.handleKey(event('ArrowRight'));
    assert.equal(tour.zoom, 1.25);
    assert.equal(tour.panX, 5);
    tour.handleKey(event('0'));
    assert.equal(tour.zoom, 1);
    assert.equal(tour.panX, 0);
});

test('virtual tour autoplay stays paused for reduced motion', () => {
    const tour = component({ reducedMotion: true });
    tour.startAutoplay();
    assert.equal(tour.autoplay, false);
    assert.equal(tour.autoplayTimer, null);
});

test('virtual tour fullscreen gracefully falls back', async () => {
    const tour = component();
    tour.$refs.viewer = {};
    await tour.toggleFullscreen();
    assert.equal(tour.fallbackFullscreen, true);
    await tour.toggleFullscreen();
    assert.equal(tour.fallbackFullscreen, false);
});
