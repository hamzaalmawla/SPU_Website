import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    bindCourseCardInteractions,
    buildEdgePath,
    clampPan,
    localizedStudyPlanText,
    modalFocusTarget,
    pinchScale,
    planEdgePaths,
    studyPlanKeyboardAction,
    transitiveReduction,
} from '../../resources/js/alpine/studyPlan.js';

test('current localized plain text wins over stale suffix values', () => {
    const course = { title: 'Current title', titleEn: 'Stale title', titleAr: 'عنوان قديم' };

    assert.equal(localizedStudyPlanText(course, 'title', false), 'Current title');
    assert.equal(localizedStudyPlanText(course, 'title', true), 'Current title');
});

test('dialog focus containment wraps and recovers escaped focus', () => {
    const first = { id: 'first' };
    const middle = { id: 'middle' };
    const last = { id: 'last' };
    const focusable = [first, middle, last];

    assert.equal(modalFocusTarget(focusable, first, true), last);
    assert.equal(modalFocusTarget(focusable, last, false), first);
    assert.equal(modalFocusTarget(focusable, { id: 'outside' }, false), first);
    assert.equal(modalFocusTarget(focusable, middle, false), null);
});

test('course focus and hover share prerequisite highlighting behavior', () => {
    const listeners = {};
    const card = {
        dataset: { courseId: 'course-101' },
        addEventListener: (type, listener) => { listeners[type] = listener; },
    };
    const highlighted = [];
    let cleared = 0;
    let opened;

    bindCourseCardInteractions(card, {
        highlight: (courseId) => highlighted.push(courseId),
        clear: () => { cleared += 1; },
        open: (courseId, trigger) => { opened = [courseId, trigger]; },
    });

    listeners.mouseenter();
    listeners.focus();
    listeners.mouseleave();
    listeners.blur();
    listeners.click();

    assert.deepEqual(highlighted, ['course-101', 'course-101']);
    assert.equal(cleared, 2);
    assert.deepEqual(opened, ['course-101', card]);
});

test('graph keyboard panning follows the screen direction in both locales', () => {
    // The graph is always laid out left to right, so Arabic does not mirror the arrow keys.
    assert.deepEqual(studyPlanKeyboardAction('ArrowLeft'), { panX: 40, panY: 0, zoom: 0 });
    assert.deepEqual(studyPlanKeyboardAction('ArrowRight'), { panX: -40, panY: 0, zoom: 0 });
    assert.deepEqual(studyPlanKeyboardAction('+'), { panX: 0, panY: 0, zoom: -0.2 });
    assert.equal(studyPlanKeyboardAction('Enter'), null);
});

test('transitive reduction drops edges implied by a longer path', () => {
    const courses = [
        { id: 'a', prerequisites: [] },
        { id: 'b', prerequisites: ['a'] },
        { id: 'c', prerequisites: ['b', 'a'] },
    ];

    assert.deepEqual(transitiveReduction(courses), [
        { source: 'a', target: 'b' },
        { source: 'b', target: 'c' },
    ]);
});

test('edge paths fan out and skip links that do not point forward', () => {
    const source = { left: 0, right: 150, centerY: 100, height: 100 };
    const target = { left: 300, right: 450, centerY: 300, height: 100 };

    assert.equal(buildEdgePath(source, { ...target, left: 150 }), null, 'same-term link has no forward gap');
    assert.equal(buildEdgePath(source, { ...target, left: 100 }), null, 'backward link has no forward gap');

    const single = buildEdgePath(source, target);
    assert.match(single, /^M 150 100 C .* 300 300$/);

    const first = buildEdgePath(source, target, { outIndex: 0, outCount: 2 });
    const second = buildEdgePath(source, target, { outIndex: 1, outCount: 2 });
    assert.notEqual(first, second);
    assert.match(first, /^M 150 95 /);
    assert.match(second, /^M 150 105 /);
});

test('fan offsets stay inside the card height', () => {
    const source = { left: 0, right: 150, centerY: 100, height: 40 };
    const target = { left: 300, right: 450, centerY: 100, height: 40 };

    // 40px card: offsets are limited to +-12px however many edges share the anchor.
    assert.match(buildEdgePath(source, target, { outIndex: 0, outCount: 20 }), /^M 150 88 /);
    assert.match(buildEdgePath(source, target, { outIndex: 19, outCount: 20 }), /^M 150 112 /);
});

test('pan is clamped so part of the board always stays visible', () => {
    const view = { panX: 5000, panY: -5000, scale: 1 };
    const clamped = clampPan(view, 800, 600, 2000, 1000, 80);

    assert.equal(clamped.panX, 720);
    assert.equal(clamped.panY, 80 - 1000);
    assert.deepEqual(clampPan({ panX: 10, panY: 10, scale: 1 }, 800, 600, 2000, 1000, 80), { panX: 10, panY: 10 });
});

test('pinch scale follows the finger distance and respects the limits', () => {
    assert.equal(pinchScale(1, 100, 200, 0.1, 3), 2);
    assert.equal(pinchScale(1, 100, 1000, 0.1, 3), 3);
    assert.equal(pinchScale(1, 100, 1, 0.1, 3), 0.1);
    assert.equal(pinchScale(1.5, 0, 200, 0.1, 3), 1.5);
});

test('edge planner routes reduced forward edges and skips backward ones', () => {
    const geometry = new Map([
        ['a', { left: 0, right: 150, centerY: 50, height: 100 }],
        ['b', { left: 300, right: 450, centerY: 50, height: 100 }],
        ['c', { left: 600, right: 750, centerY: 50, height: 100 }],
        ['d', { left: 300, right: 450, centerY: 200, height: 100 }],
    ]);
    const courses = [
        { id: 'a', prerequisites: [] },
        { id: 'b', prerequisites: ['a'] },
        { id: 'c', prerequisites: ['b', 'a'] },
        { id: 'd', prerequisites: ['b'] },
    ];

    const paths = planEdgePaths(geometry, courses);

    // a->c is implied by a->b->c, and b->d is same-term (both at left 300), so it is not drawn.
    assert.deepEqual(paths.map((p) => `${p.sourceId}>${p.targetId}`).sort(), ['a>b', 'b>c']);
    assert.ok(paths.every((p) => p.path.startsWith('M ')));
});
