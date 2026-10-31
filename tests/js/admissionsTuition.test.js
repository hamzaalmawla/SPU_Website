import assert from 'node:assert/strict';
import { test } from 'node:test';

import { createAdmissionsTuition } from '../../resources/js/alpine/admissionsTuition.js';

function tuitionWithRows(faculties) {
    const component = createAdmissionsTuition();
    const rows = faculties.map((faculty) => ({ dataset: { faculty } }));
    component.$root = { querySelectorAll: () => rows };

    return { component, rows };
}

test('the faculty filter shows only that faculty and the empty state when nothing matches', () => {
    const { component, rows } = tuitionWithRows(['الطب', 'الصيدلة']);

    assert.deepEqual(rows.map((row) => component.rowVisible(row)), [true, true]);

    component.selectedFaculty = 'الصيدلة';
    assert.deepEqual(rows.map((row) => component.rowVisible(row)), [false, true]);
    assert.equal(component.emptyStateVisible(), false);

    component.selectedFaculty = 'Missing';
    assert.equal(component.emptyStateVisible(), true);
});

test('the student type filter shows only that type of fee column', () => {
    const { component } = tuitionWithRows(['Medicine']);

    assert.equal(component.feeColumnVisible('syrian'), true);
    assert.equal(component.feeColumnVisible('foreign'), true);

    component.selectedType = 'foreign';
    assert.equal(component.feeColumnVisible('syrian'), false);
    assert.equal(component.feeColumnVisible('foreign'), true);
});
