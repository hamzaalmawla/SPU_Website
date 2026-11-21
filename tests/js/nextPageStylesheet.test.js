import assert from 'node:assert/strict';
import { test } from 'node:test';

import { prefetchNextPageStylesheet } from '../../resources/js/public/nextPageStylesheet.js';

function fakePage({ inline = true, readyState = 'loading', saveData = false, existing = [] } = {}) {
    const appended = [];
    const listeners = {};
    const idleCallbacks = [];
    const doc = {
        readyState,
        head: { append: (node) => appended.push(node) },
        getElementById: (id) => (inline && id === 'public-stylesheet' ? { dataset: { stylesheetHref: '/build/assets/app.css' } } : null),
        querySelectorAll: () => existing.map((href) => ({ getAttribute: () => href })),
        createElement: () => ({}),
    };
    const win = {
        navigator: { connection: { saveData } },
        addEventListener: (type, handler) => {
            listeners[type] = handler;
        },
        requestIdleCallback: (callback) => idleCallbacks.push(callback),
        setTimeout: (callback) => idleCallbacks.push(callback),
    };

    return { doc, win, appended, listeners, idleCallbacks };
}

test('the stylesheet is prefetched only after the page has loaded and the browser is idle', () => {
    const page = fakePage();

    assert.equal(prefetchNextPageStylesheet(page.doc, page.win), true);
    assert.equal(page.appended.length, 0, 'nothing is fetched while the page loads');

    page.listeners.load();
    assert.equal(page.appended.length, 0, 'and nothing before the browser is idle');

    page.idleCallbacks.forEach((callback) => callback());
    assert.deepEqual(page.appended, [{ rel: 'prefetch', as: 'style', href: '/build/assets/app.css' }]);
});

test('a page that has already loaded schedules it straight away', () => {
    const page = fakePage({ readyState: 'complete' });

    prefetchNextPageStylesheet(page.doc, page.win);
    page.idleCallbacks.forEach((callback) => callback());

    assert.equal(page.appended.length, 1);
});

test('nothing is prefetched without an inline stylesheet, when saving data, or twice', () => {
    for (const options of [{ inline: false }, { saveData: true }]) {
        const page = fakePage(options);
        assert.equal(prefetchNextPageStylesheet(page.doc, page.win), false);
    }

    const page = fakePage({ readyState: 'complete', existing: ['/build/assets/app.css'] });
    prefetchNextPageStylesheet(page.doc, page.win);
    page.idleCallbacks.forEach((callback) => callback());
    assert.equal(page.appended.length, 0);
});
