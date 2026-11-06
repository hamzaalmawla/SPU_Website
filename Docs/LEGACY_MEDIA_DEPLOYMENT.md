# Legacy Media Deployment

The legacy imports preserve source file paths instead of requiring duplicate binary uploads. The Laravel application resolves those paths through `MediaUrlResolver::resolveLegacy()`.

## Same cPanel Public Root

When the old files remain under the same public web root, leave `LEGACY_MEDIA_BASE_URL` unset. A stored path such as `news/images/photo.jpg` resolves to `/news/images/photo.jpg`.

## Separate Legacy Directory or Host

When the old files remain under a separate public directory or hostname, set the base URL in the production environment:

```dotenv
LEGACY_MEDIA_ENABLED=true
LEGACY_MEDIA_BASE_URL=https://legacy.example.test
```

The base URL is joined with each stored legacy path. Do not use a filesystem path in `LEGACY_MEDIA_BASE_URL`; it must be a browser-accessible URL or public path prefix.

## Imported Modules

The path bridge covers:

- News and announcements: cover images and article attachments.
- Research: publication images and deferred publication files.
- Alumni and honor students: legacy photos.
- Faculty members: photos, CVs, and Arabic CVs.
- Central council members: photos and CV paths.
- Career links: legacy photo paths.
- Existing `MediaAsset` records using the `legacy` disk.

## Deployment Checks

After uploading the Laravel application and database to cPanel:

1. Keep the legacy public directories unchanged.
2. Set `LEGACY_MEDIA_ENABLED=true`.
3. Set `LEGACY_MEDIA_BASE_URL` only if the old files are not under the new site's public root.
4. Run `php artisan migrate --force`.
5. Run the public media smoke test for one news cover, one attachment, one research file, one alumni photo, one honor-student photo, and one faculty CV.
6. Check the browser network panel for HTTP 200 responses from the legacy paths.

The database alone cannot make a file available if the old public directory is removed or blocked by the web server. This bridge avoids copying files, but it still depends on those old paths remaining reachable.
