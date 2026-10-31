import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

import {
    elementDirection,
    horizontalKeyAction,
    scrollByDirection,
} from '../../resources/js/utils/motionDirection.js';

const originalWindow = globalThis.window;
const originalDocument = globalThis.document;

afterEach(() => {
    globalThis.window = originalWindow;
    globalThis.document = originalDocument;
});

function installBrowser({ reducedMotion = false } = {}) {
    globalThis.document = { documentElement: { direction: 'ltr' } };
    globalThis.window = {
        getComputedStyle: (element) => ({ direction: element.direction }),
        matchMedia: () => ({
            matches: reducedMotion,
            addEventListener() {},
            removeEventListener() {},
        }),
    };
}

test('direction and arrow actions come from computed element direction', () => {
    installBrowser();

    assert.equal(elementDirection({ direction: 'rtl' }), 'rtl');
    assert.equal(horizontalKeyAction({ key: 'ArrowLeft' }, { direction: 'rtl' }), 'next');
    assert.equal(horizontalKeyAction({ key: 'ArrowRight' }, { direction: 'rtl' }), 'previous');
    assert.equal(horizontalKeyAction({ key: 'ArrowRight' }, { direction: 'ltr' }), 'next');
});

test('logical scrolling uses the correct RTL delta', () => {
    installBrowser();
    const calls = [];
    const track = { direction: 'rtl', scrollBy: (options) => calls.push(options) };

    scrollByDirection(track, 'next', 300);
    scrollByDirection(track, 'previous', 300);

    assert.deepEqual(calls, [
        { left: -300, behavior: 'smooth' },
        { left: 300, behavior: 'smooth' },
    ]);
});

test('reduced motion disables smooth scrolling', () => {
    installBrowser({ reducedMotion: true });
    let options;

    scrollByDirection({
        direction: 'ltr',
        scrollBy: (value) => { options = value; },
    }, 'next', 120);

    assert.deepEqual(options, { left: 120, behavior: 'auto' });
});
