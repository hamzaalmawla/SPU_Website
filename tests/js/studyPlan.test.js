import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    bindCourseCardInteractions,
    localizedStudyPlanText,
    modalFocusTarget,
    studyPlanKeyboardAction,
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

test('graph keyboard panning mirrors horizontal controls in RTL', () => {
    assert.deepEqual(studyPlanKeyboardAction('ArrowLeft', false), { panX: 40, panY: 0, zoom: 0 });
    assert.deepEqual(studyPlanKeyboardAction('ArrowLeft', true), { panX: -40, panY: 0, zoom: 0 });
    assert.deepEqual(studyPlanKeyboardAction('ArrowRight', true), { panX: 40, panY: 0, zoom: 0 });
    assert.deepEqual(studyPlanKeyboardAction('+', false), { panX: 0, panY: 0, zoom: -0.2 });
    assert.equal(studyPlanKeyboardAction('Enter', false), null);
});
