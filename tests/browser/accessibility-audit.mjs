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
const ROUTES = (process.env.AUDIT_ROUTES ? process.env.AUDIT_ROUTES.split(',') : [
  '/ar', '/en',
  '/ar/about', '/en/about',
  '/ar/admissions', '/en/admissions',
  '/ar/news', '/en/news',
  '/ar/facilities', '/en/facilities',
  '/ar/campus-life', '/en/campus-life',
  '/en/research/publications',
]);

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];

// How far down each route the contrast sweep goes, in screenfuls. Ten covers
// every template here; the cap exists so one unusually long route cannot
// dominate the run, and how far it actually reached is stated in the report.
const SCROLL_STEPS = Number(process.env.AUDIT_SCROLL_STEPS ?? 20);

const CHROME = process.env.CHROME_BIN
  ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

const findings = [];
let overImages = 0;
let pixelMeasured = 0;
let unmeasuredOverImages = 0;
const screensCovered = [];
const pixelControls = [];
const pixelUntrusted = new Set();
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

  // elementsFromPoint only sees the viewport, so one call can only judge what is
  // currently on screen. The driver therefore calls this probe once per
  // screenful and these accumulators carry the results across the sweep.
  //
  // Before the sweep existed the audit measured the first screen and nothing
  // else. That is how it passed a page whose stat strip, 2,152px down, was
  // rendering an invisible "+" at 1.09:1 — the glyph was never collected, so
  // there was nothing to report. "No findings" meant "no findings above the
  // fold", which is not what a reader takes from it.
  if (!window.__spuAccum) {
    window.__spuAccum = {
      contrast: [], overImages: 0, truncated: false,
      seenKeys: new Set(), seenEls: new WeakSet(),
    };
    window.__spuOverImage = [];
    window.__spuControl = null;
  }
  const accum = window.__spuAccum;
  const seen = accum.seenKeys;

  // Text nobody can see has no contrast requirement, and reporting it is how a
  // report gets ignored. Two kinds turned up once the sweep started reaching
  // the whole page: a card caption held at opacity:0 until its parent is
  // hovered, reported as white-on-white at 1:1, and .sr-only text clipped to a
  // 1x1 box, reported as black on brand red. Both are correct markup.
  //
  // Opacity is walked up the tree because it is inherited in effect, not in
  // cascade: a child at opacity:1 inside a parent at 0 paints nothing.
  const effectivelyInvisible = (el) => {
    for (let n = el; n && n !== document.documentElement; n = n.parentElement) {
      const cs = getComputedStyle(n);
      if (cs.visibility === 'hidden' || cs.visibility === 'collapse') return true;
      if (parseFloat(cs.opacity) === 0) return true;
    }
    return false;
  };

  const textEls = [...document.querySelectorAll('p, a, li, h1, h2, h3, h4, span, button, td, th, label')]
    .filter((el) => {
      if (accum.seenEls.has(el)) return false;
      const t = (el.textContent || '').trim();
      // Any visible character counts. The threshold used to be three, which
      // reads as "skip the noise" and actually skipped the "+" in "5k+" — a
      // glyph that carries meaning, rendered at 36.8px, and invisible at
      // 1.09:1. A single character is text; whether it is worth reporting is
      // the contrast check's decision, not the collector's.
      if (!t) return false;
      if ([...el.children].some((c) => (c.textContent || '').trim() === t)) return false;
      const r = el.getBoundingClientRect();
      // Intersecting the viewport, not contained by it. Requiring containment
      // skipped any element taller than the screen at every scroll position, so
      // scrolling alone would never have reached a long paragraph at mobile
      // width. bgOf clamps its sample point to the visible area, so an element
      // that runs off the top or bottom is still sampled somewhere real.
      // 2px, not 0: .sr-only clips itself to a 1x1 box, which is a rendered
      // size no reader ever sees.
      return r.width >= 2 && r.height >= 2
        && r.bottom > 0 && r.top < window.innerHeight
        && r.right > 0 && r.left < window.innerWidth
        && !effectivelyInvisible(el);
    });

  for (const el of textEls) {
    accum.seenEls.add(el);
    const cs = getComputedStyle(el);
    const fg = parse(cs.color);
    if (!fg || fg.a < 0.95) continue;
    // Size class first: the over-image branch below needs it, and a const
    // declared after that branch would be in its temporal dead zone there.
    const size = parseFloat(cs.fontSize);
    const bold = parseInt(cs.fontWeight, 10) >= 700;
    const large = size >= 24 || (size >= 18.66 && bold);

    const bgResult = bgOf(el);
    if (bgResult.image) {
      accum.overImages++;
      // Kept addressable so the pixel pass below can measure what computed
      // style cannot — but only leaf elements, for the same reason the control
      // must be one: photographing a box that contains other painted children
      // does not tell you what is behind the text. Non-leaf elements stay
      // counted and stay unmeasured rather than being measured wrongly.
      if (el.children.length === 0) {
        window.__spuOverImage.push({ el, color: cs.color, required: large ? 3 : 4.5 });
      }
      continue;
    }
    const bg = bgResult.rgb;
    const L1 = lum(fg.rgb), L2 = lum(bg);
    const ratio = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);
    const required = large ? 3 : 4.5;

    // The first LEAF element resolved against a solid background becomes the
    // control for the pixel pass. Two independent methods must agree on it
    // before any number that path produces is reportable.
    //
    // Leaf matters. The pixel method photographs an element's box and treats it
    // as the background its text sits on, which only holds when the box contains
    // nothing but that text. A <label> wrapping a <select> broke exactly this:
    // computed style said 21:1 for its black-on-white text while the photograph
    // was mostly the select's own control surface.
    //
    // Wholly within the viewport, unlike the text elements above. Those only
    // have to intersect it, because a clamped sample point is enough to read a
    // background — but the control is PHOTOGRAPHED, with a clip taken in
    // viewport coordinates, so any part of it off screen is a part the camera
    // does not get. Relaxing this to "intersects" let a skip link parked above
    // the fold become the control on some runs and not others, which is how a
    // page's entire pixel pass came to depend on load timing.
    if (!window.__spuControl && el.children.length === 0) {
      const cr = el.getBoundingClientRect();
      if (cr.height > 8
        && cr.top >= 0 && cr.bottom <= window.innerHeight
        && cr.left >= 0 && cr.right <= window.innerWidth) {
        window.__spuControl = { el, color: cs.color, expected: ratio };
      }
    }
    if (ratio + 0.02 < required) {
      const key = cs.color + '|' + bg.join(',') + '|' + Math.round(size);
      if (seen.has(key)) continue;
      seen.add(key);
      accum.contrast.push({
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
      // The cap was six when this probe saw a single screenful. It now runs
      // across the whole page, and six distinct colour/background/size
      // combinations is easily reached on a content-heavy route — at which
      // point the loop used to stop, abandoning the rest of that screen's
      // over-image collection with it, and say nothing. A report that quietly
      // stops looking is the defect this whole change exists to remove, so the
      // limit is higher and, when it is reached, said out loud.
      if (accum.contrast.length >= 25) { accum.truncated = true; break; }
    }
  }

  out.contrast = accum.contrast;
  out.contrastOverImages = accum.overImages;
  out.contrastTruncated = accum.truncated;
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

// ------------------------------------------------- contrast over a background

// Measures the contrast of text that sits on an image, which computed style
// cannot answer.
//
// The method: hide the text, photograph exactly the rectangle it occupied, and
// compare its known colour against the pixels that were behind it. Worst case,
// not average — a hero where white text crosses one bright patch of sky is
// precisely the failure this exists to catch, and an average hides it.
//
// GATED ON A CONTROL. Two earlier versions of the contrast check in this file
// produced confident, wrong numbers, so this one is not trusted on its own
// authority: it first measures an element whose contrast the computed-style path
// already resolved, and reports nothing at all unless the two agree. A method
// that cannot reproduce a known answer has not earned the right to report an
// unknown one.
async function measureOnPixels(S, sleep) {
  // Brings the element on screen before its rect is taken. The sweep collects
  // text from the whole document, so most of what reaches here is nowhere near
  // the viewport, and a clip at a negative or off-screen y photographs the
  // wrong pixels — or nothing. A fixed-position element is already where it
  // will be photographed and is left alone.
  const bringIntoView = async (path) => {
    const { result } = await S('Runtime.evaluate', {
      returnByValue: true,
      expression: `(() => {
        const t = ${path};
        if (!t) return false;
        if (getComputedStyle(t.el).position === 'fixed') return false;
        const r = t.el.getBoundingClientRect();
        if (r.top >= 0 && r.bottom <= window.innerHeight) return false;
        // 'instant' matters. The site sets scroll-behavior: smooth, so a
        // default scrollIntoView animates — the rect is then read mid-flight,
        // and worse, the page keeps moving between the two captures the pixel
        // method differences, which turns the whole frame into apparent glyph
        // pixels. That is what made the synthetic control report 6.26:1 for a
        // swatch whose value is 11.72:1 by construction.
        t.el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
        return true;
      })()`,
    });
    // Only pay the settle delay when something actually moved, and confirm the
    // page has come to rest before anything is photographed.
    if (result.value) {
      await sleep(250);
      let last = -1;
      for (let i = 0; i < 8; i++) {
        const { result: y } = await S('Runtime.evaluate',
          { returnByValue: true, expression: 'window.scrollY' });
        if (y.value === last) break;
        last = y.value;
        await sleep(100);
      }
    }
  };

  const rectOf = async (path) => {
    await bringIntoView(path);
    const { result } = await S('Runtime.evaluate', {
      returnByValue: true,
      expression: `(() => {
        const t = ${path};
        if (!t) return null;
        const r = t.el.getBoundingClientRect();
        if (r.width < 4 || r.height < 4) return null;
        // Still off screen after the scroll — a sticky header covering it, or a
        // scroll container the page will not move. Refusing is right: the clip
        // would photograph something else and report a number for it.
        if (r.bottom <= 0 || r.top >= window.innerHeight) return null;
        // Viewport-relative, deliberately. captureScreenshot's clip is taken in
        // viewport coordinates when captureBeyondViewport is false, so adding
        // the scroll offset double-counts it — and the keyboard traversal that
        // runs before this pass leaves the page scrolled. That is why the
        // control agreed exactly on pages the traversal happened not to scroll
        // and disagreed wildly on the ones it did.
        return { x: r.left, y: r.top,
                 width: Math.min(r.width, 900), height: Math.min(r.height, 200),
                 color: t.color, required: t.required ?? null, expected: t.expected ?? null,
                 label: (t.el.textContent || '').trim().slice(0, 40),
                 tag: t.el.tagName.toLowerCase() };
      })()`,
    });
    return result.value;
  };

  // Photographs the element twice — once as rendered, once with its glyphs
  // made transparent — and compares the two to find where the text actually is.
  //
  // Sampling the whole box was wrong, and the page disproved it: a paragraph's
  // box spans its entire column, so the worst pixel in that box was empty space
  // past the end of the last line, over bright sky, where no letter sits. That
  // reported 1.08:1 on text a screenshot showed to be perfectly legible.
  //
  // Differencing the two captures gives the glyph pixels exactly. The hidden
  // capture then supplies the true background at each of those pixels, and the
  // worst of those is the number that matters — worst rather than average,
  // because white text crossing one bright patch is the failure this exists to
  // catch, and an average hides it.
  //
  // `color: transparent` rather than `visibility: hidden`: hiding the element
  // removes its own background along with its text, which photographed a button
  // against the hero behind it. The control caught that.
  const worstRatioBehind = async (path, rect) => {
    const clip = { x: rect.x, y: rect.y, width: rect.width, height: rect.height, scale: 1 };

    const shotVisible = await S('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, clip });

    await S('Runtime.evaluate', {
      expression: `(() => {
        const el = ${path}.el;
        el.dataset.spuPrevColor = el.style.color;
        el.dataset.spuPrevShadow = el.style.textShadow;
        el.style.setProperty('color', 'transparent', 'important');
        el.style.setProperty('text-shadow', 'none', 'important');
      })()`,
    });
    await sleep(120);

    let shotHidden;
    try {
      shotHidden = await S('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false, clip });
    } finally {
      await S('Runtime.evaluate', {
        expression: `(() => {
          const el = ${path}.el;
          el.style.color = el.dataset.spuPrevColor || '';
          el.style.textShadow = el.dataset.spuPrevShadow || '';
          delete el.dataset.spuPrevColor;
          delete el.dataset.spuPrevShadow;
        })()`,
      });
    }

    if (!shotVisible?.data || !shotHidden?.data) return null;

    const { result } = await S('Runtime.evaluate', {
      returnByValue: true, awaitPromise: true,
      expression: `(async () => {
        const load = async (b64) => {
          const img = new Image();
          img.src = 'data:image/png;base64,' + b64;
          await img.decode();
          const c = document.createElement('canvas');
          c.width = img.naturalWidth; c.height = img.naturalHeight;
          const ctx = c.getContext('2d', { willReadFrequently: true });
          ctx.drawImage(img, 0, 0);
          return ctx.getImageData(0, 0, c.width, c.height);
        };

        const A = await load('${shotVisible.data}');
        const B = await load('${shotHidden.data}');
        if (A.width !== B.width || A.height !== B.height) return null;

        const lum = (r, g, b) => {
          const f = (v) => { const x = v / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); };
          return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
        };

        const parts = '${rect.color}'.match(/rgba?\\(([^)]+)\\)/);
        const p = parts ? parts[1].split(',').map(Number) : [0, 0, 0];
        const Lt = lum(p[0], p[1], p[2]);

        // A pixel counts as a glyph only where the two captures differ clearly.
        // The threshold keeps antialiased edges out: a half-covered pixel is
        // neither the text colour nor the background, and judging contrast on it
        // measures the renderer rather than the design.
        const THRESHOLD = 40;
        const ratios = [];

        for (let i = 0; i < A.data.length; i += 4) {
          const d = Math.abs(A.data[i] - B.data[i])
                  + Math.abs(A.data[i + 1] - B.data[i + 1])
                  + Math.abs(A.data[i + 2] - B.data[i + 2]);
          if (d < THRESHOLD) continue;
          const Lb = lum(B.data[i], B.data[i + 1], B.data[i + 2]);
          ratios.push((Math.max(Lt, Lb) + 0.05) / (Math.min(Lt, Lb) + 0.05));
        }

        // Too few differing pixels means the text never rendered where we looked,
        // and a ratio from a handful of pixels is noise.
        if (ratios.length < 40) return null;

        ratios.sort((a, b) => a - b);

        // The single darkest pixel is the wrong statistic over twenty thousand
        // of them: one window reflection under one letter reported 1.3:1 on a
        // heading that reads perfectly well, and a checker that cries wolf on a
        // headline is one nobody runs twice.
        //
        // "Hard to read" means a meaningful share of the letters sit on ground
        // too bright for them, so the judgement is made on the fifth percentile.
        // The single worst pixel is still reported alongside it, because it is
        // the thing to go and look at.
        const p5 = ratios[Math.floor(ratios.length * 0.05)];

        return {
          worst: Math.round(ratios[0] * 100) / 100,
          p5: Math.round(p5 * 100) / 100,
          samples: ratios.length,
        };
      })()`,
    });
    return result.value;
  };

  // Back to where the probe measured everything, since the keyboard traversal
  // scrolled away from it and every rect below is viewport-relative.
  await S('Runtime.evaluate', { expression: 'window.scrollTo(0, 0)' });
  await sleep(250);

  // 1. The control.
  //
  // An organic one is always preferred: a real element, placed by the real
  // layout, whose contrast a completely separate method already resolved. That
  // is independent evidence, and it is what caught all three flaws this method
  // has had — every one of them about WHERE an element sits rather than about
  // arithmetic.
  //
  // Where a page has none, a synthetic control is built instead: an opaque
  // swatch of known colours placed ON THE HERO IMAGE, at the same spot as the
  // text about to be measured. That placement is the whole point. A swatch
  // floated over the white body would confirm only that decoding and luminance
  // maths work, which was never in doubt; over the hero it exercises the clip
  // coordinates, the two-capture diff and the glyph threshold under exactly the
  // conditions the real measurements run in.
  //
  // It is weaker evidence, and the output labels it as such — a reader has to be
  // able to tell a page checked against independent evidence from one checked
  // against our own arithmetic.
  // The synthetic control, built on demand. Kept as a function because it is
  // now needed in two situations, not one.
  const buildSynthetic = async () => {
    const { result: built } = await S('Runtime.evaluate', {
      returnByValue: true,
      expression: `(() => {
        const target = (window.__spuOverImage || [])[0];
        if (!target) return false;
        target.el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
        const r = target.el.getBoundingClientRect();

        const FG = [255, 255, 255];
        const BG = [18, 58, 94];
        const lum = (rgb) => {
          const [r2, g2, b2] = rgb.map((v) => {
            const c = v / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
          });
          return 0.2126 * r2 + 0.7152 * g2 + 0.0722 * b2;
        };
        const L1 = lum(FG), L2 = lum(BG);
        const expected = (Math.max(L1, L2) + 0.05) / (Math.min(L1, L2) + 0.05);

        const el = document.createElement('span');
        el.id = 'spu-synthetic-control';
        el.textContent = 'Control ABCDEFGH 12345678';
        el.style.cssText = [
          'position:fixed',
          'left:' + Math.max(4, Math.round(r.left)) + 'px',
          'top:' + Math.max(4, Math.min(Math.round(r.top), window.innerHeight - 60)) + 'px',
          'z-index:2147483647',
          'background:rgb(18, 58, 94)',
          'color:rgb(255, 255, 255)',
          'font:700 20px/1.3 system-ui, sans-serif',
          'padding:8px 12px',
          'margin:0',
          'border:0',
          'text-shadow:none',
        ].join(';');
        document.body.appendChild(el);

        window.__spuControl = { el, color: 'rgb(255, 255, 255)', expected, synthetic: true };
        return true;
      })()`,
    });
    if (!built.value) return null;
    await sleep(350);
    return rectOf('window.__spuControl');
  };

  const dropSynthetic = async () => {
    await S('Runtime.evaluate', {
      expression: "document.getElementById('spu-synthetic-control')?.remove()",
    });
  };

  // Compared on the percentile, not the single darkest pixel: the control sits
  // on a solid colour, so every glyph shares one background and the two are the
  // same number — except at an antialiased edge, which must not fail an
  // otherwise sound method.
  const DRIFT_TOLERANCE = 0.35;

  const tryControl = async (rect, kind) => {
    if (!rect || rect.expected === null) {
      return { ok: false, why: `no ${kind} control could be photographed` };
    }
    const measured = await worstRatioBehind('window.__spuControl', rect);
    if (!measured) return { ok: false, why: `the ${kind} control could not be photographed` };

    if (process.env.AUDIT_VERBOSE) {
      console.log(`      [control:${kind}] ${rect.tag} "${rect.label}" `
        + `computed=${rect.expected.toFixed(2)} pixels-p5=${measured.p5} worst=${measured.worst}`);
    }
    if (Math.abs(measured.p5 - rect.expected) > DRIFT_TOLERANCE) {
      return {
        ok: false,
        why: `${kind} control disagreed: computed says ${rect.expected.toFixed(2)}:1, `
          + `pixels say ${measured.p5}:1 on ${rect.tag} "${rect.label}"`,
      };
    }
    return { ok: true, measured, rect, kind };
  };

  // Organic first, and a disagreement is no longer the end of it.
  //
  // It used to be: the synthetic control was built only when the page offered
  // no organic candidate at all, so a page with a candidate that disagreed by
  // 3.4% had every one of its pixel measurements discarded. That is what
  // happened on the faculties hub, and it is why the audit reported "no
  // findings" on a page rendering an invisible glyph.
  //
  // A disagreeing organic control is exactly when the synthetic one earns its
  // keep. Its expected value is known by construction rather than derived from
  // a computed-style walk that may itself be what is wrong, so it can say
  // whether the METHOD is unsound or merely that one candidate was a poor
  // choice. Only if it also disagrees is the page genuinely unmeasurable — and
  // the report then carries both numbers, because which one failed matters.
  let attempt = await tryControl(await rectOf('window.__spuControl'), 'organic');
  let organicFailure = null;

  if (!attempt.ok) {
    organicFailure = attempt.why;
    await dropSynthetic();
    attempt = await tryControl(await buildSynthetic(), 'synthetic');

    if (!attempt.ok) {
      await dropSynthetic();
      return {
        trusted: false,
        reason: organicFailure.startsWith('no organic')
          ? attempt.why
          : `${organicFailure}; and the ${attempt.why}`,
      };
    }
  }

  const controlRect = attempt.rect;
  const controlMeasured = attempt.measured;
  const controlKind = organicFailure ? `${attempt.kind} (organic disagreed)` : attempt.kind;

  // The swatch must not sit over anything it is about to measure.
  await dropSynthetic();

  // 2. Only now, the elements computed style could not answer for.
  //
  // Sampled across several rounds, because hero backgrounds move. The homepage
  // rotates its photograph every five seconds, so a single reading is a sample
  // of a moving target — the same heading measured 1.92, 2.17, 2.39 and 2.44 on
  // four consecutive runs, and reporting any one of those as "the" contrast
  // would be reporting which slide happened to be up.
  //
  // The requirement has to hold on every slide, so the judgement is the worst
  // round. The best is reported alongside it whenever they differ, because a
  // wide spread is itself the finding: it says the problem is one photograph
  // rather than the design.
  const { result: count } = await S('Runtime.evaluate', {
    returnByValue: true, expression: 'window.__spuOverImage.length',
  });

  const ROUNDS = 3;
  const ROUND_GAP_MS = 2500;
  const accumulated = new Map();

  for (let round = 0; round < ROUNDS; round++) {
    if (round > 0) await sleep(ROUND_GAP_MS);

    for (let i = 0; i < count.value; i++) {
      const path = `window.__spuOverImage[${i}]`;
      const rect = await rectOf(path);
      if (!rect) continue;
      const r = await worstRatioBehind(path, rect);
      if (!r) continue;

      const seen = accumulated.get(i);
      if (!seen) {
        accumulated.set(i, { ...rect, low: r.p5, high: r.p5, worstPixel: r.worst, samples: r.samples, rounds: 1 });
      } else {
        seen.low = Math.min(seen.low, r.p5);
        seen.high = Math.max(seen.high, r.p5);
        seen.worstPixel = Math.min(seen.worstPixel, r.worst);
        seen.samples = Math.max(seen.samples, r.samples);
        seen.rounds++;
      }
    }
  }

  const measured = [...accumulated.values()].map((m) => ({
    ...m,
    p5: m.low,
    varies: Math.round((m.high - m.low) * 100) / 100,
    worst: m.worstPixel,
  }));

  return {
    trusted: true,
    control: { expected: controlRect.expected, measured: controlMeasured.p5, kind: controlKind },
    measured,
  };
}

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

        // Swept down the page a screenful at a time. Everything the probe
        // reports except contrast is scroll-invariant, so the last run's values
        // are the page's values; contrast accumulates inside the page across
        // the sweep, because elementsFromPoint cannot see past the viewport.
        //
        // Bounded at SCROLL_STEPS screens so one very long route cannot dominate
        // the run, and the report says how far it reached rather than implying
        // the whole document was covered.
        // A hero photograph that has not painted yet is not a background image
        // as far as elementsFromPoint is concerned, so the text over it gets
        // classified as sitting on a solid colour and never reaches the pixel
        // pass. That made the whole classification depend on load timing: the
        // same route reported two elements over an image on one run and none on
        // the next. Waiting for the images to decode removes the race.
        await S('Runtime.evaluate', {
          awaitPromise: true, returnByValue: true,
          expression: `(async () => {
            const deadline = Date.now() + 8000;
            const wait = () => new Promise((r) => setTimeout(r, 100));
            // Stylesheets first. An unstyled document has no hero photograph to
            // sit on and its skip link is an ordinary visible link at the top of
            // the page — which is exactly the wrong control. readyState only
            // reaches 'complete' once CSS and images have loaded.
            while (document.readyState !== 'complete' && Date.now() < deadline) await wait();
            try {
              await Promise.race([document.fonts.ready, new Promise((r) => setTimeout(r, 2000))]);
            } catch {}
            const pending = () => [...document.images].filter((i) => !i.complete && i.loading !== 'lazy');
            while (pending().length && Date.now() < deadline) await wait();
            return { readyState: document.readyState, images: document.images.length, pending: pending().length };
          })()`,
        });
        await S('Runtime.evaluate', { expression: 'delete window.__spuAccum; window.scrollTo(0, 0)' });
        await sleep(250);

        let probe;
        let screensSwept = 0;
        for (let step = 0; step < SCROLL_STEPS; step++) {
          probe = await S('Runtime.evaluate', {
            expression: PAGE_PROBES, returnByValue: true, awaitPromise: false,
          });
          if (probe.exceptionDetails || !probe.result?.value) break;
          screensSwept++;

          // `behavior: 'instant'` on purpose. The site sets scroll-behavior:
          // smooth, so a plain scrollBy animates and window.scrollY still holds
          // its old value on the next line — the first version of this sweep
          // read that, concluded the page would not scroll, and checked exactly
          // one screen of every route while reporting that it had swept.
          //
          // Whether to continue is decided on movement alone, not on reaching
          // the bottom: the last screenful must be probed by the next iteration
          // before the loop ends, or the foot of every page goes unchecked.
          const scrollY = async () => (await S('Runtime.evaluate',
            { returnByValue: true, expression: 'window.scrollY' })).result.value;

          const before = await scrollY();
          await S('Runtime.evaluate', {
            expression: `window.scrollTo({ top: Math.round(window.innerHeight * 0.9) * ${step + 1}, left: 0, behavior: 'instant' })`,
          });
          // Lazy images and reveal animations need a beat before the next
          // screenful is what a reader would see.
          await sleep(500);
          if (await scrollY() <= before + 4) break;
        }
        await S('Runtime.evaluate', { expression: 'window.scrollTo(0, 0)' });
        await sleep(250);

        if (probe.exceptionDetails || !probe.result?.value) {
          const why = probe.exceptionDetails?.exception?.description
            ?? probe.exceptionDetails?.text ?? 'returned nothing';
          report(route, vp.name, 'audit failure', `the in-page probe did not run: ${why.split('\n')[0]}`);
          process.stdout.write(`  ${vp.name.padEnd(8)} ${route.padEnd(28)} PROBE FAILED\n`);
          continue;
        }
        const r = probe.result.value;
        screensCovered.push(screensSwept);
        if (r.contrastTruncated) {
          report(route, vp.name, 'audit coverage',
            `contrast collection stopped after ${r.contrast.length} distinct findings on this route — `
            + 'there may be more, and this run does not claim otherwise');
        }

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
        // Text on an image: computed style cannot answer, so photograph it.
        // Desktop only — the same elements reflow rather than change colour, and
        // each measurement costs a screenshot.
        if (r.contrastOverImages > 0 && vp.name === 'desktop') {
          overImages += r.contrastOverImages;
          const pixels = await measureOnPixels(S, sleep);

          if (!pixels.trusted) {
            pixelUntrusted.add(pixels.reason);
            unmeasuredOverImages += r.contrastOverImages;
          } else {
            pixelControls.push(pixels.control);
            for (const m of pixels.measured) {
              pixelMeasured++;
              if (process.env.AUDIT_VERBOSE) {
                console.log(`      [measured] ${m.tag} "${m.label}" p5=${m.p5} worst=${m.worst} `
                  + `required=${m.required} px=${m.samples} color=${m.color}`);
              }
              if (m.p5 + 0.02 < m.required) {
                const spread = m.varies >= 0.15
                  ? `, varying to ${m.high}:1 as the hero image rotates`
                  : '';
                report(route, vp.name, 'contrast over image',
                  `${m.p5}:1 across the worst twentieth of its letters (needs ${m.required}:1`
                  + `${spread}) — ${m.color} behind ${m.tag} "${m.label}", `
                  + `${m.samples} glyph pixels over ${m.rounds} rounds`);
              }
            }
          }
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
    // "No findings" is a claim about what was checked, and it is false when a
    // page's pixel measurements were all discarded. The faculties hub printed
    // exactly that line while rendering a glyph at 1.09:1.
    if (overImages && !pixelMeasured) {
      console.log('No findings — but nothing that sits on an image could be measured,');
      console.log('so this is not a pass. See below.');
    } else if (unmeasuredOverImages) {
      console.log(`No findings in what could be measured. ${unmeasuredOverImages} element(s) could not be,`);
      console.log('and are listed below rather than counted as passing.');
    } else {
      console.log('No findings.');
    }
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
    console.log(`${overImages} text element(s) sit on a background image, where computed style`);
    console.log('cannot resolve contrast.');

    if (pixelMeasured) {
      const worstDrift = pixelControls.reduce((acc, c) => Math.max(acc, Math.abs(c.measured - c.expected)), 0);
      console.log(`  ${pixelMeasured} of them were measured from pixels instead: the element was`);
      console.log('  photographed as rendered and again with its glyphs made transparent, the two');
      console.log('  differenced to find where the letters actually are, and the text colour');
      console.log('  compared against the worst background pixel under a letter — not the worst');
      console.log('  pixel in its box, which is empty space past the end of the last line.');
      const organic = pixelControls.filter((c) => c.kind === 'organic').length;
      const fallback = pixelControls.filter((c) => c.kind.includes('organic disagreed')).length;
      const synthetic = pixelControls.length - organic - fallback;
      console.log(`  Method verified on each page against a control it must reproduce; they agreed`);
      console.log(`  to within ${worstDrift.toFixed(2)} of a ratio point.`);
      console.log(`    ${organic} page(s) gated by a real element whose contrast a separate method had`);
      console.log('      already resolved — independent evidence.');
      if (synthetic) {
        console.log(`    ${synthetic} page(s) had no such element, so a known swatch was placed on the hero`);
        console.log('      image and measured there. Weaker: it proves the pixel path works under');
        console.log('      the same conditions, not that a second method agrees.');
      }
      if (fallback) {
        console.log(`    ${fallback} page(s) had a real element that disagreed, so the swatch gated them`);
        console.log('      instead. The swatch reproducing its known value says the method is sound');
        console.log('      and that candidate was not — weaker than agreement, stronger than a');
        console.log('      discarded page, which is what these used to be.');
      }
    }
    if (unmeasuredOverImages) {
      console.log(`  ${unmeasuredOverImages} could NOT be measured, and remain a gap needing a human eye:`);
      for (const reason of pixelUntrusted) console.log(`    - ${reason}`);
    }
    console.log('');
  }
  const sweptLow = screensCovered.length ? Math.min(...screensCovered) : 0;
  const sweptHigh = screensCovered.length ? Math.max(...screensCovered) : 0;
  const sweptCapped = screensCovered.filter((n) => n >= SCROLL_STEPS).length;
  console.log('Scope: rendered layout, keyboard traversal, focus visibility, computed');
  console.log('contrast of text — including text on images, measured from pixels —');
  console.log('reduced motion, console and network. Headless Chrome, no assistive');
  console.log('technology — this is NOT screen-reader QA and NOT accessibility sign-off.');
  console.log(`Contrast was swept down each route a screenful at a time, ${sweptLow === sweptHigh ? `${sweptLow} screen(s)` : `${sweptLow}–${sweptHigh} screens`} per pass`);
  console.log(`(cap ${SCROLL_STEPS}${sweptCapped ? `, reached on ${sweptCapped} pass(es) — those routes continue past what was checked` : ''}).`);
  console.log(`Not exercised: every route outside the ${ROUTES.length} listed, admin, forms under`);
  console.log('submission, and any announcement behaviour.');

  process.exit(findings.length ? 1 : 0);
}

main().catch((err) => { console.error(err); process.exit(2); });
