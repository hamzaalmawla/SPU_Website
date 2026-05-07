# Incident Response Runbook

## Severity

- Critical: active compromise, leaked production secret, unauthorized admin access, or public data exposure.
- High: blocked exploit attempt, malware upload, repeated account lockouts, or suspicious privileged changes.
- Medium: dependency advisory, failed webhook signature spikes, or suspicious but contained behavior.

## First Response

- Preserve evidence before cleanup: logs, audit records, Sentry event IDs, affected user IDs, request IDs, IPs, and timestamps.
- Disable or lock affected admin accounts.
- Rotate exposed secrets according to `Docs/security/secrets-rotation-policy.md`.
- If admin access is suspected, invalidate sessions and require 2FA recovery/re-enrollment where needed.

## Investigation

- Review `audit_logs` for privileged actions: login, failed login, lockout, homepage publish, page publish, settings update, menu update, media upload/delete.
- Review application logs and Sentry issues for matching timestamps.
- Confirm whether draft content, unpublished content, or out-of-scope faculty media was accessed.
- Check dependency advisories with `composer audit` and `npm audit`.

## Containment

- Lock compromised accounts.
- Disable vulnerable integrations or webhook consumers.
- Temporarily disable public forms or uploads if abuse continues.
- Deploy hotfixes through the normal CI path unless active exploitation requires emergency deployment.

## Recovery

- Run `php artisan test`, `./vendor/bin/pint --test`, `composer audit`, and `npm audit` before release.
- Confirm Sentry is receiving events after deployment.
- Confirm scheduled `audit:prune` remains active after recovery.

## Post-Incident

- Document root cause, affected data, timeline, user impact, and permanent fixes.
- Add or update regression tests for the exploited path.
- Review Sentry alert rules for authentication failures, 500-rate spikes, and suspicious admin activity.
