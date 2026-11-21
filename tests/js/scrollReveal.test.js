import assert from 'node:assert/strict';
import { test } from 'node:test';

import { initRevealSections } from '../../resources/js/alpine/scrollReveal.js';

test('reveal immediately handles initial and dynamically inserted content without IntersectionObserver', () => {
    globalThis.window = {
        matchMedia: () => ({ matches: false }),
    };
    delete globalThis.IntersectionObserver;

    let mutationCallback;
    globalThis.MutationObserver = class {
        constructor(callback) {
            mutationCallback = callback;
        }

        observe() {}
    };

    const makeElement = () => ({
        nodeType: 1,
        dataset: {},
        matches: () => true,
        querySelectorAll: () => [],
        classList: {
            visible: false,
            add() { this.visible = true; },
        },
    });
    const initial = makeElement();
    const inserted = makeElement();
    const root = {
        body: {},
        querySelectorAll: () => [initial],
    };

    initRevealSections(root);
    mutationCallback([{ addedNodes: [inserted] }]);

    assert.equal(initial.classList.visible, true);
    assert.equal(inserted.classList.visible, true);
});
