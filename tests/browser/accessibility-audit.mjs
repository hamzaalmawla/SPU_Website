#!/usr/bin/env node
//
// Rendered-page accessibility audit, driven over the Chrome DevTools Protocol.
//
// WHY THIS EXISTS
//
// The PHP and JS suites already assert the things that live in markup: landmarks,
// heading order, disclosure relationships, focus restoration on Escape, form
// field associations. None of them can see a rendered page, and four of the most
// common real accessibility defects only exist once one is rendered:
//
//   - a focus ring that is invisible, or removed by a reset
//   - a tab order that does not follow the visual order
//   - a page that scrolls sideways, which in RTL is where it usually happens
//   - animation that ignores prefers-reduced-motion
//
// WHAT THIS IS NOT
//
// It is not a screen-reader test and it is not accessibility sign-off. No
// automated tool can tell you how NVDA or VoiceOver announces a page, and
// FRONTEND_ROUTE_PARITY_MATRIX.md asks for exactly that. This narrows what a
// human has to check by hand; it does not replace them.
//
// Zero dependencies on purpose: Node 22+ has a global WebSocket, and this drives
// the Chrome already installed on the machine. Nothing to install, nothing to
// keep up to date, so it still runs in a year.
//
//   node tests/browser/accessibility-audit.mjs                  # v2.spu.edu.sy
//   node tests/browser/accessibility-audit.mjs http://localhost:8000
//
// Exits non-zero if any finding is reported.

import { spawn } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BASE = (process.argv[2] ?? 'https://v2.spu.edu.sy').replace(/\/$/, '');

// Bounded on purpose, and the bound is stated in the report rather than left
// implicit. These are the highest-traffic templates: both locales of the
// homepage and of one page per top-level section. Every other route reuses one
// of these layouts, so a layout defect shows up here — a content defect on an
// unlisted route does not, and this run does not claim otherwise.
const ROUTES = [
  '/ar', '/en',
  '/ar/about', '/en/about',
  '/ar/admissions', '/en/admissions',
  '/ar/news', '/en/news',
  '/ar/facilities', '/en/facilities',
  '/ar/campus-life', '/en/campus-life',
  '/en/research/publications',
];

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];

const CHROME = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const findings = [];
let overImages = 0;
const report = (route, viewport, check, detail) =>
  findings.push({ route, viewport, check, detail });

// ---------------------------------------------------------------- CDP client

let nextId = 0;
const pending = new Map();
const listeners = new Map();

function send(ws, method, params = {}, sessionId) {
  const id = ++nextId;
  return new Promise((resolve, reject) => {
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params, sessionId }));
  });
}

function on(event, fn) {
  if (!listeners.has(event)) listeners.set(event, []);
  listeners.get(event).push(fn);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ------------------------------------------------------------- in-page probes

// Runs in the page. Kept as a single string so it can be handed to
// Runtime.evaluate without a build step.
const PAGE_PROBES = `(() => {
  const out = {};
  const doc = document.documentElement;

  // Horizontal overflow. A page that scrolls sideways is a usability defect at
  // any width and a very common one in RTL, where a single unconstrained child
  // pushes the whole document. 2px of tolerance for subpixel rounding.
  out.overflow = {
    scrollWidth: doc.scrollWidth,
    clientWidth: doc.clientWidth,
    overflows: doc.scrollWidth - doc.clientWidth > 2,
  };

  // The widest elements, so a reported overflow is actionable rather than a
  // number to go hunting with.
  if (out.overflow.overflows) {
    const limit = doc.clientWidth;
    out.overflow.culprits = [...document.querySelectorAll('body *')]
      .map((el) => {
        const r = el.getBoundingClientRect();
        return { el, right: r.right, left: r.left, w: r.width };
      })
      .filter((x) => x.right > limit + 2 || x.left < -2)
      .sort((a, b) => (b.right - b.left) - (a.right - a.left))
      .slice(0, 4)
      .map((x) => {
        const el = x.el;
        const id = el.id ? '#' + el.id : '';
        const cls = typeof el.className === 'string' && el.className
          ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.')
          : '';
        return el.tagName.toLowerCase() + id + cls
          + ' [' + Math.round(x.left) + '→' + Math.round(x.right) + ']';
      });
  }

  out.dir = doc.getAttribute('dir');
  out.lang = doc.getAttribute('lang');
  out.hasMain = !!document.querySelector('main');
  out.h1Count = document.querySelectorAll('h1').length;

  // Skip link: the first focusable thing on the page should move focus into the
  // main content. Verified by target here; the traversal check confirms it is
  // reachable and visible when focused.
  const first = document.querySelector('a[href^="#"]');
  out.skipLink = first
    ? { text: (first.textContent || '').trim().slice(0, 40), href: first.getAttribute('href'),
        targetExists: !!document.querySelector(first.getAttribute('href')) }
    : null;

  // Images must carry an alt attribute — an absent one is a defect, an empty
  // one is a decorative-image declaration and is fine.
  out.imagesWithoutAlt = [...document.images]
    .filter((img) => !img.hasAttribute('alt'))
    .slice(0, 5)
    .map((img) => (img.currentSrc || img.src || '').split('/').pop());

  // Controls with no accessible name at all.
  out.namelessControls = [...document.querySelectorAll('button, a[href], input, select, textarea')]
    .filter((el) => {
      const r = el.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) return false;
      if (el.getAttribute('aria-hidden') === 'true') return false;
      const name = (el.getAttribute('aria-label') || '').trim()
        || (el.getAttribute('title') || '').trim()
        || (el.textContent || '').trim()
        || (el.getAttribute('alt') || '').trim()
        || (el.labels && el.labels.length ? 'labelled' : '')
        || (el.querySelector('img[alt]:not([alt=""])') ? 'img-alt' : '')
        || (() => {
          const ref = el.getAttribute('aria-labelledby');
          if (!ref) return '';
          return ref.split(/\\s+/).map((i) => {
            const t = document.getElementById(i);
            return t ? (t.textContent || '').trim() : '';
          }).join(' ').trim();
        })();
      return !name;
    })
    .slice(0, 5)
    .map((el) => el.tagName.toLowerCase()
      + (el.className && typeof el.className === 'string'
        ? '.' + el.className.trim().split(/\\s+/)[0] : ''));

  // Contrast, sampled on real rendered text against its effective background.
  const lum = (rgb) => {
    const [r, g, b] = rgb.map((v) => {
      const c = v / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const parse = (s) => {
    const m = (s || '').match(/rgba?\\(([^)]+)\\)/);
    if (!m) return null;
    const p = m[1].split(',').map((n) => parseFloat(n));
    return { rgb: p.slice(0, 3), a: p.length > 3 ? p[3] : 1 };
  };
  // Resolves what is actually painted behind a piece of text.
  //
  // Walking up the DOM is the obvious approach and it is wrong here. A hero
  // puts its photo on an absolutely positioned layer that is a *sibling* of the
  // text, not an ancestor, so an ancestor walk finds no background at all,
  // falls through to the body, and reports white-on-white — a perfect 1:1
  // failure on text that is perfectly readable. The first two runs of this
  // audit did exactly that on eighteen headings, which is worse than useless:
  // eighteen false alarms is how a report gets ignored.
  //
  // elementsFromPoint returns the painted stack at a coordinate, front to back,
  // regardless of DOM ancestry, so it sees that sibling layer. Where the stack
  // contains an image or a gradient, contrast genuinely cannot be computed from
  // style — it depends on pixels that vary across the element — and the honest
  // answer is "a human has to look at this", not a number.
  const bgOf = (el) => {
    const r = el.getBoundingClientRect();
    const x = Math.min(Math.max(r.left + r.width / 2, 1), window.innerWidth - 1);
    const y = Math.min(Math.max(r.top + r.height / 2, 1), window.innerHeight - 1);
    const stack = document.elementsFromPoint(x, y);
    const start = stack.indexOf(el);
    const behind = start === -1 ? stack : stack.slice(start);

    const painted = new Set(['IMG', 'VIDEO', 'CANVAS', 'SVG', 'PICTURE']);

    for (const n of behind) {
      // A hero photo is as often an <img> stretched behind the text as it is a
      // CSS background, and it contributes no background-color either way.
      if (painted.has(n.tagName)) return { image: true };
      const cs = getComputedStyle(n);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') return { image: true };
      const c = parse(cs.backgroundColor);
      if (c && c.a > 0.95) return { rgb: c.rgb };
    }
    const c = parse(getComputedStyle(document.body).backgroundColor);
    return { rgb: c && c.a > 0.95 ? c.rgb : [255, 255, 255] };
  };

  const seen = new Set();
  out.contrast = [];
  out.contrastOverImages = 0;
  const textEls = [...document.querySelectorAll('p, a, li, h1, h2, h3, h4, span, button, td, th, label')]
    .filter((el) => {
      const t = (el.textContent || '').trim();
      if (!t || t.length < 3) return false;
      if ([...el.children].some((c) => (c.textContent || '').trim() === t)) return false;
      const r = el.getBoundingClientRect();
      // elementsFromPoint can only see the viewport, so the sample is limited
      // to what is on screen. The report says so rather than implying the whole
      // page was checked.
      return r.width > 0 && r.height > 0
        && r.top >= 0 && r.bottom <= window.innerHeight
        && r.left >= 0 && r.right <= window.innerWidth;
    });

  for (const el of textEls) {
    const cs = getComputedStyle(el);
    const fg = parse(cs.color);
    if (!fg || fg.a < 0.95) continue;
    const bgResult = bgOf(el);
    if (bgResult.image) { out.contrastOverImages++; continue; }
    const bg = bgResult.rgb;
    const size = parseFloat(cs.fontSize);
    const bold = parseInt(cs.fontWeight, 10) >= 700;
    const large = size >= 24 || (size >= 18.66 && bold);
    const L1 = lum(fg.rgb), L2 = lum(bg);
    const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
    const required = large ? 3 : 4.5;
    if (ratio + 0.02 < required) {
      const key = cs.color + '|' + bg.join(',') + '|' + Math.round(size);
      if (seen.has(key)) continue;
      seen.add(key);
      out.contrast.push({
        ratio: Math.round(ratio * 100) / 100,
        required,
        color: cs.color,
        background: 'rgb(' + bg.join(', ') + ')',
        fontSize: Math.round(size * 10) / 10,
        sample: (el.textContent || '').trim().slice(0, 40),
        selector: el.tagName.toLowerCase()
          + (typeof el.className === 'string' && el.className
            ? '.' + el.className.trim().split(/\\s+/)[0] : ''),
      });
      if (out.contrast.length >= 6) break;
    }
  }

  return out;
})()`;

// Reads what currently has focus and whether that focus is actually visible.
const FOCUS_PROBE = `(() => {
  const el = document.activeElement;
  if (!el || el === document.body) return null;
  const cs = getComputedStyle(el);
  const r = el.getBoundingClientRect();
  const outlineVisible = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0;
  const ringVisible = cs.boxShadow !== 'none' && cs.boxShadow !== '';
  return {
    tag: el.tagName.toLowerCase(),
    name: (el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 34),
    top: Math.round(r.top), left: Math.round(r.left),
    width: Math.round(r.width), height: Math.round(r.height),
    offscreen: r.width === 0 && r.height === 0,
    visible: outlineVisible || ringVisible,
    outline: cs.outline,
    boxShadow: cs.boxShadow === 'none' ? '' : cs.boxShadow.slice(0, 46),
  };
})()`;

// ------------------------------------------------------------------- the run

async function main() {
  const profile = await mkdtemp(join(tmpdir(), 'spu-a11y-'));
  const chrome = spawn(CHROME, [
    '--headless=new',
    '--remote-debugging-port=9333',
    `--user-data-dir=${profile}`,
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-extensions',
    '--hide-scrollbars',
  ], { stdio: 'ignore' });

  let ws;
  try {
    let targetWs = null;
    for (let i = 0; i < 40 && !targetWs; i++) {
      await sleep(250);
      try {
        const r = await fetch('http://127.0.0.1:9333/json/version');
        targetWs = (await r.json()).webSocketDebuggerUrl;
      } catch { /* not up yet */ }
    }
    if (!targetWs) throw new Error('Chrome did not expose a debugging endpoint');

    ws = new WebSocket(targetWs);
    await new Promise((res, rej) => { ws.onopen = res; ws.onerror = rej; });

    ws.onmessage = (ev) => {
      const msg = JSON.parse(ev.data);
      if (msg.id && pending.has(msg.id)) {
        const { resolve, reject } = pending.get(msg.id);
        pending.delete(msg.id);
        msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
        return;
      }
      for (const fn of listeners.get(msg.method) ?? []) fn(msg.params, msg.sessionId);
    };

    const { targetId } = await send(ws, 'Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send(ws, 'Target.attachToTarget', { targetId, flatten: true });
    const S = (m, p) => send(ws, m, p, sessionId);

    await S('Page.enable');
    await S('Runtime.enable');
    await S('Network.enable');
    await S('Log.enable');

    let consoleErrors = [];
    let failedRequests = [];
    on('Runtime.exceptionThrown', (p) => {
      const d = p.exceptionDetails;
      consoleErrors.push((d.exception?.description ?? d.text ?? 'exception').split('\n')[0].slice(0, 150));
    });
    on('Runtime.consoleAPICalled', (p) => {
      if (p.type === 'error') {
        consoleErrors.push(p.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 150));
      }
    });
    on('Network.responseReceived', (p) => {
      if (p.response.status >= 400) {
        failedRequests.push(`${p.response.status} ${p.response.url.replace(BASE, '')}`);
      }
    });
    on('Network.loadingFailed', (p) => {
      if (p.type !== 'Document') failedRequests.push(`failed ${p.errorText}`);
    });

    console.log(`\nAccessibility audit — ${BASE}`);
    console.log(`${ROUTES.length} routes × ${VIEWPORTS.length} viewports, headless Chrome over CDP\n`);

    for (const vp of VIEWPORTS) {
      await S('Emulation.setDeviceMetricsOverride', {
        width: vp.width, height: vp.height, deviceScaleFactor: 1,
        mobile: vp.name === 'mobile',
      });

      for (const route of ROUTES) {
        consoleErrors = [];
        failedRequests = [];

        await S('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'no-preference' }] });
        await S('Page.navigate', { url: BASE + route });
        await sleep(2600);

        const { result } = await S('Runtime.evaluate', {
          expression: PAGE_PROBES, returnByValue: true, awaitPromise: false,
        });
        const r = result.value;

        if (r.overflow.overflows) {
          report(route, vp.name, 'horizontal overflow',
            `document scrolls sideways: ${r.overflow.scrollWidth}px in a ${r.overflow.clientWidth}px viewport`
            + (r.overflow.culprits?.length ? ` — widest: ${r.overflow.culprits.join('; ')}` : ''));
        }
        if (!r.hasMain) report(route, vp.name, 'landmark', 'no <main> element');
        if (r.h1Count !== 1) report(route, vp.name, 'heading order', `${r.h1Count} <h1> elements, expected exactly 1`);
        if (r.skipLink && !r.skipLink.targetExists) {
          report(route, vp.name, 'skip link', `points at ${r.skipLink.href}, which does not exist`);
        }
        if (r.imagesWithoutAlt.length) {
          report(route, vp.name, 'image alt', `${r.imagesWithoutAlt.length} image(s) with no alt attribute: ${r.imagesWithoutAlt.join(', ')}`);
        }
        if (r.namelessControls.length) {
          report(route, vp.name, 'accessible name', `control(s) with no name: ${r.namelessControls.join(', ')}`);
        }
        for (const c of r.contrast) {
          report(route, vp.name, 'contrast',
            `${c.ratio}:1 (needs ${c.required}:1) — ${c.color} on ${c.background} at ${c.fontSize}px — ${c.selector} "${c.sample}"`);
        }
        if (r.contrastOverImages > 0 && vp.name === 'desktop') {
          overImages += r.contrastOverImages;
        }
        if (consoleErrors.length) {
          report(route, vp.name, 'console error', [...new Set(consoleErrors)].slice(0, 3).join(' | '));
        }
        if (failedRequests.length) {
          report(route, vp.name, 'failed request', [...new Set(failedRequests)].slice(0, 5).join(', '));
        }

        let tabStops = 0;

        // Keyboard traversal, on the desktop pass only: tab order is a property
        // of the page, and running it twice reports every finding twice.
        if (vp.name === 'desktop') {
          await S('Runtime.evaluate', { expression: 'document.body.focus(); document.activeElement.blur();' });
          const seq = [];
          for (let i = 0; i < 14; i++) {
            await S('Input.dispatchKeyEvent', { type: 'rawKeyDown', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9, key: 'Tab', code: 'Tab' });
            await S('Input.dispatchKeyEvent', { type: 'keyUp', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9, key: 'Tab', code: 'Tab' });
            const { result: f } = await S('Runtime.evaluate', { expression: FOCUS_PROBE, returnByValue: true });
            if (f.value) seq.push(f.value);
          }

          // A traversal that finds nothing would report no problems, which is
          // indistinguishable from a page where everything is correct. Say so.
          if (!seq.length) {
            report(route, vp.name, 'keyboard traversal',
              '14 Tab presses moved focus to nothing — either the page has no focusable '
              + 'content or the traversal is not working, and both need a human to look');
          }

          tabStops = seq.length;

          const invisible = seq.filter((s) => !s.visible && !s.offscreen);
          if (invisible.length) {
            report(route, vp.name, 'focus not visible',
              `${invisible.length} of ${seq.length} tab stops show no outline or ring: `
              + invisible.slice(0, 3).map((s) => `${s.tag}"${s.name}"`).join(', '));
          }
          if (seq.length && seq[0].top > 400) {
            report(route, vp.name, 'tab order',
              `first tab stop is ${seq[0].top}px down the page (${seq[0].tag} "${seq[0].name}") — expected a skip link or the first header control`);
          }
        }

        // prefers-reduced-motion must actually stop animation.
        await S('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
        await sleep(700);
        const { result: motion } = await S('Runtime.evaluate', {
          returnByValue: true,
          expression: `(() => {
            const moving = [...document.querySelectorAll('body *')].filter((el) => {
              const cs = getComputedStyle(el);
              const dur = parseFloat(cs.animationDuration) || 0;
              const name = cs.animationName;
              const r = el.getBoundingClientRect();
              return dur > 0.15 && name && name !== 'none' && r.width > 0 && r.height > 0;
            });
            return moving.slice(0, 3).map((el) => el.tagName.toLowerCase()
              + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\\s+/)[0] : '')
              + ' (' + getComputedStyle(el).animationName + ')');
          })()`,
        });
        if (motion.value?.length) {
          report(route, vp.name, 'reduced motion',
            `animation still running under prefers-reduced-motion: ${motion.value.join(', ')}`);
        }

        const note = vp.name === 'desktop' ? ` ${tabStops} tab stops` : '';
        process.stdout.write(`  ${vp.name.padEnd(8)} ${route.padEnd(28)}${note.padEnd(15)} ${findings.length ? findings.length + ' finding(s) so far' : 'clean'}\n`);
      }
    }

    await S('Target.closeTarget', { targetId }).catch(() => {});
  } finally {
    try { ws?.close(); } catch {}
    chrome.kill();
    await rm(profile, { recursive: true, force: true }).catch(() => {});
  }

  // ------------------------------------------------------------------ report

  console.log('\n' + '─'.repeat(78));
  if (!findings.length) {
    console.log('No findings.');
  } else {
    const byCheck = new Map();
    for (const f of findings) {
      if (!byCheck.has(f.check)) byCheck.set(f.check, []);
      byCheck.get(f.check).push(f);
    }
    console.log(`${findings.length} finding(s) across ${byCheck.size} check(s)\n`);
    for (const [check, list] of [...byCheck].sort((a, b) => b[1].length - a[1].length)) {
      console.log(`${check.toUpperCase()}  (${list.length})`);
      const seen = new Set();
      for (const f of list) {
        if (seen.has(f.detail)) continue;
        seen.add(f.detail);
        const routes = list.filter((x) => x.detail === f.detail).map((x) => `${x.route} ${x.viewport}`);
        console.log(`  ${f.detail}`);
        console.log(`    on: ${routes.slice(0, 6).join(', ')}${routes.length > 6 ? ` +${routes.length - 6} more` : ''}`);
      }
      console.log('');
    }
  }

  console.log('─'.repeat(78));
  if (overImages) {
    console.log(`${overImages} text element(s) sit on a background image and their contrast`);
    console.log('could not be computed. That is a gap, not a pass — it needs a human eye.\n');
  }
  console.log('Scope: rendered layout, keyboard traversal, focus visibility, computed');
  console.log('contrast of text within the first viewport, reduced motion, console and');
  console.log('network. Headless Chrome, no assistive technology — this is NOT');
  console.log('screen-reader QA and NOT accessibility sign-off.');
  console.log(`Not exercised: every route outside the ${ROUTES.length} listed, admin, forms under`);
  console.log('submission, and any announcement behaviour.');

  process.exit(findings.length ? 1 : 0);
}

main().catch((err) => { console.error(err); process.exit(2); });
