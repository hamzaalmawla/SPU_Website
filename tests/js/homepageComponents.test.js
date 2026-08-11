import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

import { createCalendarApp } from '../../resources/js/alpine/calendarApp.js';
import { createHeroSlider } from '../../resources/js/alpine/heroSlider.js';
import { createHonorPanel } from '../../resources/js/alpine/honorPanel.js';
import { createPathSlider } from '../../resources/js/alpine/pathSlider.js';
import { createResearchSlider } from '../../resources/js/alpine/researchSlider.js';
import { createStatsCounter } from '../../resources/js/alpine/statsCounter.js';

const originalWindow = globalThis.window;
const originalDocument = globalThis.document;
const originalIntersectionObserver = globalThis.IntersectionObserver;

afterEach(() => {
    globalThis.window = originalWindow;
    globalThis.document = originalDocument;
    globalThis.IntersectionObserver = originalIntersectionObserver;
});

function installReducedMotionBrowser() {
    globalThis.document = {
        activeElement: null,
        documentElement: { lang: 'en', direction: 'ltr' },
    };
    globalThis.window = {
        getComputedStyle: () => ({ direction: 'ltr' }),
        matchMedia: () => ({
            matches: true,
            addEventListener() {},
            removeEventListener() {},
        }),
    };
}

function root(dataset = {}) {
    return {
        dataset,
        contains: () => false,
        matches: () => false,
    };
}

test('hero and honor autoplay remain stopped under reduced motion', () => {
    installReducedMotionBrowser();

    const hero = createHeroSlider();
    hero.$el = root({ images: JSON.stringify(['/one.jpg', '/two.jpg']) });
    hero.init();

    const honor = createHonorPanel();
    honor.$el = root({
        items: JSON.stringify([{ id: 1 }, { id: 2 }]),
        itemLabel: 'Show item',
    });
    honor.init();

    assert.equal(hero._timer, null);
    assert.equal(honor._timer, null);
    assert.equal(honor.itemLabel(0), 'Show item 1 / 2');
});

test('calendar preserves the first chronological event and stops autoplay', () => {
    installReducedMotionBrowser();
    const calendar = createCalendarApp();
    calendar.$el = root({
        events: JSON.stringify([
            { id: 2, title: 'Later', startsAt: '2026-08-20' },
            { id: 1, title: 'First', startsAt: '2026-08-10' },
        ]),
    });

    calendar.init();

    assert.equal(calendar.selectedDate, '2026-08-10');
    assert.equal(calendar.selectedEvent.title, 'First');
    assert.equal(calendar.carouselInterval, null);
});

test('counters retain their server-rendered value under reduced motion', () => {
    installReducedMotionBrowser();
    const target = { dataset: { value: '1250' }, textContent: '1250' };
    const counter = createStatsCounter();
    counter.$el = { querySelectorAll: () => [target] };

    counter.init();

    assert.equal(target.textContent, '1250');
});

test('path cards flip open on tap and close on second tap when the device cannot hover', () => {
    globalThis.window = { matchMedia: () => ({ matches: false }) };

    const slider = createPathSlider();

    slider.togglePathCard(2);
    assert.equal(slider.activePathCard, 2);

    slider.togglePathCard(2);
    assert.equal(slider.activePathCard, null);
});

test('path cards ignore taps on hover-capable devices', () => {
    globalThis.window = { matchMedia: () => ({ matches: true }) };

    const slider = createPathSlider();
    slider.togglePathCard(2);

    assert.equal(slider.activePathCard, null);
});

test('path card taps on links navigate without toggling the card', () => {
    globalThis.window = { matchMedia: () => ({ matches: false }) };

    const slider = createPathSlider();
    const linkEvent = { target: { closest: (selector) => (selector === 'a, button' ? {} : null) } };
    const plainEvent = { target: { closest: () => null } };

    slider.handlePathCardTap(linkEvent, 1);
    assert.equal(slider.activePathCard, null);

    slider.handlePathCardTap(plainEvent, 1);
    assert.equal(slider.activePathCard, 1);
});

test('research slider advances by the rendered card width and gap', () => {
    globalThis.window = {
        getComputedStyle: () => ({ direction: 'ltr', columnGap: '32px', gap: '32px' }),
        matchMedia: () => ({ matches: false }),
    };

    const scrollCalls = [];
    const slider = createResearchSlider();
    const track = {
        querySelector: () => ({ getBoundingClientRect: () => ({ width: 262 }) }),
        scrollBy: (options) => scrollCalls.push(options),
    };
    slider.$refs = { researchTrack: track };

    slider.slide('next');

    assert.deepEqual(scrollCalls, [{ left: 294, behavior: 'smooth' }]);
});
