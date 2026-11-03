import assert from 'node:assert/strict';
import { test } from 'node:test';

import { daysUntil, examDayStates, examMatches, foldExamText } from '../../resources/js/alpine/examSchedule.js';

test('search text folds Arabic spellings and digits like the server', () => {
    assert.equal(foldExamText('  الإحصاءُ   الطبية ٣ '), 'الاحصاء الطبيه 3');
    assert.equal(foldExamText('Pharmacology A1'), 'pharmacology a1');
});

test('an exam matches the picked year and every search word in any order', () => {
    const exam = { year: 'y2', search: 'علم الادويه 2 قاعه a1' };

    assert.equal(examMatches(exam), true);
    assert.equal(examMatches(exam, { year: 'y2' }), true);
    assert.equal(examMatches(exam, { year: 'y1' }), false);
    assert.equal(examMatches(exam, { query: 'a1 الأدوية' }), true);
    assert.equal(examMatches(exam, { query: 'تشريح' }), false);
});

test('days until an exam are whole calendar days', () => {
    assert.equal(daysUntil('2027-01-12', '2027-01-10'), 2);
    assert.equal(daysUntil('2027-01-09', '2027-01-10'), -1);
    assert.equal(daysUntil('', '2027-01-10'), null);
});

test('only the first day still to come is the next exam day', () => {
    assert.deepEqual(
        examDayStates(['2027-01-08', '2027-01-12', '', '2027-01-14'], '2027-01-10'),
        ['done', 'next', '', ''],
    );
    assert.deepEqual(
        examDayStates(['2027-01-10', '2027-01-12'], '2027-01-10'),
        ['today', ''],
    );
});
