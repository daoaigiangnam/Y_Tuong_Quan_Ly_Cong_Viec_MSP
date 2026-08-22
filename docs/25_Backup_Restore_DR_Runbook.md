# Backup, Restore and Disaster Recovery Runbook

## Backup policy

### Database

- Full backup before every production release.
- Scheduled full backup at least daily.
- Retain backups according to the organization's retention policy.
- Store at least one copy outside the primary application host.
- Protect backup credentials and encryption keys separately from application credentials.

### Application

Back up or version-control deployment artifacts, configuration templates, cron definitions and uploaded business files as applicable.

## Restore verification

A backup is not considered usable until a restore test has succeeded.

1. Provision an isolated restore environment.
2. Restore the selected database backup.
3. Verify schema/migration version.
4. Verify reference data and representative business records.
5. Run application smoke tests.
6. Run RBAC/security checks.
7. Record restore duration, backup identifier and result.

## RPO/RTO

Define these values with the business before production:

- **RPO:** maximum acceptable data loss window.
- **RTO:** maximum acceptable service restoration time.

Do not declare DR readiness until both targets have an owner and a tested recovery procedure.

## Disaster recovery sequence

1. Declare the incident and assign an incident owner.
2. Identify whether the primary environment is recoverable.
3. Protect the latest known-good backup.
4. Provision/reuse the recovery environment.
5. Restore database and required application artifacts.
6. Apply only the migrations/configuration compatible with the restored release.
7. Validate authentication, RBAC, core modules, audit and scheduled jobs.
8. Redirect traffic when validation passes.
9. Monitor the recovered environment.
10. Document the incident and recovery evidence.

## DR acceptance criteria

- Restore test passes.
- Backup integrity is verified.
- RPO/RTO targets are documented.
- Recovery owner is identified.
- Recovery runbook is executable by another qualified operator.
- Evidence is retained for audit.
