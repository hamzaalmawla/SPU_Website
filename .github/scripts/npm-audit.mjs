// Runs `npm audit` and fails on any advisory at or above "high", except the
// advisories listed in npm-audit-ignore.json (each with a reason and the day
// to look again). An ignored advisory only covers the packages that are
// vulnerable through it; any other high advisory still fails the build.
//
// Exit codes: 0 clean, 1 advisory found, 2 registry or transport error.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const BLOCKING = new Set(['high', 'critical']);
const ignoreFile = fileURLToPath(new URL('./npm-audit-ignore.json', import.meta.url));
const ignored = new Map(JSON.parse(readFileSync(ignoreFile, 'utf8')).map((entry) => [entry.advisory, entry]));

let raw;
try {
    raw = execFileSync('npm', ['audit', '--json'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
} catch (error) {
    // npm audit exits non-zero when it finds anything; the report is still on stdout.
    raw = error.stdout ?? '';
    if (raw.trim() === '') {
        console.error(error.stderr ?? String(error));
        process.exit(2);
    }
}

let report;
try {
    report = JSON.parse(raw);
} catch {
    console.error(raw);
    process.exit(2);
}

if (report.error) {
    console.error(JSON.stringify(report.error, null, 2));
    process.exit(2);
}

const advisoryId = (url) => String(url ?? '').split('/').pop();
const seenIgnored = new Set();
const blocking = [];

for (const vulnerability of Object.values(report.vulnerabilities ?? {})) {
    for (const via of vulnerability.via ?? []) {
        // A string means "vulnerable through another package", which is reported on its own.
        if (typeof via !== 'object' || !BLOCKING.has(via.severity)) {
            continue;
        }
        const id = advisoryId(via.url);
        if (ignored.has(id)) {
            seenIgnored.add(id);
            continue;
        }
        blocking.push(`${vulnerability.name} (${via.severity}): ${via.title} ${via.url}`);
    }
}

for (const id of seenIgnored) {
    const entry = ignored.get(id);
    console.log(`::warning::Ignoring npm advisory ${id} until a fix ships (review by ${entry.reviewBy}): ${entry.reason}`);
}

for (const id of ignored.keys()) {
    if (!seenIgnored.has(id)) {
        console.log(`::warning::npm advisory ${id} no longer applies; remove it from .github/scripts/npm-audit-ignore.json`);
    }
}

if (blocking.length > 0) {
    console.log([...new Set(blocking)].join('\n'));
    process.exit(1);
}

console.log('No npm advisories at or above high, apart from the ignored ones above.');
