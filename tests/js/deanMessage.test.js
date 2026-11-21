import assert from 'node:assert/strict';
import { test } from 'node:test';

import { createDeanMessage } from '../../resources/js/alpine/deanMessage.js';

function deanMessage({ photoHeight, textTop, textHeight }) {
    const component = createDeanMessage();
    component.$refs = {
        photo: { getBoundingClientRect: () => ({ height: photoHeight }) },
        text: { offsetTop: textTop, scrollHeight: textHeight },
    };
    component.$root = { dataset: { moreLabel: 'Show more', lessLabel: 'Show less' } };
    component.measure();

    return component;
}

test('a message that fits beside the photo shows in full, with no button', () => {
    const component = deanMessage({ photoHeight: 700, textTop: 120, textHeight: 400 });

    assert.equal(component.overflowing, false);
    assert.equal(component.isCollapsed(), false);
    assert.equal(component.textStyle(), '');
});

test('a longer message is cut at the photo height until "show more" is pressed', () => {
    const component = deanMessage({ photoHeight: 700, textTop: 120, textHeight: 1600 });

    assert.equal(component.overflowing, true);
    assert.equal(component.textStyle(), 'max-height: 580px');
    assert.equal(component.toggleLabel(), 'Show more');
    assert.equal(component.ariaExpanded(), 'false');

    component.toggle();

    assert.equal(component.textStyle(), '');
    assert.equal(component.toggleLabel(), 'Show less');
    assert.equal(component.ariaExpanded(), 'true');
});
