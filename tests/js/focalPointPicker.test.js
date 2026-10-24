import assert from 'node:assert/strict';
import test from 'node:test';

import { clampPercentage, createFocalPointPicker, pointerToFocalPoint } from '../../resources/js/filament/focalPoint.js';

test('pointer coordinates map to visual focal percentages', () => {
    const bounds = { left: 100, top: 50, width: 400, height: 200 };

    assert.deepEqual(pointerToFocalPoint(300, 100, bounds), { x: 50, y: 25 });
    assert.deepEqual(pointerToFocalPoint(50, 300, bounds), { x: 0, y: 100 });
});

test('keyboard movement and centering remain within image bounds', () => {
    const picker = createFocalPointPicker({ x: 99, y: 1, fit: 'cover' });
    picker.init();
    picker.handleKeydown({ key: 'ArrowRight', shiftKey: true, preventDefault() {} });
    picker.handleKeydown({ key: 'ArrowUp', shiftKey: true, preventDefault() {} });

    assert.equal(picker.x, 100);
    assert.equal(picker.y, 0);
    assert.equal(clampPercentage(-8), 0);

    picker.center();
    assert.equal(picker.x, 50);
    assert.equal(picker.y, 50);
});
