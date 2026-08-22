# Production Go-Live Readiness

## Purpose

This document is the operational gate between UAT acceptance and production deployment for the MSP platform.

## Go-Live gates

- [ ] UAT Readiness Gate is green.
- [ ] Release Regression Gate is green.
- [ ] Platform Integration Gate is green.
- [ ] Security/RBAC Gate is green.
- [ ] Database migrations are reviewed and reversible where applicable.
- [ ] Production configuration is externalized; no credentials are committed.
- [ ] Database backup has been completed immediately before release.
- [ ] Backup restore has been verified in a non-production environment.
- [ ] Application health check is available.
- [ ] Error logging and audit logging are enabled.
- [ ] Rollback package/version is identified.
- [ ] Change owner and release owner are identified.
- [ ] Business owner has approved the release window.
- [ ] Monitoring/contact escalation path is confirmed.

## Release sequence

1. Freeze the release candidate.
2. Record commit SHA, database migration version and configuration version.
3. Take a production database backup.
4. Verify backup integrity and restore metadata.
5. Put the application into maintenance/read-only mode if required.
6. Deploy application artifacts.
7. Apply database migrations in the documented order.
8. Run smoke checks: login, RBAC, customer, service, ticket/task, audit and health endpoint.
9. Run post-deployment validation.
10. Remove maintenance mode.
11. Monitor errors, latency, queue/cron execution and database health.
12. Record the release result and evidence.

## Rollback decision

Rollback immediately when a release causes data corruption, authentication/RBAC failure, critical workflow failure, unrecoverable application errors, or a business-critical service outage.

Rollback sequence:

1. Stop or isolate the affected release.
2. Restore the previous application artifact/version.
3. Restore the database only when the migration/data-change strategy explicitly requires it.
4. Re-run smoke and security checks.
5. Confirm business-critical workflows.
6. Record incident, evidence and root cause.

## Production evidence

For every release retain:

- Release commit SHA
- Migration version
- Backup timestamp and backup identifier
- Deployment timestamp
- Smoke-test result
- Rollback result or rollback verification
- Approver/release owner
- Incident/change reference

## Exit criteria

The release is considered production-ready only when all mandatory gates are green and the evidence above is available.
