# Measuring v2 performance

I reached three different conclusions about this site's capacity in four days.
All three were honestly measured and two were wrong, because no two measurements
were taken the same way. This file exists so the next person does not repeat
that.

**Record the method with every number, or the number is not evidence.**

---

## The protocol

State all six of these, every time:

| | |
|---|---|
| **Client** | `curl`, forced to `--http1.1` |
| **Process model** | one process per request, not one client multiplexing |
| **Vantage point** | where you ran it from — this is not neutral |
| **n and concurrency** | both, separately |
| **Cache state** | `-H 'Cache-Control: no-cache'` to bypass the page cache, and confirm it with `X-Cache: BYPASS`. State which path you measured. |
| **Repetitions** | three runs; report the spread, not one median |

`--http1.1` is not optional. Forcing it changed a median from 23.08s to 15.58s
on an identical test — HTTP/2 multiplexes many requests over one connection with
a 64KB flow-control window, so a concurrency test over h2 measures the window as
much as the server.

**`?cb=N` does not bypass this application's page cache, and an earlier version
of this table said it did.** `CachePublicPages::normalizeQuery()` builds the
cache key from a per-route allow-list of query parameters and drops everything
else before hashing, so `cb` never reaches the key and every request in a sweep
lands on one cached entry. Anyone following that instruction would have measured
the cache and labelled it a fresh render. `Cache-Control: no-cache` is matched by
`hasPrivateRequestHeaders()` and genuinely bypasses — and unlike a cache-buster,
it says so in the response, which is why the header is the thing to check rather
than the request.

---

## Always baseline against the legacy site

`spu.edu.sy` and `v2.spu.edu.sy` share an account, an nginx, and an uplink. It is
the only control that separates "this migration is slow" from "this host is
slow", and it costs one extra command.

Measured 2026-09-01, 15 concurrent, `--http1.1`, 30 requests:

| Target | Bytes | Median | Max | OK |
|---|---:|---:|---:|---|
| legacy `spu.edu.sy` homepage | 27,842 | **1.15s** | 2.30s | 30/30 |
| v2 `/ar/admissions` | 114,640 | **23.08s** | 50.00s | 30/30 |

The legacy site is fine. So the constraint is not the host in general.

---

## What is actually going on

Response size, not PHP. A size sweep over **static files** — no PHP, no database,
no framework — at 15 concurrent:

| File | Bytes | Median | Max | OK |
|---|---:|---:|---:|---|
| `corsera.webp` | 2,722 | 0.95s | 1.83s | 30/30 |
| `arab-uni.webp` | 9,404 | 0.82s | 1.23s | 30/30 |
| `about-highlight-1.webp` | 17,746 | 1.04s | 1.84s | 30/30 |
| `campus-dental.webp` | 23,512 | 1.00s | 2.11s | 30/30 |
| `campus-feature-01.webp` | 38,872 | **4.09s** | 13.00s | 30/30 |
| `campus-feature-02.webp` | 59,290 | 5.02s | 24.66s | 30/30 |
| `dental-clin-lab.webp` | 81,952 | 6.78s | 50.00s | **25/30** |
| `campus-hospital.webp` | 100,160 | 27.88s | 50.01s | **20/30** |

Flat and reliable to about 24KB. A knee somewhere between 24KB and 39KB. Above
it, times climb and requests start failing outright.

Time-to-first-byte stays flat at ~1.0s across every concurrency level, which
exonerates PHP: worker saturation queues *before* the first byte, so TTFB would
rise. It does not.

**Above the knee the numbers are also unstable.** The same 100KB file at
concurrency 1 measured 25.58s once and 1.20s at concurrency 4 minutes later.
Report ranges above 24KB, never single medians.

### What this is consistent with, and what is not yet proven

A knee near 32KB is what an nginx reverse proxy does when a response exceeds
`proxy_buffers` and every body starts spilling to `proxy_temp` on shared disk.
That fits the threshold, the instability, and the fact that static files are
affected identically.

It is **not proven**. Two questions settle it and neither can be answered from
outside the server:

- is `mod_deflate` loaded in the Apache runtime?
- does `Accept-Encoding` survive nginx's hop to Apache?

Ask the host for both, and for the effective `proxy_buffers` and `gzip_proxied`
values. `gzip_proxied` defaults to `off`, which alone would explain why nothing
proxied is ever compressed.

---

## Why this makes compression the whole game

Compression does not merely make pages smaller. It moves them **back under the
knee**:

| Page | Raw | gzip | Ratio |
|---|---:|---:|---:|
| `/ar` | 183,527 | 23,080 | 8.0× |
| `/en` | 168,353 | 20,418 | 8.2× |
| `/ar/admissions` | 114,640 | 13,263 | 8.6× |
| `/ar/news` | 120,200 | 14,147 | 8.5× |

Every one lands in the 13–23KB band that measures flat and reliable. That is
also, not coincidentally, the band the legacy site's 27.8KB homepage sits in —
which is why the old site feels fine on the same infrastructure.

The same applies to images: the WebP derivative pipeline matters here for the
same reason, because it moves large photographs under the threshold rather than
merely making them smaller.

---

## Result, measured 2026-09-02

Compression shipped on 1 September and did nothing, because nginx removes
`Accept-Encoding` before PHP sees it — a request sending
`gzip, deflate, br` arrives at PHP with the header absent. The application was
declining to compress for a client it had been told could not decompress. With
`COMPRESS_WITHOUT_ACCEPT_ENCODING` enabled it compresses regardless.

Same protocol as above: local macOS vantage, `curl --http1.1`, one process per
request, unique `?cb=N`, 15 concurrent, n=30, three runs.

Served from the page cache (`X-Cache: HIT`):

| Target | Bytes | Median (3 runs) | Max | OK |
|---|---:|---|---:|---|
| legacy `spu.edu.sy` homepage | 27,842 | 1.01 / 0.96 / 0.89s | 1.35s | 30/30 |
| v2 `/ar` | 23,538 | 1.01 / 1.01 / 1.04s | 1.36s | 30/30 |
| v2 `/ar/admissions` | 13,602 | 0.98 / 0.97 / 0.99s | 1.51s | 30/30 |

Full render, page cache bypassed (`X-Cache: BYPASS`) — what the first visitor to
a page gets after a deploy:

| Target | Bytes | Median (3 runs) | Max | OK |
|---|---:|---|---:|---|
| v2 `/ar` | 23,538 | 1.14 / 1.23 / 1.19s | 1.72s | 30/30 |
| v2 `/ar/admissions` | 13,602 | 1.01 / 0.98 / 1.04s | 1.79s | 30/30 |

`/ar/admissions` was **23.08s median, 50.00s max** on 1 September. It is now
around 1s on both paths, and v2 is indistinguishable from the legacy site under
identical load. That the uncached path is barely slower than the cached one is
the useful detail: rendering was never what made this site slow.

Two things worth keeping straight about this number. It is a **23× improvement
on the same page from the same vantage**, and it is not a 23× improvement in the
application: nothing about the rendering changed. The site was spending that
time pushing bytes through a link that could not take them. And it confirms the
size threshold was the real constraint — the prediction made from the sweep
above held, which is the only reason to trust the sweep.

What has still not been measured: real peak concurrency, and any vantage point
inside Syria.

---

## What to measure before cutover

1. Rerun the size sweep and the legacy baseline. Three runs.
2. Read the real peak concurrency out of the legacy access logs. Every number
   above is against an invented load level; the logs know the true one, and it
   spikes at registration and results week rather than on average.
3. Repeat from a second vantage point, ideally inside Syria. One client on one
   path is not the audience.
