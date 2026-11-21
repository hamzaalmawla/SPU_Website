import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

// The admin panel runs Filament's own Alpine. If the built admin.js reaches the public app.js
// through a shared chunk, the public bundle boots a second Alpine on the admin page and the
// panel's layout and navigation break.
test('the built admin bundle does not load the public app bundle', () => {
    const manifest = JSON.parse(readFileSync(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'));
    const seen = new Set();
    const queue = ['resources/js/filament/admin.js'];

    while (queue.length) {
        const key = queue.shift();
        if (seen.has(key)) continue;
        seen.add(key);
        queue.push(...(manifest[key]?.imports || []));
    }

    assert.ok(manifest['resources/js/filament/admin.js'], 'admin.js is missing from the manifest');
    assert.equal(seen.has('resources/js/app.js'), false, `admin.js imports app.js via ${[...seen].join(' -> ')}`);
});
