# Secrets Rotation Policy

## Scope

This policy covers Laravel application secrets, webhook secrets, Sentry DSNs, TOTP recovery codes, database credentials, Redis credentials, and CI/CD secrets.

## Rotation Triggers

- Rotate immediately after suspected compromise, staff departure with secret access, accidental disclosure, or vendor incident.
- Rotate production operational secrets at least every 180 days.
- Rotate webhook secrets when integrating or removing external systems.

## Rules

- Inject secrets through the deployment platform or CI/CD secret store, never through committed files.
- Keep `.env`, `.env.production`, and generated key material out of git.
- Generate `APP_KEY`, `WEBHOOK_SECRET`, database passwords, and Redis passwords with cryptographically secure random values.
- Record rotation time, actor, affected environment, and validation outcome in the incident or maintenance log.

## Laravel APP_KEY

Changing `APP_KEY` invalidates encrypted data, including encrypted sessions, TOTP secrets, and recovery codes. Rotate only with a migration plan that re-encrypts stored encrypted fields or accepts forced 2FA re-enrollment.

## Validation

- Run `php artisan config:clear` and redeploy after secret changes.
- Verify admin login, 2FA challenge, preview token generation, webhook signature verification, Sentry event capture, and database/cache connectivity.
