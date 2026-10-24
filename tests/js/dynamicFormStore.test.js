import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

globalThis.document = {
    documentElement: { lang: 'ar' },
    querySelector: () => null,
    getElementById: () => null,
};
globalThis.window = { setTimeout: (callback) => callback() };

const { registerDynamicFormStore } = await import('../../resources/js/alpine/dynamicFormStore.js');

const builtInForms = JSON.parse(readFileSync(new URL('../../database/seeders/Data/forms/built-in-forms.json', import.meta.url), 'utf8'));

// The page prints the form the way FormDefinitionService::publicSchema() shapes it.
function schemaFor(formId) {
    const form = builtInForms.find((item) => item.key === formId);

    return form.multiStep ? { multiStep: true, steps: form.steps } : { fields: form.steps.flatMap((step) => step.fields) };
}

function openForm(formId, locale = 'ar', context = {}) {
    let store;
    registerDynamicFormStore({ store: (name, definition) => { store = definition; } });
    store.open(formId, locale, null, context, schemaFor(formId));

    return store;
}

function answerWith(status, body) {
    globalThis.fetch = async () => ({ status, ok: status < 400, json: async () => body });
}

test('a server message is shown under its own field, not as "required"', async () => {
    const store = openForm('activity-registration');
    answerWith(422, { errors: { phone: ['صيغة حقل رقم الهاتف غير صالحة.'] } });

    await store.submit();

    assert.equal(store.errors.phone, 'صيغة حقل رقم الهاتف غير صالحة.');
    assert.equal(store.submitError, 'يرجى تصحيح الحقول المشار إليها.');
});

test('a message that is not about a field is shown at the top', async () => {
    const store = openForm('activity-registration', 'en', { eventSource: 'news-events', eventId: '7' });
    answerWith(422, { errors: { event_id: ['The selected event is full.'] } });

    await store.submit();

    assert.deepEqual(store.errors, {});
    assert.equal(store.submitError, 'The selected event is full.');
});

test('a multi-step form opens the step that holds the field the server refused', async () => {
    const store = openForm('job-application', 'en', { jobId: 'job-1', jobSlug: 'lecturer' });
    const cvStep = store.schema.steps.findIndex((step) => step.fields.some((field) => field.name === 'cvFile'));
    answerWith(422, { errors: { cvFile: ['The CV must be a PDF.'] } });

    await store.submit();

    assert.equal(store.currentStep, cvStep);
    assert.equal(store.errors.cvFile, 'The CV must be a PDF.');
});

test('a CV that is not a PDF, or is over 5 MB, is refused when it is chosen', () => {
    const store = openForm('job-application', 'en');

    store.setFile('cvFile', { name: 'cv.docx', size: 1000 });
    assert.equal(store.errors.cvFile, 'This file type is not accepted. Allowed: PDF.');
    assert.equal(store.files.cvFile, undefined);

    store.setFile('cvFile', { name: 'cv.pdf', size: 6 * 1024 * 1024 });
    assert.equal(store.errors.cvFile, 'The file is larger than 5 MB.');

    store.setFile('cvFile', { name: 'cv.pdf', size: 1000 });
    assert.equal(store.errors.cvFile, undefined);
    assert.equal(store.formData.cvFile, 'cv.pdf');
});

test('the page check names the problem instead of a fixed text', () => {
    const store = openForm('activity-registration', 'en');
    store.formData.fullName = 'Sara';
    store.formData.email = 'not-an-email';

    assert.equal(store.validate(), false);
    assert.equal(store.errors.email, 'Enter a valid email address.');
});

test('a form the page did not print (switched off) shows nothing', () => {
    let store;
    registerDynamicFormStore({ store: (name, definition) => { store = definition; } });
    store.open('activity-registration', 'ar', null, {}, null);

    assert.equal(store.schema, null);
    assert.deepEqual(store.fields(), []);
});
