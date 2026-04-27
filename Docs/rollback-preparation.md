# Rollback Preparation

## 1. Rollback Threshold Definitions

| Metric | Threshold | Action |
|--------|-----------|--------|
| Unresolved legacy URL spike | > 50 unique URLs/hour sustained for 2+ hours | Investigate; consider rollback if user-facing |
| Homepage rendering failure | Any locale returns 500 | Immediate rollback |
| Admin panel inaccessible | `/admin` returns 500 or auth loop | Immediate rollback |
| SEO regression | Canonical/hreflang errors on > 10% of pages | Rollback within 1 hour |
| Redirect continuity failure rate | > 5% of sampled legacy URLs fail | Investigate; rollback if not resolvable in 30 min |
| File continuity failure rate | > 10% of sampled legacy file URLs fail | Investigate; rollback if not resolvable in 1 hour |
| Cache failure | Public pages consistently uncached (MISS on all requests) | Investigate; not a rollback trigger alone |
| Database errors | Persistent query failures or connection issues | Immediate rollback |

## 2. Cutover Abort Criteria

The cutover should be aborted (not started or halted mid-process) if any of the following are true before DNS switch:

- `launch:validate` command exits with code 1 (critical check failure)
- Database migration has unresolved errors
- Redirect rule validation reports conflicts (`continuity:validate-redirects` fails)
- Homepage does not render for both AR and EN locales
- Admin panel is not accessible with valid credentials
- SSL certificate is not valid for the target domain
- DNS propagation test shows unexpected results

## 3. Pre-Cutover Snapshot Expectations

Before initiating cutover:

- Full MySQL database dump stored in a dated, labeled backup location
- Redis cache state is expendable (will be rebuilt via `cache:warm`)
- Media/file storage snapshot or sync verification complete
- `.env` configuration backed up separately
- Current production application code tagged in version control
- Load balancer health check endpoints verified

## 4. Continuity Rollback Expectations

If rollback is triggered after cutover:

- DNS reverts to previous application server
- Database restores from pre-cutover snapshot
- Legacy redirect rules remain in the old system (no data loss)
- Unresolved legacy request logs from the new system are preserved for analysis
- File continuity mappings are not affected (stored in database, restored with snapshot)
- Any content changes made in the new admin panel after cutover are lost
- Cache is cleared on rollback; old system rebuilds its own cache

Post-rollback verification:

- Confirm old homepage renders correctly for AR/EN
- Confirm legacy URLs that were working before cutover still work
- Confirm admin panel on old system is accessible
- Review unresolved request logs from the brief new-system window for insights

## 5. Unresolved Continuity Spike Monitoring

After cutover, monitor the `unresolved_legacy_requests` table for spikes:

### Monitoring approach

- Run `continuity:report-unresolved --since="1 hour ago"` every 15 minutes during the first 24 hours
- Alert if unique unresolved URLs exceed 50/hour for 2 consecutive checks
- Distinguish between page-type and file-type unresolved requests

### Triage process

1. Check if unresolved URLs are legitimate legacy paths missing from redirect rules
2. Check if unresolved URLs are bot/crawler noise (check user_agent patterns)
3. For legitimate missing redirects: add exact redirect rules via admin or direct DB insert
4. For file-type misses: check if files exist in media storage but lack inventory mapping
5. For persistent high-volume misses: consider adding pattern rules to catch URL families

### Escalation

- If unresolved spike is caused by a systematic mapping gap (entire URL family missing), escalate to engineering for pattern rule creation
- If spike correlates with external link sources (referrer analysis), consider reaching out to linking sites
- If spike exceeds rollback threshold and cannot be resolved within the defined window, initiate rollback procedure
