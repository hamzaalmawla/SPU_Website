import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';

import { registerDynamicFormStore } from '../../resources/js/alpine/dynamicFormStore.js';
import { createDynamicFormView } from '../../resources/js/alpine/dynamicFormView.js';
import { createMobileNav, NAV_DESKTOP_BREAKPOINT } from '../../resources/js/alpine/mobileNav.js';

const originalDocument = globalThis.document;
const originalWindow = globalThis.window;

afterEach(() => {
    globalThis.document = originalDocument;
    globalThis.window = originalWindow;
});

test('navigation disclosures restore focus when Escape closes them', () => {
    let focused = '';
    const nav = createMobileNav();
    nav.$nextTick = (callback) => callback();
    nav.$refs = { mobileToggle: { focus: () => { focused = 'mobile'; } } };
    nav.$el = {
        querySelector: (selector) => ({ focus: () => { focused = selector; } }),
    };

    nav.openMenu = '2';
    nav.handleEscape();
    assert.equal(nav.openMenu, null);
    assert.match(focused, /nav \[data-dropdown-trigger="2"\]/);

    nav.mobileNav = true;
    nav.handleEscape();
    assert.equal(nav.mobileNav, false);
    assert.equal(focused, 'mobile');
});

test('navigation focus-out only closes a dropdown when focus leaves its item', () => {
    const nav = createMobileNav();
    nav.openMenu = '1';
    nav.closeDropdownForFocus({ currentTarget: { contains: (node) => node === 'inside' }, relatedTarget: 'inside' });
    assert.equal(nav.openMenu, '1');
    nav.closeDropdownForFocus({ currentTarget: { contains: () => false }, relatedTarget: 'outside' });
    assert.equal(nav.openMenu, null);
});

test('navigation initializes from scroll position and resets mobile state at desktop width', () => {
    const listeners = {};
    const classes = new Set();
    globalThis.document = {
        documentElement: {
            classList: {
                toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
            },
        },
    };
    globalThis.window = {
        scrollY: 80,
        innerWidth: NAV_DESKTOP_BREAKPOINT - 1,
        addEventListener: (name, callback) => { listeners[name] = callback; },
        removeEventListener: () => {},
    };

    const nav = createMobileNav();
    nav.$el = { dataset: { searchItems: '[]' } };
    nav.init();
    assert.equal(nav.stickyNav, true);

    nav.$nextTick = () => {};
    nav.toggleMobile();
    assert.equal(nav.mobileNav, true);
    assert.equal(classes.has('site-navigation-open'), true);

    window.innerWidth = NAV_DESKTOP_BREAKPOINT;
    listeners.resize();
    assert.equal(nav.mobileNav, false);
    assert.equal(classes.has('site-navigation-open'), false);
});

test('dynamic forms generate deterministic field and error associations', () => {
    let store;
    globalThis.document = {
        documentElement: { lang: 'en' },
        getElementById: () => null,
    };
    globalThis.window = { setTimeout: (callback) => callback() };
    registerDynamicFormStore({ store: (_name, value) => { store = value; } });

    store.open('job-application', 'en');
    const field = store.fields()[0];
    const view = createDynamicFormView();
    view.$store = { dynamicForm: store };

    assert.match(view.fieldId(field), /^dynamic-job-application-/);
    store.errors[field.name] = true;
    assert.equal(view.invalid(field), 'true');
    assert.equal(view.describedBy(field), `${view.fieldId(field)}-error`);
});

test('dynamic form validation focuses the first invalid control', () => {
    let store;
    let focusedId = '';
    globalThis.document = {
        documentElement: { lang: 'en' },
        getElementById: (id) => ({ focus: () => { focusedId = id; } }),
    };
    globalThis.window = { setTimeout: (callback) => callback() };
    registerDynamicFormStore({ store: (_name, value) => { store = value; } });

    store.open('job-application', 'en');
    assert.equal(store.validate(), false);
    assert.equal(focusedId, store.fieldId(Object.keys(store.errors)[0]));
});
