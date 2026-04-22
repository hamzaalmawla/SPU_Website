# Website Migration Quick Reference

## The Five Things That Protect Rankings Most

1. one best destination for every valuable old URL
2. permanent redirects for permanent moves
3. canonical and `hreflang` correctness
4. preserved files, PDFs, and archive visibility
5. aggressive post-launch monitoring

## Never Do This

- do not redirect everything to the homepage
- do not delete important legacy PDFs
- do not launch indexable staging
- do not block `noindex` targets in `robots.txt`
- do not assume the new homepage is enough for full cutover

## Launch Readiness Checklist

- [ ] top legacy landing pages mapped
- [ ] top linked files mapped
- [ ] sitemap live
- [ ] canonical live
- [ ] `hreflang` live
- [ ] unresolved-request logging live
- [ ] Search Console ready
- [ ] Bing Webmaster Tools ready
- [ ] admin path conflict resolved

## Launch-Day Checks

- [ ] Arabic homepage
- [ ] English homepage
- [ ] representative current-scope landing pages
- [ ] representative legacy query-string URLs
- [ ] representative PDFs and files
- [ ] sitemap index
- [ ] `robots.txt`

## First 24 Hours

Watch:

- [ ] `404` spikes
- [ ] unresolved legacy requests
- [ ] redirect misses
- [ ] file failures
- [ ] canonical mistakes
- [ ] unexpected `noindex`

## First Week Priorities

1. fix high-traffic misses
2. fix high-backlink misses
3. fix file and PDF failures
4. fix multilingual canonical issues
5. fix lower-value archive misses

## Code Helpers

Use these local commands before launch:

- `php artisan migration:inspect-legacy-url "https://spu.edu.sy/index.php?page=show&dir=items&item_id=12&lang=1"`
- `php artisan migration:audit-redirect-map storage/app/redirects.csv --json=storage/app/redirect-audit.json`

These commands help catch:

- duplicate old URLs
- homepage fallbacks
- file-to-homepage mistakes
- locale mismatches
- redirect chain risk

## Webometrics Reminder

As verified on `2026-04-21`, the official methodology emphasizes:

- Visibility
- Transparency
- Excellence

The old "Presence" metric is discontinued.

That means the goal is not random file volume.
The goal is a strong, discoverable, stable institutional web footprint.
